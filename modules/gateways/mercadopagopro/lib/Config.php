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

    public static function fromParams(array $p): self
    {
        $c = new self();
        $c->test = (string) ($p['mode'] ?? '') === Module::MODE_TEST;
        $c->token = trim((string) ($c->test ? ($p['token_test'] ?? '') : ($p['token_live'] ?? '')));
        $c->secret = trim((string) ($c->test ? ($p['secret_test'] ?? '') : ($p['secret_live'] ?? '')));
        $n = (int) ($p['installments'] ?? 0);
        $c->installments = $n >= 1 && $n <= 24 ? $n : 12;
        $c->descriptor = self::descriptor((string) ($p['descriptor'] ?? ''));
        // Checkbox do WHMCS: "on" marcado; vazio desmarcado. Sem valor salvo (nunca configurado) = marcado.
        $c->excludeTicket = !array_key_exists('exclude_ticket', $p) || in_array((string) $p['exclude_ticket'], ['on', '1', 'yes'], true);
        $h = (int) ($p['link_hours'] ?? 0);
        $c->hours = $h >= 1 && $h <= 720 ? $h : 72;
        $c->name = (string) ($p['name'] ?? $p['paymentmethod'] ?? Module::ID);
        return $c;
    }

    public function mode(): string
    {
        return $this->test ? 'test' : 'live';
    }

    /** Descrição na fatura do cartão: letras, números e espaço, até 22 caracteres. */
    public static function descriptor(string $s): string
    {
        $s = preg_replace('/[^A-Za-z0-9 ]+/', '', $s) ?? '';
        return trim(substr(trim(preg_replace('/\s+/', ' ', $s) ?? ''), 0, 22));
    }
}
