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
     * @param string $email      e-mail do cliente (vai só ao Mercado Pago)
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
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $p['payer'] = ['email' => $email];
        }
        if ($c->descriptor !== '') {
            $p['statement_descriptor'] = $c->descriptor;
        }
        return $p;
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
