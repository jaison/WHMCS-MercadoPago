<?php

namespace MercadoPagoPro;

/**
 * Configuração do gateway lida dos $params do WHMCS (ou de getGatewayVariables), com o
 * token e o secret do modo escolhido.
 */
final class Config
{
    /** @var bool */
    public $test;
    /** @var string */
    public $token;
    /** @var string */
    public $secret;
    /** @var int */
    public $installments;
    /** @var string */
    public $descriptor;
    /** @var bool */
    public $excludeTicket;
    /** @var int */
    public $hours;
    /** @var string */
    public $name;
    /** @var string e-mail do comprador de teste (só no modo Teste) */
    public $testPayerEmail;
    /** @var string 3DS (config.online.transaction_security.validation): off, on_fraud_risk ou always */
    public $threeDs;
    /** @var string origem do CPF/CNPJ: '' automático, 'tax_id' ou 'cf:<id>' */
    public $docField;

    public static function fromParams(array $p): self
    {
        $c = new self();
        $c->test = (string) ($p['mode'] ?? '') === Module::MODE_TEST;
        $c->token = trim((string) ($c->test ? ($p['token_test'] ?? '') : ($p['token_live'] ?? '')));
        $c->secret = trim((string) ($c->test ? ($p['secret_test'] ?? '') : ($p['secret_live'] ?? '')));
        $n = (int) ($p['installments'] ?? 0);
        $c->installments = $n >= 1 && $n <= 24 ? $n : 12;
        // Sempre preenchida: a configurada; senão o nome da empresa do WHMCS; senão um padrão.
        $c->descriptor = self::descriptor((string) ($p['descriptor'] ?? ''));
        if ($c->descriptor === '') {
            $c->descriptor = self::descriptor((string) ($p['companyname'] ?? ''));
        }
        if ($c->descriptor === '') {
            $c->descriptor = Module::DEFAULT_DESCRIPTOR;
        }
        // Checkbox do WHMCS: "on" marcado; vazio desmarcado. Sem valor salvo (nunca configurado) = marcado.
        $c->excludeTicket = !array_key_exists('exclude_ticket', $p) || in_array((string) $p['exclude_ticket'], ['on', '1', 'yes'], true);
        $t3 = (string) ($p['three_ds'] ?? '');
        $c->threeDs = in_array($t3, ['off', 'on_fraud_risk', 'always'], true) ? $t3 : 'on_fraud_risk';
        $h = (int) ($p['link_hours'] ?? 0);
        $c->hours = $h >= 1 && $h <= 720 ? $h : 72;
        $c->name = (string) ($p['name'] ?? $p['paymentmethod'] ?? Module::ID);
        $t = trim((string) ($p['test_payer_email'] ?? ''));
        $c->testPayerEmail = filter_var($t, FILTER_VALIDATE_EMAIL) ? $t : '';
        $d = (string) ($p['doc_field'] ?? '');
        $c->docField = $d === 'tax_id' || preg_match('/^cf:\d{1,10}$/', $d) ? $d : '';
        return $c;
    }

    public function mode(): string
    {
        return $this->test ? 'test' : 'live';
    }

    /**
     * E-mail que vai em payer.email. Em Teste, nunca o do cliente do WHMCS (o Mercado Pago recusa
     * misturar vendedor de teste com comprador real): só o comprador de teste configurado, se houver.
     */
    public function payerEmail(string $clientEmail): string
    {
        if ($this->test) {
            return $this->testPayerEmail;
        }
        return filter_var($clientEmail, FILTER_VALIDATE_EMAIL) ? $clientEmail : '';
    }

    /**
     * Descrição na fatura do cartão (statement_descriptor): até 13 caracteres (limite do Mercado
     * Pago no Brasil), maiúsculas, sem acento, só letras, números e espaço.
     */
    public static function descriptor(string $s): string
    {
        $map = ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n'];
        $s = strtr(mb_strtolower($s, 'UTF-8'), $map);
        $s = strtoupper(preg_replace('/[^a-z0-9 ]+/', '', $s) ?? '');
        return trim(substr(trim(preg_replace('/\s+/', ' ', $s) ?? ''), 0, 13));
    }
}
