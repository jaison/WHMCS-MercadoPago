<?php

namespace MercadoPagoPro;

/**
 * Lógica de _link (botão de pagamento) e _refund, separada das funções do WHMCS para testar.
 */
final class Gateway
{
    /** @var Http */
    private $http;
    /** @var Store */
    private $store;
    /** @var Bridge */
    private $bridge;
    /** @var Data */
    private $data;

    public function __construct(Http $http, Store $store, Bridge $bridge, Data $data)
    {
        $this->http = $http;
        $this->store = $store;
        $this->bridge = $bridge;
        $this->data = $data;
    }

    /** HTML do botão (ou do aviso) na fatura. */
    public function link(array $params, int $now): string
    {
        $c = Config::fromParams($params);
        $en = self::english($params);
        $invoiceId = (int) ($params['invoiceid'] ?? 0);
        $amount = (float) ($params['amount'] ?? 0);
        if (strtoupper((string) ($params['currency'] ?? '')) !== 'BRL') {
            return self::notice($en ? 'Mercado Pago only accepts payments in Brazilian reais (BRL).' : 'O Mercado Pago só aceita pagamentos em reais (BRL).');
        }
        if ($invoiceId <= 0 || $amount <= 0) {
            return '';
        }
        $systemUrl = (string) ($params['systemurl'] ?? '');
        $title = ($en ? 'Invoice #' : 'Fatura #') . $invoiceId;
        try {
            $client = new Client($c->token, $this->http);
            [$payer, $info] = $this->buyer($params, $c, $invoiceId);
            $payload = Payloads::order($invoiceId, $amount, $systemUrl, $c, $title, $payer, $this->lines($invoiceId), $info);
            $key = Payloads::fingerprint($c, $payload);
            $url = (new PreferenceCache($this->store))->link($invoiceId, $amount, $c, $key, $now, static function () use ($client, $payload) {
                return $client->createOrder($payload, Client::uuid());
            });
        } catch (ApiException $e) {
            $this->bridge->log($c->name, ['fatura' => (string) $invoiceId, 'modo' => $c->mode(), 'erro' => $e->getMessage()], 'Erro ao criar a order');
            return self::notice($en ? 'We could not generate the Mercado Pago payment link right now. Please try again in a few minutes.' : 'Não foi possível gerar o link de pagamento do Mercado Pago agora. Tente de novo em alguns minutos.');
        }
        $label = $en ? 'Pay with Mercado Pago' : 'Pagar com Mercado Pago';
        return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary mercadopagopro-pay" rel="noopener">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
    }

    /**
     * _refund do WHMCS. transid ORD… = order (POST /v1/orders/{id}/refund: total sem corpo, parcial
     * com o pagamento da order); transid numérico = pagamento de link antigo (/v1/payments/{id}/refunds).
     * @return array{status: string, rawdata: array<mixed>, transid?: string}
     */
    public function refund(array $params): array
    {
        $c = Config::fromParams($params);
        $transId = (string) ($params['transid'] ?? '');
        $amount = round((float) ($params['amount'] ?? 0), 2);
        if ($amount <= 0) {
            return ['status' => 'error', 'rawdata' => ['erro' => 'valor inválido']];
        }
        try {
            $client = new Client($c->token, $this->http);
            if (Module::isOrderId($transId)) {
                return $this->refundOrder($client, $transId, $amount);
            }
            $r = $client->refund($transId, $amount, Client::uuid());
        } catch (ApiException $e) {
            return ['status' => $e->status >= 400 && $e->status < 500 ? 'declined' : 'error', 'rawdata' => ['transacao' => $transId, 'erro' => $e->getMessage()]];
        }
        $raw = ['pagamento' => $transId, 'reembolso' => (string) ($r['id'] ?? ''), 'status' => (string) ($r['status'] ?? ''), 'valor' => (string) ($r['amount'] ?? $amount)];
        $status = (string) ($r['status'] ?? '');
        // in_process: o Mercado Pago aceitou e conclui depois; o WHMCS não tem estado pendente.
        if ($status === 'approved' || $status === 'in_process') {
            return ['status' => 'success', 'transid' => (string) ($r['id'] ?? ''), 'rawdata' => $raw];
        }
        return ['status' => $status === '' ? 'error' : 'declined', 'rawdata' => $raw];
    }

    private function refundOrder(Client $client, string $orderId, float $amount): array
    {
        $order = $client->getOrder($orderId);
        $paid = Processor::orderPaid($order);
        $pays = Processor::orderPayments($order);
        if ($paid > 0 && $amount > $paid + 0.001) {
            return ['status' => 'declined', 'rawdata' => ['order' => $orderId, 'erro' => 'valor maior que o pago']];
        }
        $partial = $paid > 0 && $amount < $paid - 0.001;
        if ($partial && empty($pays[0]['id'])) {
            return ['status' => 'declined', 'rawdata' => ['order' => $orderId, 'erro' => 'order sem pagamento para reembolso parcial']];
        }
        $r = $partial
            ? $client->refundOrder($orderId, Client::uuid(), (string) $pays[0]['id'], $amount)
            : $client->refundOrder($orderId, Client::uuid());
        $refunds = (array) ($r['transactions']['refunds'] ?? []);
        $last = is_array(end($refunds)) ? end($refunds) : [];
        $raw = ['order' => $orderId, 'tipo' => $partial ? 'parcial' : 'total', 'status' => (string) ($r['status'] ?? ''), 'status_detail' => (string) ($r['status_detail'] ?? ''),
            'reembolso' => (string) ($last['id'] ?? ''), 'valor' => (string) ($last['amount'] ?? Payloads::money($amount))];
        if (in_array((string) ($r['status'] ?? ''), ['refunded', 'processed'], true)) {
            return ['status' => 'success', 'transid' => (string) ($last['id'] ?? $orderId), 'rawdata' => $raw];
        }
        return ['status' => 'declined', 'rawdata' => $raw];
    }

    /**
     * payer e additional_info da order. Falha ao ler dados extras do WHMCS não impede o link.
     * @return array{0: array, 1: array}
     */
    private function buyer(array $params, Config $c, int $invoiceId): array
    {
        $client = (array) ($params['clientdetails'] ?? []);
        $clientId = (int) ($client['userid'] ?? $client['id'] ?? 0);
        $doc = '';
        $created = '';
        $last = '';
        try {
            $doc = Buyer::document($client, $c->docField, $this->data);
            if ($clientId > 0) {
                $created = $this->data->clientCreated($clientId);
                $last = $this->data->lastPaidInvoice($clientId, $invoiceId);
            }
        } catch (\Throwable $e) {
            // segue sem documento e sem histórico
        }
        $info = $clientId > 0 ? Buyer::additionalInfo($created, $last) : [];
        return [Buyer::orderPayer($client, $c->payerEmail((string) ($client['email'] ?? '')), $doc), $info];
    }

    private function lines(int $invoiceId): string
    {
        try {
            return implode(' · ', array_filter(array_map(static function ($l) {
                return trim(preg_replace('/\s+/u', ' ', (string) $l) ?? '');
            }, $this->data->invoiceLines($invoiceId))));
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function english(array $params): bool
    {
        $lang = strtolower((string) ($params['clientdetails']['language'] ?? ''));
        return $lang !== '' && strpos($lang, 'portug') === false;
    }

    private static function notice(string $text): string
    {
        return '<div class="alert alert-warning mercadopagopro-notice">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</div>';
    }
}
