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

    public function __construct(Http $http, Store $store, Bridge $bridge)
    {
        $this->http = $http;
        $this->store = $store;
        $this->bridge = $bridge;
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
            $url = (new PreferenceCache($this->store))->link($invoiceId, $amount, $c, $now, static function () use ($client, $invoiceId, $amount, $params, $systemUrl, $c, $now, $title) {
                $email = (string) ($params['clientdetails']['email'] ?? '');
                return $client->createPreference(Payloads::preference($invoiceId, $amount, $email, $systemUrl, $c, $now, $title), Client::uuid());
            });
        } catch (ApiException $e) {
            $this->bridge->log($c->name, ['fatura' => (string) $invoiceId, 'modo' => $c->mode(), 'erro' => $e->getMessage()], 'Erro ao criar a preferência');
            return self::notice($en ? 'We could not generate the Mercado Pago payment link right now. Please try again in a few minutes.' : 'Não foi possível gerar o link de pagamento do Mercado Pago agora. Tente de novo em alguns minutos.');
        }
        $label = $en ? 'Pay with Mercado Pago' : 'Pagar com Mercado Pago';
        return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary mercadopagopro-pay" rel="noopener">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
    }

    /** @return array{status: string, rawdata: array<mixed>, transid?: string} formato do _refund do WHMCS */
    public function refund(array $params): array
    {
        $c = Config::fromParams($params);
        $transId = (string) ($params['transid'] ?? '');
        $amount = round((float) ($params['amount'] ?? 0), 2);
        if ($amount <= 0) {
            return ['status' => 'error', 'rawdata' => ['erro' => 'valor inválido']];
        }
        try {
            $r = (new Client($c->token, $this->http))->refund($transId, $amount, Client::uuid());
        } catch (ApiException $e) {
            return ['status' => $e->status >= 400 && $e->status < 500 ? 'declined' : 'error', 'rawdata' => ['pagamento' => $transId, 'erro' => $e->getMessage()]];
        }
        $raw = ['pagamento' => $transId, 'reembolso' => (string) ($r['id'] ?? ''), 'status' => (string) ($r['status'] ?? ''), 'valor' => (string) ($r['amount'] ?? $amount)];
        $status = (string) ($r['status'] ?? '');
        // in_process: o Mercado Pago aceitou e conclui depois; o WHMCS não tem estado pendente.
        if ($status === 'approved' || $status === 'in_process') {
            return ['status' => 'success', 'transid' => (string) ($r['id'] ?? ''), 'rawdata' => $raw];
        }
        return ['status' => $status === '' ? 'error' : 'declined', 'rawdata' => $raw];
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
