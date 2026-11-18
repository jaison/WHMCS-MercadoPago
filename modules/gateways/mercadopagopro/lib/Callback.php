<?php

namespace MercadoPagoPro;

/**
 * Callback único (modules/gateways/callback/mercadopagopro.php):
 * - ?return=1: o cliente voltando do Mercado Pago. Consulta o pagamento, dá a baixa se aprovado e
 *   redireciona para a fatura (com paymentsuccess/paymentfailed).
 * - senão: notificação (webhook ou IPN). 200 quando processada ou ignorada de propósito,
 *   401 com assinatura inválida (secret configurado), 503 em falha temporária da API.
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
    /** @var string */
    private $systemUrl;
    /** @var string|null */
    public $pendingRedirect;

    public function __construct(Config $config, ?Client $client, Bridge $bridge, string $systemUrl)
    {
        $this->config = $config;
        $this->client = $client;
        $this->bridge = $bridge;
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
        if (!$n->isPayment()) {
            return self::out(200, 'ignored');
        }
        if ($this->client === null) {
            $this->bridge->log($this->config->name, ['id' => $n->id], 'Access Token não configurado');
            return self::out(200, 'not configured');
        }
        try {
            $payment = $this->client->getPayment($n->id);
        } catch (ApiException $e) {
            if ($e->temporary()) {
                return self::out(503, 'retry');
            }
            $this->bridge->log($this->config->name, ['id' => $n->id, 'erro' => $e->getMessage()], 'Ignorado: pagamento não consultado');
            return self::out(200, 'ignored');
        }
        $this->processor->settle($payment);
        return self::out(200, 'ok');
    }

    private function customerReturn(array $get): array
    {
        $inv = (string) ($get['inv'] ?? '');
        $invoiceId = preg_match('/^\d{1,10}$/', $inv) ? (int) $inv : 0;
        $pid = (string) ($get['payment_id'] ?? ($get['collection_id'] ?? ''));
        $flag = '';
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
        } elseif (in_array((string) ($get['status'] ?? ($get['collection_status'] ?? '')), ['rejected', 'failure'], true)) {
            $flag = 'paymentfailed';
        }
        $to = $invoiceId > 0
            ? $this->systemUrl . '/viewinvoice.php?id=' . $invoiceId . ($flag !== '' ? '&' . $flag . '=true' : '')
            : $this->systemUrl . '/clientarea.php?action=invoices';
        $this->pendingRedirect = $to;
        if ($payment !== null && $flag === 'paymentsuccess') {
            $this->processor->settle($payment);
        }
        return ['status' => 302, 'body' => '', 'location' => $to];
    }

    private static function out(int $status, string $body): array
    {
        return ['status' => $status, 'body' => $body, 'location' => null];
    }
}
