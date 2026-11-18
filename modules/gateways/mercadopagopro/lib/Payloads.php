<?php

namespace MercadoPagoPro;

/**
 * Corpos enviados à API: preferência do Checkout Pro e reembolso.
 */
final class Payloads
{
    /**
     * @param int    $invoiceId
     * @param float  $amount     saldo da fatura, em BRL
     * @param string $email      e-mail do cliente do WHMCS (em Teste é trocado pelo do comprador de teste)
     * @param string $systemUrl  URL do WHMCS
     * @param int    $now        timestamp
     */
    public static function preference(int $invoiceId, float $amount, string $email, string $systemUrl, Config $c, int $now, string $title): array
    {
        $cb = Module::callbackUrl($systemUrl);
        $back = $cb . '?return=1&inv=' . $invoiceId;
        $p = [
            'items' => [[
                'id' => (string) $invoiceId,
                'title' => $title,
                'quantity' => 1,
                'unit_price' => round($amount, 2),
                'currency_id' => 'BRL',
            ]],
            'external_reference' => (string) $invoiceId,
            'back_urls' => ['success' => $back, 'pending' => $back, 'failure' => $back],
            'auto_return' => 'approved',
            'notification_url' => $cb . '?source_news=webhooks',
            'payment_methods' => [
                'installments' => $c->installments,
                'excluded_payment_types' => $c->excludeTicket ? [['id' => 'ticket']] : [],
            ],
            'expires' => true,
            'expiration_date_from' => self::date($now),
            'expiration_date_to' => self::date($now + $c->hours * 3600),
        ];
        $payer = $c->payerEmail($email);
        if ($payer !== '') {
            $p['payer'] = ['email' => $payer];
        }
        if ($c->descriptor !== '') {
            $p['statement_descriptor'] = $c->descriptor;
        }
        return $p;
    }

    /**
     * Impressão digital do que vai na preferência além de fatura e valor: muda quando muda o
     * formato do payload (PREFERENCE_VERSION) ou a configuração que entra nele, e aí o link guardado
     * deixa de valer. 8 caracteres: "t:" ou "l:" (modo) + 6 hexadecimais.
     */
    public static function fingerprint(Config $c, string $clientEmail): string
    {
        $parts = [Module::PREFERENCE_VERSION, $c->payerEmail($clientEmail), $c->installments, $c->excludeTicket ? 1 : 0, $c->descriptor];
        return substr($c->mode(), 0, 1) . ':' . substr(hash('sha256', implode('|', $parts)), 0, 6);
    }

    public static function refund(float $amount): array
    {
        return ['amount' => round($amount, 2)];
    }

    /** ISO 8601 com milissegundos e fuso, como a API pede: 2026-11-18T12:00:00.000-03:00 */
    public static function date(int $ts): string
    {
        return (new \DateTimeImmutable('@' . $ts))->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d\TH:i:s.000P');
    }
}
