<?php

use MercadoPagoPro\Notification;

return [
    'Webhook pela URL: ?type=payment&data.id=X (o PHP entrega data_id)' => function () {
        $n = Notification::parse(['source_news' => 'webhooks', 'type' => 'payment', 'data_id' => '123'], '');
        eq(['payment', '123', '123', true], [$n->topic, $n->id, $n->idFromQuery, $n->webhook]);
        ok($n->isPayment());
    },
    'Webhook só no corpo JSON {type, action, data.id}' => function () {
        $n = Notification::parse([], '{"action":"payment.updated","type":"payment","data":{"id":"456"}}');
        eq(['payment', '456', '', 'payment.updated', true], [$n->topic, $n->id, $n->idFromQuery, $n->action, $n->webhook]);
        ok($n->isPayment());
    },
    'IPN: ?topic=payment&id=X (sem assinatura)' => function () {
        $n = Notification::parse(['topic' => 'payment', 'id' => '789'], '');
        eq(['payment', '789', false], [$n->topic, $n->id, $n->webhook]);
        ok($n->isPayment());
    },
    'merchant_order, tópicos desconhecidos, id não numérico e vazio: não são pagamento' => function () {
        ok(!Notification::parse(['topic' => 'merchant_order', 'id' => '1'], '')->isPayment());
        ok(!Notification::parse(['type' => 'merchant_order', 'data_id' => '1'], '')->isPayment());
        ok(!Notification::parse(['type' => 'subscription_preapproval', 'data_id' => '1'], '')->isPayment());
        ok(!Notification::parse(['type' => 'payment', 'data_id' => "1' OR 1=1"], '')->isPayment());
        ok(!Notification::parse([], '')->isPayment());
        ok(!Notification::parse([], 'não é json')->isPayment());
        ok(!Notification::parse(['type' => ['payment']], '')->isPayment(), 'array no lugar de texto');
    },
];
