<?php

use MercadoPagoPro\Buyer;
use MercadoPagoPro\Config;
use MercadoPagoPro\Gateway;
use MercadoPagoPro\Payloads;

$params = function (array $over = []): array {
    return $over + gw() + [
        'invoiceid' => 501,
        'amount' => '59.90',
        'currency' => 'BRL',
        'systemurl' => 'https://loja.example.com/',
        'companyname' => 'Loja Exemplo Ltda',
        'clientdetails' => client(),
    ];
};
$now = 1_800_000_000;
$created = ['status' => 201, 'body' => ['id' => 'ORDTST01KS5AJ6HTK2HRQ3XJ3C2JCKP9', 'status' => 'created', 'checkout_url' => 'https://www.mercadopago.com.br/checkout/v1/redirect?order_id=ORDTST01KS5AJ6HTK2HRQ3XJ3C2JCKP9']];
$gateway = function (FakeHttp $http, ?MemoryStore $store = null, ?FakeBridge $b = null): Gateway {
    return new Gateway($http, $store ?? new MemoryStore(), $b ?? new FakeBridge(), new FakeData());
};

return [
    'Order: tipo, modo, valor em texto, referência, validade, item completo e config (descrição, URLs, parcelas, boleto, 3DS)' => function () {
        $c = Config::fromParams(gw(['installments' => '6', 'descriptor' => 'Loja*Éxemplo!! 2026', 'link_hours' => '48']));
        $o = Payloads::order(501, 59.9, 'https://loja.example.com/', $c, 'Fatura #501', [], "Plano VPS\n(01/12 - 31/12) ");
        eq(['online', 'manual', '59.90', '501', 'PT48H', 'Fatura #501'], [$o['type'], $o['processing_mode'], $o['total_amount'], $o['external_reference'], $o['expiration_time'], $o['description']]);
        eq([['external_code' => 'INV-501', 'title' => 'Fatura #501', 'category_id' => 'services', 'quantity' => 1, 'unit_price' => '59.90', 'description' => 'Plano VPS (01/12 - 31/12)']], $o['items']);
        $back = 'https://loja.example.com/modules/gateways/callback/mercadopagopro.php?return=1&inv=501';
        eq(['statement_descriptor' => 'LOJAEXEMPLO 2',
            'online' => ['success_url' => $back, 'failure_url' => $back, 'pending_url' => $back, 'auto_return' => 'approved', 'transaction_security' => ['validation' => 'on_fraud_risk', 'liability_shift' => 'required']],
            'payment_method' => ['max_installments' => 6, 'not_allowed_types' => ['ticket']]], $o['config']);
        ok(!isset($o['payer']) && !isset($o['additional_info']) && !isset($o['integration_data']) && !isset($o['platform_id']), 'sem payer vazio e sem platform_id');
        $long = Payloads::order(1, 1, 'https://x.example', $c, 't', [], str_repeat('abc ', 100));
        eq(256, mb_strlen($long['items'][0]['description']));
    },
    'Order: 3DS desligado não manda transaction_security; "Sempre" manda validation always; boleto liberado não manda not_allowed_types' => function () {
        $c = Config::fromParams(gw(['three_ds' => 'off', 'exclude_ticket' => '']));
        $o = Payloads::order(1, 10, 'https://x.example', $c, 't');
        ok(!isset($o['config']['online']['transaction_security']));
        $always = Payloads::order(1, 10, 'https://x.example', Config::fromParams(gw(['three_ds' => 'always'])), 't');
        eq(['validation' => 'always', 'liability_shift' => 'required'], $always['config']['online']['transaction_security']);
        eq('on_fraud_risk', Config::fromParams(gw(['three_ds' => 'x']))->threeDs, 'valor desconhecido vira o padrão');
        eq(['max_installments' => 12], $o['config']['payment_method']);
    },
    'Reembolso de order: parcial com uma transação e valor em texto' => function () {
        eq(['transactions' => [['id' => 'PAY01', 'amount' => '20.50']]], Payloads::orderRefund('PAY01', 20.5));
    },
    'Descrição na fatura do cartão: sempre preenchida (configurada, nome da empresa ou padrão), 13 caracteres' => function () {
        eq('MINHA LOJA', Config::fromParams(gw(['descriptor' => 'minha loja', 'companyname' => 'Outra']))->descriptor);
        eq('CONSTRUCAO SA', Config::fromParams(gw(['companyname' => 'Construção S.A. Serviços']))->descriptor);
        eq('MERCADOPAGO', Config::fromParams(gw(['companyname' => '***']))->descriptor);
        eq('MERCADOPAGO', Config::fromParams(gw())->descriptor);
    },
    'payer da order: first_name, last_name, e-mail, telefone, CPF, endereço com bairro e cidade' => function () {
        eq(['email' => 'cliente@example.com', 'first_name' => 'Ana', 'last_name' => 'Souza', 'phone' => ['area_code' => '47', 'number' => '999998888'],
            'identification' => ['type' => 'CPF', 'number' => '52998224725'],
            'address' => ['zip_code' => '89000000', 'street_name' => 'Rua das Flores', 'street_number' => '100', 'neighborhood' => 'Centro', 'city' => 'Blumenau']],
            Buyer::orderPayer(client(), 'cliente@example.com', '529.982.247-25'));
        eq([], Buyer::orderPayer(client(), '', '529.982.247-25'), 'sem e-mail não vai payer (a API exige email)');
    },
    'additional_info: cadastro, primeira compra online e última compra' => function () {
        eq(['payer.registration_date' => '2021-03-25T00:00:00.000-03:00', 'payer.is_first_purchase_online' => false, 'payer.last_purchase' => '2026-10-02T14:30:00.000-03:00'],
            Buyer::additionalInfo('2021-03-25', '2026-10-02 14:30:00'));
        eq(['payer.is_first_purchase_online' => true], Buyer::additionalInfo('0000-00-00', ''));
    },
    'Link em Produção: POST /v1/orders com Bearer e X-Idempotency-Key; payer e additional_info completos; botão para o checkout_url' => function () use ($params, $now, $created, $gateway) {
        $http = new FakeHttp($created);
        $html = $gateway($http)->link($params(['mode' => 'live']), $now);
        $r = $http->requests[0];
        eq(['POST', 'https://api.mercadopago.com/v1/orders'], [$r['method'], $r['url']]);
        eq('Bearer APP_USR-live-token', $r['headers']['Authorization']);
        ok(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $r['headers']['X-Idempotency-Key']) === 1);
        eq('cliente@example.com', $r['body']['payer']['email']);
        eq('CPF', $r['body']['payer']['identification']['type']);
        eq(false, $r['body']['additional_info']['payer.is_first_purchase_online']);
        eq('Plano VPS 2 GB (18/11/2026 - 17/12/2026) · Desconto 5OFF', $r['body']['items'][0]['description']);
        eq('LOJA EXEMPLO', $r['body']['config']['statement_descriptor']);
        ok(strpos($html, 'href="https://www.mercadopago.com.br/checkout/v1/redirect?order_id=ORDTST01KS5AJ6HTK2HRQ3XJ3C2JCKP9"') !== false, $html);
        ok(strpos($html, 'Pagar com Mercado Pago') !== false);
    },
    'Link em Teste: sem o e-mail do cliente (então sem payer); com o comprador de teste, payer completo' => function () use ($params, $now, $created, $gateway) {
        $http = new FakeHttp($created, $created);
        $gateway($http)->link($params(), $now);
        ok(!isset($http->requests[0]['body']['payer']));
        ok(isset($http->requests[0]['body']['additional_info']['payer.registration_date']));
        $gateway($http)->link($params(['test_payer_email' => 'test_user_123@testuser.com']), $now);
        eq('test_user_123@testuser.com', $http->requests[1]['body']['payer']['email']);
        eq('Ana', $http->requests[1]['body']['payer']['first_name']);
    },
    'Chave do cache: 8 caracteres; muda com modo ou conteúdo da order' => function () {
        $c = Config::fromParams(gw());
        $base = Payloads::fingerprint($c, Payloads::order(501, 10, 'https://x.example', $c, 't', ['email' => 'a@x.example']));
        ok(preg_match('/^t:[0-9a-f]{6}$/', $base) === 1, $base);
        $live = Config::fromParams(gw(['mode' => 'live']));
        $variants = [
            Payloads::fingerprint($live, Payloads::order(501, 10, 'https://x.example', $live, 't', ['email' => 'a@x.example'])),
            Payloads::fingerprint($c, Payloads::order(501, 10, 'https://x.example', $c, 't', ['email' => 'b@x.example'])),
            Payloads::fingerprint($c, Payloads::order(501, 10, 'https://x.example', $c, 't', ['email' => 'a@x.example'], 'linha')),
            Payloads::fingerprint(Config::fromParams(gw(['three_ds' => 'off'])), Payloads::order(501, 10, 'https://x.example', Config::fromParams(gw(['three_ds' => 'off'])), 't', ['email' => 'a@x.example'])),
        ];
        eq(4, count(array_unique($variants)));
        ok(!in_array($base, $variants, true));
    },
    'Cache: reaproveita a order com o mesmo conteúdo; link de preferência antigo (1.x) é refeito como order' => function () use ($params, $now, $created, $gateway) {
        $store = new MemoryStore();
        $store->rows[501] = ['invoice_id' => 501, 'mode' => 't:000000', 'amount' => '59.90', 'pref_id' => '3854449797-0ccc3df9', 'init_point' => 'https://www.mercadopago.com.br/antiga', 'expires_at' => $now + 72 * 3600];
        $http = new FakeHttp($created);
        $g = $gateway($http, $store);
        $g->link($params(), $now);
        $g->link($params(), $now + 3600);
        eq(1, count($http->requests), 'uma order só');
        eq('ORDTST01KS5AJ6HTK2HRQ3XJ3C2JCKP9', $store->rows[501]['pref_id']);
        $http->responses = [$created];
        $g->link($params(['amount' => '49.90']), $now + 3600);
        eq(2, count($http->requests), 'valor mudou');
    },
    'Erro da API de Orders (errors[] com code, message e details) vai legível ao log' => function () use ($params, $now, $gateway) {
        $b = new FakeBridge();
        $gateway(new FakeHttp(['status' => 400, 'body' => ['errors' => [['code' => 'invalid_properties', 'message' => 'The following properties are invalid', 'details' => ['config.online.transaction_security.liability_shift must be one of [...]']]]]]), null, $b)->link($params(), $now);
        eq('HTTP 400: invalid_properties The following properties are invalid (config.online.transaction_security.liability_shift must be one of [...])', $b->logs[0][1]['erro']);
    },
    'Moeda diferente de BRL: aviso, sem order' => function () use ($params, $now, $gateway) {
        $http = new FakeHttp();
        $html = $gateway($http)->link($params(['currency' => 'USD']), $now);
        ok(strpos($html, 'só aceita pagamentos em reais') !== false, $html);
        eq([], $http->requests);
    },
    'Erro da API: mensagem amigável; log sem token, e-mail nem documento' => function () use ($params, $now, $gateway) {
        $b = new FakeBridge();
        $html = $gateway(new FakeHttp(['status' => 400, 'body' => ['message' => 'invalid TEST-test-token for cliente@example.com 529.982.247-25']]), null, $b)->link($params(), $now);
        ok(strpos($html, 'Não foi possível gerar o link') !== false, $html);
        eq(['Erro ao criar a order'], $b->results());
        eq('HTTP 400: invalid *** for *** ***', $b->logs[0][1]['erro']);
        assertCleanLogs($b);
    },
    'Reembolso de order total: consulta a order e POST /refund sem corpo' => function () use ($params, $gateway) {
        $http = new FakeHttp(['status' => 200, 'body' => order()], ['status' => 201, 'body' => ['id' => 'ORDTST01KS5AJ6HTK2HRQ3XJ3C2JCKP9', 'status' => 'refunded', 'status_detail' => 'refunded', 'transactions' => ['refunds' => [['id' => 'REF01X', 'amount' => '59.90', 'status' => 'processed']]]]]);
        $r = $gateway($http)->refund($params(['transid' => 'ORDTST01KS5AJ6HTK2HRQ3XJ3C2JCKP9', 'amount' => '59.90']));
        eq(['success', 'REF01X', 'total'], [$r['status'], $r['transid'], $r['rawdata']['tipo']]);
        eq(['POST', 'https://api.mercadopago.com/v1/orders/ORDTST01KS5AJ6HTK2HRQ3XJ3C2JCKP9/refund', null], [$http->requests[1]['method'], $http->requests[1]['url'], $http->requests[1]['body']]);
        ok(isset($http->requests[1]['headers']['X-Idempotency-Key']));
    },
    'Reembolso de order parcial: transação = pagamento da order; acima do pago é recusado sem chamar a API' => function () use ($params, $gateway) {
        $http = new FakeHttp(['status' => 200, 'body' => order()], ['status' => 201, 'body' => ['status' => 'processed', 'status_detail' => 'partially_refunded', 'transactions' => ['refunds' => [['id' => 'REF02', 'amount' => '20.50']]]]]);
        $r = $gateway($http)->refund($params(['transid' => 'ORDTST01KS5AJ6HTK2HRQ3XJ3C2JCKP9', 'amount' => '20.50']));
        eq(['success', 'parcial'], [$r['status'], $r['rawdata']['tipo']]);
        eq(['transactions' => [['id' => 'PAY01KS5AJ6HTK2HRQ3XJ3C2JCKPA', 'amount' => '20.50']]], $http->requests[1]['body']);
        $http = new FakeHttp(['status' => 200, 'body' => order()]);
        eq('declined', $gateway($http)->refund($params(['transid' => 'ORDTST01KS5AJ6HTK2HRQ3XJ3C2JCKP9', 'amount' => '99.00']))['status']);
        eq(1, count($http->requests));
    },
    'Reembolso de pagamento antigo (transid numérico): POST /v1/payments/{id}/refunds' => function () use ($params, $gateway) {
        $http = new FakeHttp(['status' => 201, 'body' => ['id' => 999, 'status' => 'approved', 'amount' => 20.5]]);
        $r = $gateway($http)->refund($params(['transid' => '1234567890', 'amount' => '20.50']));
        eq(['success', '999'], [$r['status'], $r['transid']]);
        eq(['POST', 'https://api.mercadopago.com/v1/payments/1234567890/refunds', ['amount' => 20.5]], [$http->requests[0]['method'], $http->requests[0]['url'], $http->requests[0]['body']]);
        eq('declined', $gateway(new FakeHttp(['status' => 400, 'body' => ['message' => 'amount too high']]))->refund($params(['transid' => '1234567890', 'amount' => '20.50']))['status']);
        eq('error', $gateway(new FakeHttp(['status' => 503, 'body' => null]))->refund($params(['transid' => '1234567890', 'amount' => '20.50']))['status']);
        eq('declined', $gateway(new FakeHttp())->refund($params(['transid' => 'abc', 'amount' => '1']))['status'], 'transid inválido não chama a API');
        eq('error', $gateway(new FakeHttp())->refund($params(['transid' => '1', 'amount' => '0']))['status']);
    },
    'Configuração: padrões (parcelas 12, boleto excluído, 3DS ligado, 72 h) e limites' => function () {
        $c = Config::fromParams(['mode' => 'live', 'token_live' => ' tok ']);
        eq([false, 'tok', 12, true, 'on_fraud_risk', 72, '', ''], [$c->test, $c->token, $c->installments, $c->excludeTicket, $c->threeDs, $c->hours, $c->testPayerEmail, $c->docField]);
        $c = Config::fromParams(gw(['installments' => '99', 'link_hours' => '0', 'exclude_ticket' => '', 'three_ds' => 'always', 'doc_field' => 'cf:7']));
        eq([true, 'TEST-test-token', 12, false, 'always', 72, 'cf:7'], [$c->test, $c->token, $c->installments, $c->excludeTicket, $c->threeDs, $c->hours, $c->docField]);
        eq('', Config::fromParams(gw(['doc_field' => 'x; drop']))->docField);
        eq('', Config::fromParams(gw(['doc_field' => 'auto']))->docField);
    },
];
