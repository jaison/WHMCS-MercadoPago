<?php

namespace MercadoPagoPro;

/**
 * Corpos enviados à API: preferência do Checkout Pro e reembolso.
 */
final class Payloads
{
    /**
     * Corpo de POST /checkout/preferences.
     * @param array  $payer       objeto payer pronto (Buyer::payer); vazio = não envia
     * @param string $description resumo das linhas da fatura
     */
    public static function preference(int $invoiceId, float $amount, string $systemUrl, Config $c, int $now, string $title, array $payer = [], string $description = ''): array
    {
        $cb = Module::callbackUrl($systemUrl);
        $back = $cb . '?return=1&inv=' . $invoiceId;
        $item = [
            'id' => 'INV-' . $invoiceId,
            'title' => mb_substr($title, 0, 256),
            'category_id' => 'services',
            'quantity' => 1,
            'unit_price' => round($amount, 2),
            'currency_id' => 'BRL',
        ];
        $description = self::summary($description);
        if ($description !== '') {
            $item['description'] = $description;
        }
        $p = [
            'items' => [$item],
            'external_reference' => (string) $invoiceId,
            'back_urls' => ['success' => $back, 'pending' => $back, 'failure' => $back],
            'auto_return' => 'approved',
            'notification_url' => $cb . '?source_news=webhooks',
            'payment_methods' => [
                'installments' => $c->installments,
                'excluded_payment_types' => $c->excludeTicket ? [['id' => 'ticket']] : [],
            ],
            'statement_descriptor' => $c->descriptor,
            'expires' => true,
            'expiration_date_from' => self::date($now),
            'expiration_date_to' => self::date($now + $c->hours * 3600),
        ];
        if ($payer) {
            $p['payer'] = $payer;
        }
        return $p;
    }

    /** Linhas da fatura num texto só, sem quebras, até 256 caracteres. */
    public static function summary(string $s): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
        return mb_strlen($s) > 256 ? rtrim(mb_substr($s, 0, 255)) . '…' : $s;
    }

    /**
     * Chave do link guardado: modo + impressão digital de tudo o que vai na preferência, menos as
     * datas de validade. Muda o comprador, a fatura, a configuração ou o formato (PREFERENCE_VERSION)
     * e o link guardado deixa de valer. 8 caracteres: "t:" ou "l:" + 6 hexadecimais.
     */
    public static function fingerprint(Config $c, array $payload): string
    {
        unset($payload['expiration_date_from'], $payload['expiration_date_to']);
        $json = json_encode([Module::PREFERENCE_VERSION, $payload], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return substr($c->mode(), 0, 1) . ':' . substr(hash('sha256', (string) $json), 0, 6);
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
