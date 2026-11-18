<?php

use MercadoPagoPro\Callback;
use MercadoPagoPro\Client;
use MercadoPagoPro\Config;
use MercadoPagoPro\Signature;

$make = function (array $gw, FakeHttp $http, FakeBridge $b): Callback {
    $c = Config::fromParams($gw);
    return new Callback($c, new Client($c->token, $http), $b, 'https://loja.example.com/');
};

return [
    'Webhook de pagamento aprovado: consulta /v1/payments com Bearer do modo e dá baixa' => function () use ($make) {
        $http = new FakeHttp(['status' => 200, 'body' => payment()]);
        $b = new FakeBridge([501]);
        $r = $make(gw(), $http, $b)->handle(['source_news' => 'webhooks', 'type' => 'payment', 'data_id' => '1234567890'], [], '');
        eq(200, $r['status']);
        eq('GET', $http->requests[0]['method']);
        eq('https://api.mercadopago.com/v1/payments/1234567890', $http->requests[0]['url']);
        eq('Bearer TEST-test-token', $http->requests[0]['headers']['Authorization']);
        eq(1, count($b->payments));
    },
    'IPN ?topic=payment&id=X também dá baixa' => function () use ($make) {
        $b = new FakeBridge([501]);
        $r = $make(gw(), new FakeHttp(['status' => 200, 'body' => payment()]), $b)->handle(['topic' => 'payment', 'id' => '1234567890'], [], '');
        eq([200, 1], [$r['status'], count($b->payments)]);
    },
    'merchant_order, vazio e lixo: 200 sem chamar a API' => function () use ($make) {
        $http = new FakeHttp();
        $cb = $make(gw(), $http, new FakeBridge());
        eq(200, $cb->handle(['topic' => 'merchant_order', 'id' => '1'], [], '')['status']);
        eq(200, $cb->handle(['type' => 'merchant_order', 'data_id' => '1'], [], '')['status']);
        eq(200, $cb->handle([], [], '')['status']);
        eq(200, $cb->handle(['type' => 'payment', 'data_id' => 'x'], [], 'lixo')['status']);
        eq([], $http->requests);
    },
    'Com secret: assinatura válida passa; inválida ou ausente dá 401 sem consultar a API' => function () use ($make) {
        $gw = gw(['secret_test' => 'segredo']);
        $get = ['type' => 'payment', 'data_id' => '1234567890'];
        $good = ['HTTP_X_SIGNATURE' => Signature::sign('segredo', '1234567890', 'req-9', '1700000000'), 'HTTP_X_REQUEST_ID' => 'req-9'];

        $b = new FakeBridge([501]);
        eq(200, $make($gw, new FakeHttp(['status' => 200, 'body' => payment()]), $b)->handle($get, $good, '')['status']);
        eq(1, count($b->payments));

        $http = new FakeHttp();
        $b = new FakeBridge([501]);
        $cb = $make($gw, $http, $b);
        eq(401, $cb->handle($get, ['HTTP_X_SIGNATURE' => Signature::sign('outro', '1234567890', 'req-9', '1700000000'), 'HTTP_X_REQUEST_ID' => 'req-9'], '')['status']);
        eq(401, $cb->handle($get, [], '')['status'], 'sem header');
        eq(401, $cb->handle($get + ['x' => 1], ['HTTP_X_SIGNATURE' => $good['HTTP_X_SIGNATURE'], 'HTTP_X_REQUEST_ID' => 'outro'], '')['status'], 'outro request-id');
        eq([], $http->requests);
        eq([], $b->payments);
        assertCleanLogs($b);
    },
    'Sem secret: webhook sem assinatura é aceito (o pagamento é sempre reconsultado na API)' => function () use ($make) {
        $b = new FakeBridge([501]);
        eq(200, $make(gw(), new FakeHttp(['status' => 200, 'body' => payment()]), $b)->handle(['type' => 'payment', 'data_id' => '1234567890'], [], '')['status']);
        eq(1, count($b->payments));
    },
    'Secret usa o do modo: o de produção não vale em Teste' => function () use ($make) {
        $gw = gw(['secret_live' => 'segredo-prod', 'secret_test' => '']);
        $b = new FakeBridge([501]);
        eq(200, $make($gw, new FakeHttp(['status' => 200, 'body' => payment()]), $b)->handle(['type' => 'payment', 'data_id' => '1234567890'], [], '')['status']);
    },
    'API fora (rede, 429, 5xx): 503 para o Mercado Pago tentar de novo; 404: 200 e log' => function () use ($make) {
        $get = ['type' => 'payment', 'data_id' => '1234567890'];
        foreach ([0, 429, 500, 502] as $s) {
            eq(503, $make(gw(), new FakeHttp(['status' => $s, 'body' => null]), new FakeBridge())->handle($get, [], '')['status'], 'HTTP ' . $s);
        }
        $b = new FakeBridge();
        eq(200, $make(gw(), new FakeHttp(['status' => 404, 'body' => ['message' => 'Payment not found']]), $b)->handle($get, [], '')['status']);
        eq(['Ignorado: pagamento não consultado'], $b->results());
        eq('HTTP 404: Payment not found', $b->logs[0][1]['erro']);
    },
    'Sem token configurado: 200 e log, sem chamar a API' => function () {
        $b = new FakeBridge();
        $cb = new Callback(Config::fromParams(gw(['token_test' => ''])), null, $b, 'https://loja.example.com');
        eq(200, $cb->handle(['type' => 'payment', 'data_id' => '1'], [], '')['status']);
        eq(['Access Token não configurado'], $b->results());
    },
    'Retorno do cliente aprovado: baixa na hora e vai para a fatura com paymentsuccess' => function () use ($make) {
        $b = new FakeBridge([501]);
        $cb = $make(gw(), new FakeHttp(['status' => 200, 'body' => payment()]), $b);
        $r = $cb->handle(['return' => '1', 'inv' => '501', 'payment_id' => '1234567890', 'status' => 'approved'], [], '');
        eq(302, $r['status']);
        eq('https://loja.example.com/viewinvoice.php?id=501&paymentsuccess=true', $r['location']);
        eq(1, count($b->payments));
    },
    'Retorno depois do webhook: transação já registrada encerra, mas o redirecionamento já está decidido' => function () use ($make) {
        $b = new FakeBridge([501]);
        $b->transactions = ['1234567890'];
        $cb = $make(gw(), new FakeHttp(['status' => 200, 'body' => payment()]), $b);
        try {
            $cb->handle(['return' => '1', 'inv' => '501', 'payment_id' => '1234567890'], [], '');
            ok(false, 'devia encerrar');
        } catch (WhmcsExit $e) {
            eq('https://loja.example.com/viewinvoice.php?id=501&paymentsuccess=true', $cb->pendingRedirect);
        }
        eq([], $b->payments);
    },
    'Retorno pendente, recusado, sem pagamento ou com inv inválido' => function () use ($make) {
        $r = $make(gw(), new FakeHttp(['status' => 200, 'body' => payment(['status' => 'pending'])]), new FakeBridge([501]))->handle(['return' => '1', 'inv' => '501', 'payment_id' => '1234567890'], [], '');
        eq('https://loja.example.com/viewinvoice.php?id=501', $r['location']);
        $r = $make(gw(), new FakeHttp(['status' => 200, 'body' => payment(['status' => 'rejected'])]), new FakeBridge([501]))->handle(['return' => '1', 'inv' => '501', 'payment_id' => '1234567890'], [], '');
        eq('https://loja.example.com/viewinvoice.php?id=501&paymentfailed=true', $r['location']);
        $r = $make(gw(), new FakeHttp(), new FakeBridge())->handle(['return' => '1', 'inv' => '501', 'payment_id' => 'null', 'status' => 'null'], [], '');
        eq('https://loja.example.com/viewinvoice.php?id=501', $r['location']);
        $r = $make(gw(), new FakeHttp(), new FakeBridge())->handle(['return' => '1', 'inv' => 'https://evil.example', 'status' => 'rejected'], [], '');
        eq('https://loja.example.com/clientarea.php?action=invoices', $r['location']);
    },
    'Retorno: a fatura do redirecionamento vem do pagamento consultado, não do parâmetro' => function () use ($make) {
        $r = $make(gw(), new FakeHttp(['status' => 200, 'body' => payment(['status' => 'pending', 'external_reference' => '777'])]), new FakeBridge())->handle(['return' => '1', 'inv' => '501', 'payment_id' => '1234567890'], [], '');
        eq('https://loja.example.com/viewinvoice.php?id=777', $r['location']);
    },
];
