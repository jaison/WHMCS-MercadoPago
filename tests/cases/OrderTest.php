<?php

use MercadoPagoPro\Callback;
use MercadoPagoPro\Client;
use MercadoPagoPro\Config;
use MercadoPagoPro\Notification;
use MercadoPagoPro\Processor;
use MercadoPagoPro\Signature;

const ORD = 'ORDTST01KS5AJ6HTK2HRQ3XJ3C2JCKP9';

$make = function (array $gw, FakeHttp $http, FakeBridge $b, ?MemoryStore $store = null): Callback {
    $c = Config::fromParams($gw);
    return new Callback($c, new Client($c->token, $http), $b, 'https://loja.example.com/', $store ?? new MemoryStore());
};
$ok = ['status' => 200, 'body' => order()];
$fee = ['status' => 200, 'body' => payment(['id' => 190343815810, 'fee_details' => [['amount' => 2.5], ['amount' => 0.49]]])];

return [
    'Parser: webhook de order com id alfanumérico (URL e corpo); id inválido não é order' => function () {
        $n = Notification::parse(['type' => 'order', 'data_id' => ORD], '');
        ok($n->isOrder() && !$n->isPayment());
        $n = Notification::parse([], '{"type":"order","action":"order.processed","data":{"id":"' . ORD . '"}}');
        ok($n->isOrder());
        eq('order.processed', $n->action);
        ok(!Notification::parse(['type' => 'order', 'data_id' => '12345'], '')->isOrder());
        ok(!Notification::parse(['type' => 'order', 'data_id' => 'ORD../etc'], '')->isOrder());
        ok(!Notification::parse(['type' => 'payment', 'data_id' => ORD], '')->isPayment());
    },
    'Assinatura com id alfanumérico: manifesto em minúsculas' => function () {
        eq('id:' . strtolower(ORD) . ';request-id:r1;ts:1700000000;', Signature::manifest(ORD, 'r1', '1700000000'));
        $h = 'ts=1700000000,v1=' . hash_hmac('sha256', 'id:' . strtolower(ORD) . ';request-id:r1;ts:1700000000;', 'segredo');
        ok(Signature::verify('segredo', $h, 'r1', ORD));
        ok(!Signature::verify('segredo', 'ts=1700000000,v1=' . hash_hmac('sha256', 'id:' . ORD . ';request-id:r1;ts:1700000000;', 'segredo'), 'r1', ORD), 'id maiúsculo no manifesto não vale');
    },
    'Status da order: processed/accredited paga; o resto não' => function () {
        eq('paid', Processor::classifyOrder(order()));
        eq('reversed', Processor::classifyOrder(order(['status_detail' => 'partially_refunded'])));
        foreach (['refunded', 'charged_back'] as $s) {
            eq('reversed', Processor::classifyOrder(order(['status' => $s])), $s);
        }
        foreach (['failed', 'canceled'] as $s) {
            eq('failed', Processor::classifyOrder(order(['status' => $s])), $s);
        }
        foreach (['created', 'processing', 'action_required'] as $s) {
            eq('pending', Processor::classifyOrder(order(['status' => $s])), $s);
        }
    },
    'Valor = soma dos pagamentos aprovados; sem eles, total_paid_amount' => function () {
        $o = order();
        $o['transactions']['payments'][] = ['id' => 'PAY2', 'paid_amount' => '10.00', 'status' => 'processed', 'status_detail' => 'accredited'];
        $o['transactions']['payments'][] = ['id' => 'PAY3', 'paid_amount' => '99.00', 'status' => 'failed', 'status_detail' => 'rejected'];
        eq(69.9, Processor::orderPaid($o));
        eq(30.0, Processor::orderPaid(order(['transactions' => [], 'total_paid_amount' => '30.00'])));
    },
    'Webhook de order paga: GET /v1/orders, baixa com transid = order, valor e taxa (via pagamento de referência); log sem pagador' => function () use ($make, $ok, $fee) {
        $http = new FakeHttp($ok, $fee);
        $b = new FakeBridge([501]);
        eq(200, $make(gw(), $http, $b)->handle(['type' => 'order', 'data_id' => ORD], [], '')['status']);
        eq(['GET', 'https://api.mercadopago.com/v1/orders/' . ORD], [$http->requests[0]['method'], $http->requests[0]['url']]);
        eq('https://api.mercadopago.com/v1/payments/190343815810', $http->requests[1]['url']);
        eq([[501, ORD, 59.9, 2.99]], $b->payments);
        eq('Successful', $b->logs[0][2]);
        eq(['order' => ORD, 'status' => 'processed', 'status_detail' => 'accredited', 'valor' => '59.9', 'moeda' => 'BRL', 'taxa' => '2.99', 'metodo' => 'credit_card/master',
            'pagamento' => 'PAY01KS5AJ6HTK2HRQ3XJ3C2JCKPA', 'pagamento_ref' => '190343815810', 'fatura' => '501'], $b->logs[0][1]);
        assertCleanLogs($b);
    },
    'Taxa indisponível (pagamento de referência falha): baixa com taxa 0' => function () use ($make, $ok) {
        $b = new FakeBridge([501]);
        $make(gw(), new FakeHttp($ok, ['status' => 404, 'body' => null]), $b)->handle(['type' => 'order', 'data_id' => ORD], [], '');
        eq([[501, ORD, 59.9, 0.0]], $b->payments);
    },
    'Idempotência: a mesma order duas vezes registra uma transação só' => function () use ($make, $ok, $fee) {
        $b = new FakeBridge([501]);
        $make(gw(), new FakeHttp($ok, $fee), $b)->handle(['type' => 'order', 'data_id' => ORD], [], '');
        try {
            $make(gw(), new FakeHttp($ok, $fee), $b)->handle(['type' => 'order', 'data_id' => ORD], [], '');
            ok(false, 'devia encerrar');
        } catch (WhmcsExit $e) {
            eq('transação já registrada', $e->getMessage());
        }
        eq(1, count($b->payments));
    },
    'Order não paga, outra moeda ou sem fatura: sem baixa; estorno só no log' => function () use ($make) {
        foreach ([order(['status' => 'action_required', 'status_detail' => 'waiting_payment']), order(['currency' => 'USD']), order(['external_reference' => 'x']), order(['status' => 'refunded', 'status_detail' => 'refunded'])] as $o) {
            $b = new FakeBridge([501]);
            eq(200, $make(gw(), new FakeHttp(['status' => 200, 'body' => $o]), $b)->handle(['type' => 'order', 'data_id' => ORD], [], '')['status']);
            eq([], $b->payments);
        }
    },
    'Com secret: order com assinatura válida passa; errada dá 401' => function () use ($make, $ok, $fee) {
        $gw = gw(['secret_test' => 'segredo']);
        $b = new FakeBridge([501]);
        $good = ['HTTP_X_SIGNATURE' => Signature::sign('segredo', ORD, 'r9', '1700000000'), 'HTTP_X_REQUEST_ID' => 'r9'];
        eq(200, $make($gw, new FakeHttp($ok, $fee), $b)->handle(['type' => 'order', 'data_id' => ORD], $good, '')['status']);
        eq(1, count($b->payments));
        eq(401, $make($gw, new FakeHttp(), new FakeBridge())->handle(['type' => 'order', 'data_id' => ORD], ['HTTP_X_SIGNATURE' => Signature::sign('outro', ORD, 'r9', '1700000000'), 'HTTP_X_REQUEST_ID' => 'r9'], '')['status']);
    },
    'API fora ao consultar a order: 503' => function () use ($make) {
        eq(503, $make(gw(), new FakeHttp(['status' => 502, 'body' => null]), new FakeBridge())->handle(['type' => 'order', 'data_id' => ORD], [], '')['status']);
    },
    'Retorno com order_id: consulta, baixa e paymentsuccess' => function () use ($make, $ok, $fee) {
        $b = new FakeBridge([501]);
        $r = $make(gw(), new FakeHttp($ok, $fee), $b)->handle(['return' => '1', 'inv' => '501', 'order_id' => ORD], [], '');
        eq('https://loja.example.com/viewinvoice.php?id=501&paymentsuccess=true', $r['location']);
        eq(1, count($b->payments));
    },
    'Retorno sem order_id: usa a order guardada da fatura; recusado vai com paymentfailed' => function () use ($make, $ok, $fee) {
        $store = new MemoryStore();
        $store->save(501, 't:abcdef', '59.90', ORD, 'https://x', 2_000_000_000, 1);
        $b = new FakeBridge([501]);
        $http = new FakeHttp($ok, $fee);
        $r = $make(gw(), $http, $b, $store)->handle(['return' => '1', 'inv' => '501', 'payment_id' => '190343815810', 'status' => 'approved'], [], '');
        eq('https://api.mercadopago.com/v1/orders/' . ORD, $http->requests[0]['url'], 'consultou a order, não o pagamento');
        eq([[501, ORD, 59.9, 2.99]], $b->payments);
        eq('https://loja.example.com/viewinvoice.php?id=501&paymentsuccess=true', $r['location']);
        $r = $make(gw(), new FakeHttp(['status' => 200, 'body' => order(['status' => 'action_required', 'status_detail' => 'waiting_payment'])]), new FakeBridge([501]), $store)->handle(['return' => '1', 'inv' => '501', 'status' => 'rejected'], [], '');
        eq('https://loja.example.com/viewinvoice.php?id=501&paymentfailed=true', $r['location']);
    },
    'Compatibilidade: tópico payment de fatura com order é ignorado (baixa pela order); de link antigo dá baixa' => function () use ($make) {
        $store = new MemoryStore();
        $store->save(501, 't:abcdef', '59.90', ORD, 'https://x', 2_000_000_000, 1);
        $b = new FakeBridge([501]);
        eq(200, $make(gw(), new FakeHttp(['status' => 200, 'body' => payment()]), $b, $store)->handle(['type' => 'payment', 'data_id' => '1234567890'], [], '')['status']);
        eq([], $b->payments);
        eq(['Ignorado: pagamento de order (baixa pela order)'], $b->results());
        $store->save(501, 't:abcdef', '59.90', '3854449797-0ccc3df9', 'https://x', 2_000_000_000, 1);
        $b = new FakeBridge([501]);
        $make(gw(), new FakeHttp(['status' => 200, 'body' => payment()]), $b, $store)->handle(['topic' => 'payment', 'id' => '1234567890'], [], '');
        eq([[501, '1234567890', 59.9, 4.0]], $b->payments);
    },
];
