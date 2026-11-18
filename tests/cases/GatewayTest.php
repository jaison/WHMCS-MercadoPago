<?php

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
$created = ['status' => 201, 'body' => ['id' => 'pref-1', 'init_point' => 'https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=pref-1']];
$gateway = function (FakeHttp $http, ?MemoryStore $store = null, ?FakeBridge $b = null): Gateway {
    return new Gateway($http, $store ?? new MemoryStore(), $b ?? new FakeBridge(), new FakeData());
};

return [
    'Payload da preferência: item completo, referência, URLs, parcelas, boleto, validade e descrição na fatura' => function () use ($now) {
        $c = Config::fromParams(gw(['installments' => '6', 'descriptor' => 'Loja*Éxemplo!! 2026', 'link_hours' => '48']));
        $p = Payloads::preference(501, 59.9, 'https://loja.example.com/', $c, $now, 'Fatura #501', [], "Plano VPS\n(01/12 - 31/12) ");
        eq([['id' => 'INV-501', 'title' => 'Fatura #501', 'category_id' => 'services', 'quantity' => 1, 'unit_price' => 59.9, 'currency_id' => 'BRL', 'description' => 'Plano VPS (01/12 - 31/12)']], $p['items']);
        eq('501', $p['external_reference']);
        $back = 'https://loja.example.com/modules/gateways/callback/mercadopagopro.php?return=1&inv=501';
        eq(['success' => $back, 'pending' => $back, 'failure' => $back], $p['back_urls']);
        eq('approved', $p['auto_return']);
        eq('https://loja.example.com/modules/gateways/callback/mercadopagopro.php?source_news=webhooks', $p['notification_url']);
        eq(['installments' => 6, 'excluded_payment_types' => [['id' => 'ticket']]], $p['payment_methods']);
        eq(true, $p['expires']);
        eq('2027-01-15T05:00:00.000-03:00', $p['expiration_date_from']);
        eq('2027-01-17T05:00:00.000-03:00', $p['expiration_date_to']);
        ok(!isset($p['payer']));
        eq('LOJAEXEMPLO 2', $p['statement_descriptor']);
        ok(!isset($p['binary_mode']), 'modo binário desligado');
        $long = Payloads::preference(1, 1, 'https://x.example', $c, $now, 't', [], str_repeat('abc ', 100));
        eq(256, mb_strlen($long['items'][0]['description']));
    },
    'Descrição na fatura do cartão: sempre preenchida (configurada, nome da empresa ou padrão), 13 caracteres' => function () {
        eq('MINHA LOJA', Config::fromParams(gw(['descriptor' => 'minha loja', 'companyname' => 'Outra'])) ->descriptor);
        eq('CONSTRUCAO SA', Config::fromParams(gw(['companyname' => 'Construção S.A. Serviços'])) ->descriptor);
        eq('MERCADOPAGO', Config::fromParams(gw(['companyname' => '***'])) ->descriptor);
        eq('MERCADOPAGO', Config::fromParams(gw())->descriptor);
    },
    'Chave do cache: 8 caracteres; muda com modo, payload ou configuração; ignora as datas de validade' => function () use ($now) {
        $c = Config::fromParams(gw());
        $base = Payloads::fingerprint($c, Payloads::preference(501, 10, 'https://x.example', $c, $now, 't', ['name' => 'Ana']));
        ok(preg_match('/^t:[0-9a-f]{6}$/', $base) === 1, $base);
        eq($base, Payloads::fingerprint($c, Payloads::preference(501, 10, 'https://x.example', $c, $now + 999, 't', ['name' => 'Ana'])), 'datas não entram');
        $live = Config::fromParams(gw(['mode' => 'live']));
        $variants = [
            Payloads::fingerprint($live, Payloads::preference(501, 10, 'https://x.example', $live, $now, 't', ['name' => 'Ana'])),
            Payloads::fingerprint($c, Payloads::preference(501, 10, 'https://x.example', $c, $now, 't', ['name' => 'Bia'])),
            Payloads::fingerprint($c, Payloads::preference(501, 10, 'https://x.example', $c, $now, 't', ['name' => 'Ana'], 'linha')),
            Payloads::fingerprint(Config::fromParams(gw(['installments' => '3'])), Payloads::preference(501, 10, 'https://x.example', Config::fromParams(gw(['installments' => '3'])), $now, 't', ['name' => 'Ana'])),
        ];
        eq(4, count(array_unique($variants)));
        ok(!in_array($base, $variants, true));
        ok(strpos($variants[0], 'l:') === 0 && strlen($variants[0]) === 8);
    },
    'Link: payer completo em Produção (nome, e-mail, telefone, CPF, endereço, cadastro) e item com linhas da fatura' => function () use ($params, $now, $created, $gateway) {
        $http = new FakeHttp($created);
        $gateway($http)->link($params(['mode' => 'live']), $now);
        $b = $http->requests[0]['body'];
        eq(['name' => 'Ana', 'surname' => 'Souza', 'email' => 'cliente@example.com', 'phone' => ['area_code' => '47', 'number' => '999998888'],
            'identification' => ['type' => 'CPF', 'number' => '52998224725'], 'address' => ['zip_code' => '89000000', 'street_name' => 'Rua das Flores', 'street_number' => '100'],
            'date_created' => '2021-03-25T00:00:00.000-03:00'], $b['payer']);
        eq('Plano VPS 2 GB (18/11/2026 - 17/12/2026) · Desconto 5OFF', $b['items'][0]['description']);
        eq('LOJA EXEMPLO', $b['statement_descriptor']);
    },
    'Link em Teste: sem o e-mail do cliente; com o do comprador de teste se configurado; resto do payer igual' => function () use ($params, $now, $created, $gateway) {
        $http = new FakeHttp($created, $created);
        $gateway($http)->link($params(), $now);
        ok(!isset($http->requests[0]['body']['payer']['email']));
        eq('Ana', $http->requests[0]['body']['payer']['name']);
        $gateway($http)->link($params(['test_payer_email' => 'test_user_123@testuser.com']), $now);
        eq('test_user_123@testuser.com', $http->requests[1]['body']['payer']['email']);
    },
    'Link guardado de versão anterior (chave "t:" de outro payload) não é reaproveitado' => function () use ($params, $now, $created, $gateway) {
        $store = new MemoryStore();
        $store->rows[501] = ['invoice_id' => 501, 'mode' => 't:000000', 'amount' => '59.90', 'pref_id' => 'antiga', 'init_point' => 'https://www.mercadopago.com.br/antiga', 'expires_at' => $now + 72 * 3600];
        $http = new FakeHttp($created);
        $html = $gateway($http, $store)->link($params(), $now);
        eq(1, count($http->requests), 'criou preferência nova');
        ok(strpos($html, 'pref_id=pref-1') !== false);
        eq('pref-1', $store->rows[501]['pref_id']);
    },
    'Configuração: padrões e limites (parcelas 12, boleto excluído, 72 h); boleto liberado se desmarcado' => function () {
        $c = Config::fromParams(['mode' => 'live', 'token_live' => ' tok ']);
        eq([false, 'tok', 12, true, 72, '', ''], [$c->test, $c->token, $c->installments, $c->excludeTicket, $c->hours, $c->testPayerEmail, $c->docField]);
        $c = Config::fromParams(gw(['installments' => '99', 'link_hours' => '0', 'exclude_ticket' => '', 'doc_field' => 'cf:7']));
        eq([true, 'TEST-test-token', 12, false, 72, 'cf:7'], [$c->test, $c->token, $c->installments, $c->excludeTicket, $c->hours, $c->docField]);
        eq('', Config::fromParams(gw(['doc_field' => 'x; drop']))->docField);
        $p = Payloads::preference(1, 10, 'https://x.example', $c, 0, 't');
        eq([], $p['payment_methods']['excluded_payment_types']);
    },
    'Link: cria a preferência com Bearer e X-Idempotency-Key e mostra o botão' => function () use ($params, $now, $created, $gateway) {
        $http = new FakeHttp($created);
        $html = $gateway($http)->link($params(), $now);
        ok(strpos($html, 'href="https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=pref-1"') !== false, $html);
        ok(strpos($html, 'Pagar com Mercado Pago') !== false);
        $r = $http->requests[0];
        eq(['POST', 'https://api.mercadopago.com/checkout/preferences'], [$r['method'], $r['url']]);
        eq('Bearer TEST-test-token', $r['headers']['Authorization']);
        ok(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $r['headers']['X-Idempotency-Key']) === 1);
        eq('Fatura #501', $r['body']['items'][0]['title']);
    },
    'Cache: reaproveita com mesmo valor e modo; recria se o valor, o modo mudar ou o link estiver perto de expirar' => function () use ($params, $now, $created, $gateway) {
        $store = new MemoryStore();
        $http = new FakeHttp($created);
        $g = $gateway($http, $store);
        $g->link($params(), $now);
        $g->link($params(), $now + 3600);
        eq(1, count($http->requests), 'segunda visualização sem chamar a API');

        $http->responses = [$created, $created, $created];
        $g->link($params(['amount' => '49.90']), $now + 3600);
        eq(2, count($http->requests), 'valor mudou');
        $g->link($params(['amount' => '49.90', 'mode' => 'live']), $now + 3600);
        eq(3, count($http->requests), 'modo mudou');
        eq('Bearer APP_USR-live-token', $http->requests[2]['headers']['Authorization']);
        $g->link($params(['amount' => '49.90', 'mode' => 'live']), $now + 3600 + 72 * 3600 - 1000);
        eq(4, count($http->requests), 'faltam menos de 30 min');
        ok(strpos($store->rows[501]['mode'], 'l:') === 0);
        eq('49.90', $store->rows[501]['amount']);
    },
    'Moeda diferente de BRL: aviso, sem preferência' => function () use ($params, $now, $gateway) {
        $http = new FakeHttp();
        $html = $gateway($http)->link($params(['currency' => 'USD', 'clientdetails' => ['language' => 'english']]), $now);
        ok(strpos($html, 'only accepts payments in Brazilian reais') !== false, $html);
        eq([], $http->requests);
    },
    'Erro da API: mensagem amigável e Log de Gateways sem token nem e-mail' => function () use ($params, $now, $gateway) {
        $b = new FakeBridge();
        $html = $gateway(new FakeHttp(['status' => 401, 'body' => ['message' => 'invalid access token TEST-test-token for cliente@example.com 529.982.247-25']]), null, $b)->link($params(), $now);
        ok(strpos($html, 'Não foi possível gerar o link') !== false, $html);
        eq(['Erro ao criar a preferência'], $b->results());
        eq('HTTP 401: invalid access token *** for *** ***', $b->logs[0][1]['erro']);
        assertCleanLogs($b);
    },
    'Reembolso: POST /refunds com amount e X-Idempotency-Key; status no formato do WHMCS' => function () use ($params, $gateway) {
        $http = new FakeHttp(['status' => 201, 'body' => ['id' => 999, 'status' => 'approved', 'amount' => 20.5]]);
        $r = $gateway($http)->refund($params(['transid' => '1234567890', 'amount' => '20.50']));
        eq('success', $r['status']);
        eq('999', $r['transid']);
        eq(['POST', 'https://api.mercadopago.com/v1/payments/1234567890/refunds', ['amount' => 20.5]], [$http->requests[0]['method'], $http->requests[0]['url'], $http->requests[0]['body']]);
        ok(isset($http->requests[0]['headers']['X-Idempotency-Key']));

        $r = $gateway(new FakeHttp(['status' => 400, 'body' => ['message' => 'amount too high']]))->refund($params(['transid' => '1234567890', 'amount' => '20.50']));
        eq('declined', $r['status']);
        $r = $gateway(new FakeHttp(['status' => 503, 'body' => null]))->refund($params(['transid' => '1234567890', 'amount' => '20.50']));
        eq('error', $r['status']);
        $r = $gateway(new FakeHttp())->refund($params(['transid' => 'abc', 'amount' => '1']));
        eq('declined', $r['status'], 'transid inválido não chama a API');
    },
];
