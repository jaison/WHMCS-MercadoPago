<?php

use MercadoPagoPro\Config;
use MercadoPagoPro\Processor;

return [
    'Status: approved paga; pendentes, recusados e estornos não' => function () {
        eq('paid', Processor::classify('approved'));
        foreach (['pending', 'in_process', 'authorized'] as $s) {
            eq('pending', Processor::classify($s), $s);
        }
        foreach (['rejected', 'cancelled'] as $s) {
            eq('failed', Processor::classify($s), $s);
        }
        foreach (['refunded', 'charged_back', 'in_mediation'] as $s) {
            eq('reversed', Processor::classify($s), $s);
        }
        eq('unknown', Processor::classify('x'));
    },
    'Aprovado: addInvoicePayment com valor pago e taxa (soma de fee_details); log sem pagador' => function () {
        $b = new FakeBridge([501]);
        eq(Processor::PAID, (new Processor($b, Config::fromParams(gw())))->settle(payment()));
        eq([[501, '1234567890', 59.9, 4.0]], $b->payments);
        eq(['Successful'], $b->results());
        eq(['id' => '1234567890', 'status' => 'approved', 'status_detail' => 'accredited', 'valor' => '59.9', 'moeda' => 'BRL', 'taxa' => '4', 'metodo' => 'credit_card/visa', 'live_mode' => 'não', 'fatura' => '501'], $b->logs[0][1]);
        assertCleanLogs($b);
    },
    'Idempotência: a mesma transação duas vezes paga uma só (checkCbTransID encerra)' => function () {
        $b = new FakeBridge([501]);
        $p = new Processor($b, Config::fromParams(gw()));
        $p->settle(payment());
        try {
            $p->settle(payment());
            ok(false, 'devia encerrar');
        } catch (WhmcsExit $e) {
            eq('transação já registrada', $e->getMessage());
        }
        eq(1, count($b->payments));
    },
    'Fatura inexistente: checkCbInvoiceID encerra e nada é pago' => function () {
        $b = new FakeBridge([]);
        try {
            (new Processor($b, Config::fromParams(gw())))->settle(payment());
            ok(false, 'devia encerrar');
        } catch (WhmcsExit $e) {
            eq([], $b->payments);
        }
    },
    'Ignorados: pendente, recusado, outra moeda, sem fatura' => function () {
        $b = new FakeBridge([501]);
        $p = new Processor($b, Config::fromParams(gw()));
        eq(Processor::IGNORED, $p->settle(payment(['status' => 'pending'])));
        eq(Processor::IGNORED, $p->settle(payment(['status' => 'rejected'])));
        eq(Processor::IGNORED, $p->settle(payment(['currency_id' => 'USD'])));
        eq(Processor::IGNORED, $p->settle(payment(['external_reference' => 'abc'])));
        eq(Processor::IGNORED, $p->settle(payment(['external_reference' => null])));
        eq([], $b->payments);
        eq(['Ignorado: moeda diferente de BRL', 'Ignorado: sem fatura ou valor', 'Ignorado: sem fatura ou valor'], $b->results());
        assertCleanLogs($b);
    },
    'live_mode não bloqueia (usuários de teste vêm com true); só aparece no log' => function () {
        $b = new FakeBridge([501]);
        eq(Processor::PAID, (new Processor($b, Config::fromParams(gw())))->settle(payment(['live_mode' => true])));
        eq('sim', $b->logs[0][1]['live_mode']);
        $b = new FakeBridge([501]);
        eq(Processor::PAID, (new Processor($b, Config::fromParams(gw(['mode' => 'live']))))->settle(payment(['id' => 222, 'live_mode' => false])));
        eq(1, count($b->payments));
    },
    'Estorno e chargeback: só Log de Gateways' => function () {
        $b = new FakeBridge([501]);
        $p = new Processor($b, Config::fromParams(gw()));
        eq(Processor::LOGGED, $p->settle(payment(['status' => 'refunded'])));
        eq(Processor::LOGGED, $p->settle(payment(['status' => 'charged_back'])));
        eq([], $b->payments);
        eq(2, count($b->logs));
    },
];
