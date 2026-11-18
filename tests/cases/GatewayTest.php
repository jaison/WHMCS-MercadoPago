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
        'clientdetails' => ['email' => 'cliente@example.com', 'language' => ''],
    ];
};
$now = 1_800_000_000;
$created = ['status' => 201, 'body' => ['id' => 'pref-1', 'init_point' => 'https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=pref-1']];

return [
    'Payload da preferência: item, referência, URLs, parcelas, boleto, validade e descrição' => function () use ($now) {
        $c = Config::fromParams(gw(['installments' => '6', 'descriptor' => 'Loja*Exemplo!! 2026 com nome longo', 'link_hours' => '48']));
        $p = Payloads::preference(501, 59.9, 'cliente@example.com', 'https://loja.example.com/', $c, $now, 'Fatura #501');
        eq([['id' => '501', 'title' => 'Fatura #501', 'quantity' => 1, 'unit_price' => 59.9, 'currency_id' => 'BRL']], $p['items']);
        eq('501', $p['external_reference']);
        $back = 'https://loja.example.com/modules/gateways/callback/mercadopagopro.php?return=1&inv=501';
        eq(['success' => $back, 'pending' => $back, 'failure' => $back], $p['back_urls']);
        eq('approved', $p['auto_return']);
        eq('https://loja.example.com/modules/gateways/callback/mercadopagopro.php?source_news=webhooks', $p['notification_url']);
        eq(['installments' => 6, 'excluded_payment_types' => [['id' => 'ticket']]], $p['payment_methods']);
        eq(true, $p['expires']);
        eq('2027-01-15T05:00:00.000-03:00', $p['expiration_date_from']);
        eq('2027-01-17T05:00:00.000-03:00', $p['expiration_date_to']);
        eq(['email' => 'cliente@example.com'], $p['payer']);
        eq('LojaExemplo 2026 com n', $p['statement_descriptor']);
    },
    'Configuração: padrões e limites (parcelas 12, boleto excluído, 72 h); boleto liberado se desmarcado' => function () {
        $c = Config::fromParams(['mode' => 'live', 'token_live' => ' tok ']);
        eq([false, 'tok', 12, true, 72, ''], [$c->test, $c->token, $c->installments, $c->excludeTicket, $c->hours, $c->descriptor]);
        $c = Config::fromParams(gw(['installments' => '99', 'link_hours' => '0', 'exclude_ticket' => '']));
        eq([true, 'TEST-test-token', 12, false, 72], [$c->test, $c->token, $c->installments, $c->excludeTicket, $c->hours]);
        $p = Payloads::preference(1, 10, '', 'https://x.example', $c, 0, 't');
        eq([], $p['payment_methods']['excluded_payment_types']);
        ok(!isset($p['payer']) && !isset($p['statement_descriptor']));
    },
    'Link: cria a preferência com Bearer e X-Idempotency-Key e mostra o botão' => function () use ($params, $now, $created) {
        $http = new FakeHttp($created);
        $html = (new Gateway($http, new MemoryStore(), new FakeBridge()))->link($params(), $now);
        ok(strpos($html, 'href="https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=pref-1"') !== false, $html);
        ok(strpos($html, 'Pagar com Mercado Pago') !== false);
        $r = $http->requests[0];
        eq(['POST', 'https://api.mercadopago.com/checkout/preferences'], [$r['method'], $r['url']]);
        eq('Bearer TEST-test-token', $r['headers']['Authorization']);
        ok(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $r['headers']['X-Idempotency-Key']) === 1);
        eq('Fatura #501', $r['body']['items'][0]['title']);
    },
    'Cache: reaproveita com mesmo valor e modo; recria se o valor, o modo mudar ou o link estiver perto de expirar' => function () use ($params, $now, $created) {
        $store = new MemoryStore();
        $http = new FakeHttp($created);
        $g = new Gateway($http, $store, new FakeBridge());
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
        eq('live', $store->rows[501]['mode']);
        eq('49.90', $store->rows[501]['amount']);
    },
    'Moeda diferente de BRL: aviso, sem preferência' => function () use ($params, $now) {
        $http = new FakeHttp();
        $html = (new Gateway($http, new MemoryStore(), new FakeBridge()))->link($params(['currency' => 'USD', 'clientdetails' => ['language' => 'english']]), $now);
        ok(strpos($html, 'only accepts payments in Brazilian reais') !== false, $html);
        eq([], $http->requests);
    },
    'Erro da API: mensagem amigável e Log de Gateways sem token nem e-mail' => function () use ($params, $now) {
        $b = new FakeBridge();
        $html = (new Gateway(new FakeHttp(['status' => 401, 'body' => ['message' => 'invalid access token TEST-test-token']]), new MemoryStore(), $b))->link($params(), $now);
        ok(strpos($html, 'Não foi possível gerar o link') !== false, $html);
        eq(['Erro ao criar a preferência'], $b->results());
        eq('HTTP 401: invalid access token ***', $b->logs[0][1]['erro']);
        assertCleanLogs($b);
    },
    'Reembolso: POST /refunds com amount e X-Idempotency-Key; status no formato do WHMCS' => function () use ($params) {
        $http = new FakeHttp(['status' => 201, 'body' => ['id' => 999, 'status' => 'approved', 'amount' => 20.5]]);
        $r = (new Gateway($http, new MemoryStore(), new FakeBridge()))->refund($params(['transid' => '1234567890', 'amount' => '20.50']));
        eq('success', $r['status']);
        eq('999', $r['transid']);
        eq(['POST', 'https://api.mercadopago.com/v1/payments/1234567890/refunds', ['amount' => 20.5]], [$http->requests[0]['method'], $http->requests[0]['url'], $http->requests[0]['body']]);
        ok(isset($http->requests[0]['headers']['X-Idempotency-Key']));

        $r = (new Gateway(new FakeHttp(['status' => 400, 'body' => ['message' => 'amount too high']]), new MemoryStore(), new FakeBridge()))->refund($params(['transid' => '1234567890', 'amount' => '20.50']));
        eq('declined', $r['status']);
        $r = (new Gateway(new FakeHttp(['status' => 503, 'body' => null]), new MemoryStore(), new FakeBridge()))->refund($params(['transid' => '1234567890', 'amount' => '20.50']));
        eq('error', $r['status']);
        $r = (new Gateway(new FakeHttp(), new MemoryStore(), new FakeBridge()))->refund($params(['transid' => 'abc', 'amount' => '1']));
        eq('declined', $r['status'], 'transid inválido não chama a API');
    },
];
