<?php

namespace MercadoPagoPro;

/**
 * Callback único (modules/gateways/callback/mercadopagopro.php):
 * - ?return=1: o cliente voltando do Mercado Pago. Consulta a order (ou o pagamento, em links
 *   antigos), dá a baixa se aprovada e redireciona para a fatura (com paymentsuccess/paymentfailed).
 * - senão: notificação (webhook ou IPN), tópicos order e payment. 200 quando processada ou
 *   ignorada de propósito, 401 com assinatura inválida (secret configurado), 503 em falha
 *   temporária da API.
 *
 * $pendingRedirect: destino já decidido no retorno do cliente, para o arquivo de entrada
 * redirecionar mesmo se uma função do WHMCS encerrar o script (ex.: transação já registrada).
 */
final class Callback
{
    /** @var Config */
    private $config;
    /** @var Client|null */
    private $client;
    /** @var Processor */
    private $processor;
    /** @var Bridge */
    private $bridge;
    /** @var Store|null */
    private $store;
    /** @var string */
    private $systemUrl;
    /** @var string|null */
    public $pendingRedirect;

    public function __construct(Config $config, ?Client $client, Bridge $bridge, string $systemUrl, ?Store $store = null)
    {
        $this->config = $config;
        $this->client = $client;
        $this->bridge = $bridge;
        $this->store = $store;
        $this->processor = new Processor($bridge, $config);
        $this->systemUrl = rtrim($systemUrl, '/');
    }

    /**
     * @param array<string, mixed>  $get
     * @param array<string, mixed>  $server  $_SERVER (headers HTTP_X_SIGNATURE, HTTP_X_REQUEST_ID)
     * @return array{status: int, body: string, location: string|null}
     */
    public function handle(array $get, array $server, string $body): array
    {
        if ((string) ($get['return'] ?? '') === '1') {
            return $this->customerReturn($get);
        }
        return $this->notification($get, $server, $body);
    }

    private function notification(array $get, array $server, string $body): array
    {
        $n = Notification::parse($get, $body);
        if ($n->webhook && $this->config->secret !== '') {
            $ok = Signature::verify($this->config->secret, (string) ($server['HTTP_X_SIGNATURE'] ?? ''), (string) ($server['HTTP_X_REQUEST_ID'] ?? ''), $n->idFromQuery !== '' ? $n->idFromQuery : $n->id);
            if (!$ok) {
                $this->bridge->log($this->config->name, ['topico' => $n->topic, 'id' => $n->id], 'Assinatura inválida');
                return self::out(401, 'invalid signature');
            }
        }
        if (!$n->isOrder() && !$n->isPayment()) {
            return self::out(200, 'ignored');
        }
        if ($this->client === null) {
            $this->bridge->log($this->config->name, ['id' => $n->id], 'Access Token não configurado');
            return self::out(200, 'not configured');
        }
        try {
            if ($n->isOrder()) {
                $this->processor->settleOrder($this->client->getOrder($n->id), $this->feeLookup());
            } else {
                $payment = $this->client->getPayment($n->id);
                if (!$this->belongsToOrder($payment)) {
                    $this->processor->settle($payment);
                }
            }
        } catch (ApiException $e) {
            if ($e->temporary()) {
                return self::out(503, 'retry');
            }
            $this->bridge->log($this->config->name, ['id' => $n->id, 'erro' => $e->getMessage()], 'Ignorado: não consultado');
            return self::out(200, 'ignored');
        }
        return self::out(200, 'ok');
    }

    /**
     * Pagamento (tópico payment) de uma fatura cujo link é uma order: a baixa é feita pela order
     * (transid = id da order), para não registrar o mesmo dinheiro duas vezes.
     */
    private function belongsToOrder(array $payment): bool
    {
        $ref = (string) ($payment['external_reference'] ?? '');
        if ($this->store === null || !preg_match('/^\d{1,10}$/', $ref)) {
            return false;
        }
        $row = $this->store->find((int) $ref);
        if ($row && Module::isOrderId($row['pref_id'])) {
            $this->bridge->log($this->config->name, Processor::summary($payment), 'Ignorado: pagamento de order (baixa pela order)');
            return true;
        }
        return false;
    }

    /** Taxa da order: pagamento correspondente em /v1/payments (reference_id numérico), se existir. */
    private function feeLookup(): callable
    {
        $client = $this->client;
        return static function (array $order) use ($client): float {
            $fee = 0.0;
            foreach (Processor::orderPayments($order) as $pay) {
                $ref = (string) ($pay['reference_id'] ?? '');
                if ($client !== null && preg_match('/^\d{1,20}$/', $ref)) {
                    $fee += Processor::fee($client->getPayment($ref));
                }
            }
            return $fee;
        };
    }

    private function customerReturn(array $get): array
    {
        $inv = (string) ($get['inv'] ?? '');
        $invoiceId = preg_match('/^\d{1,10}$/', $inv) ? (int) $inv : 0;
        $hint = (string) ($get['status'] ?? ($get['collection_status'] ?? ''));
        $orderId = (string) ($get['order_id'] ?? '');
        if (!Module::isOrderId($orderId) && $invoiceId > 0 && $this->store !== null) {
            $row = $this->store->find($invoiceId);
            $orderId = $row && Module::isOrderId($row['pref_id']) ? $row['pref_id'] : '';
        }
        $flag = '';
        $settle = null;
        if ($this->client !== null && Module::isOrderId($orderId)) {
            try {
                $order = $this->client->getOrder($orderId);
                $ref = (string) ($order['external_reference'] ?? '');
                if (preg_match('/^\d{1,10}$/', $ref)) {
                    $invoiceId = (int) $ref;
                }
                $kind = Processor::classifyOrder($order);
                $flag = $kind === 'paid' ? 'paymentsuccess' : ($kind === 'failed' || $hint === 'rejected' ? 'paymentfailed' : '');
                if ($kind === 'paid') {
                    $settle = function () use ($order) {
                        $this->processor->settleOrder($order, $this->feeLookup());
                    };
                }
            } catch (ApiException $e) {
                $flag = $hint === 'rejected' ? 'paymentfailed' : '';
            }
        } else {
            $pid = (string) ($get['payment_id'] ?? ($get['collection_id'] ?? ''));
            $payment = null;
            if ($this->client !== null && preg_match('/^\d{1,20}$/', $pid)) {
                try {
                    $payment = $this->client->getPayment($pid);
                } catch (ApiException $e) {
                    $payment = null;
                }
            }
            if ($payment !== null) {
                $ref = (string) ($payment['external_reference'] ?? '');
                if (preg_match('/^\d{1,10}$/', $ref)) {
                    $invoiceId = (int) $ref;
                }
                $kind = Processor::classify((string) ($payment['status'] ?? ''));
                $flag = $kind === 'paid' ? 'paymentsuccess' : ($kind === 'failed' ? 'paymentfailed' : '');
                if ($kind === 'paid') {
                    $settle = function () use ($payment) {
                        $this->processor->settle($payment);
                    };
                }
            } elseif (in_array($hint, ['rejected', 'failure'], true)) {
                $flag = 'paymentfailed';
            }
        }
        $to = $invoiceId > 0
            ? $this->systemUrl . '/viewinvoice.php?id=' . $invoiceId . ($flag !== '' ? '&' . $flag . '=true' : '')
            : $this->systemUrl . '/clientarea.php?action=invoices';
        $this->pendingRedirect = $to;
        if ($settle !== null) {
            $settle();
        }
        return ['status' => 302, 'body' => '', 'location' => $to];
    }

    private static function out(int $status, string $body): array
    {
        return ['status' => $status, 'body' => $body, 'location' => null];
    }
}
