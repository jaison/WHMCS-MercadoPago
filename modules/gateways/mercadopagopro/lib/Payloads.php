<?php

namespace MercadoPagoPro;

/**
 * Corpos enviados à API: order do Checkout Pro e reembolsos.
 */
final class Payloads
{
    /**
     * Corpo de POST /v1/orders (Checkout Pro, API de Orders). Valores em texto com 2 casas.
     * @param array $payer          objeto payer pronto (Buyer::orderPayer); vazio = não envia
     * @param array $additionalInfo chaves "payer.*" (Buyer::additionalInfo); vazio = não envia
     */
    public static function order(int $invoiceId, float $amount, string $systemUrl, Config $c, string $title, array $payer = [], string $description = '', array $additionalInfo = []): array
    {
        $back = Module::callbackUrl($systemUrl) . '?return=1&inv=' . $invoiceId;
        $value = self::money($amount);
        $item = [
            'external_code' => 'INV-' . $invoiceId,
            'title' => mb_substr($title, 0, 256),
            'category_id' => 'services',
            'quantity' => 1,
            'unit_price' => $value,
        ];
        $description = self::summary($description);
        if ($description !== '') {
            $item['description'] = $description;
        }
        $online = [
            'success_url' => $back,
            'failure_url' => $back,
            'pending_url' => $back,
            'auto_return' => 'approved',
        ];
        // 3DS: a API exige validation junto com liability_shift.
        if ($c->threeDs !== 'off') {
            $online['transaction_security'] = ['validation' => $c->threeDs, 'liability_shift' => 'required'];
        }
        $paymentMethod = ['max_installments' => $c->installments];
        if ($c->excludeTicket) {
            $paymentMethod['not_allowed_types'] = ['ticket'];
        }
        $o = [
            'type' => 'online',
            'processing_mode' => 'manual',
            'total_amount' => $value,
            'external_reference' => (string) $invoiceId,
            'description' => mb_substr($title, 0, 256),
            'expiration_time' => 'PT' . $c->hours . 'H',
            'items' => [$item],
            'config' => [
                'statement_descriptor' => $c->descriptor,
                'online' => $online,
                'payment_method' => $paymentMethod,
            ],
        ];
        if ($payer) {
            $o['payer'] = $payer;
        }
        if ($additionalInfo) {
            $o['additional_info'] = $additionalInfo;
        }
        return $o;
    }

    /** Reembolso parcial de order: uma transação (pagamento da order) e o valor. */
    public static function orderRefund(string $paymentId, float $amount): array
    {
        return ['transactions' => [['id' => $paymentId, 'amount' => self::money($amount)]]];
    }

    /** "59.90" */
    public static function money(float $v): string
    {
        return number_format(round($v, 2), 2, '.', '');
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

    /** ISO 8601 com milissegundos e fuso: 2026-11-18T12:00:00.000-03:00 */
    public static function date(int $ts): string
    {
        return (new \DateTimeImmutable('@' . $ts))->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d\TH:i:s.000P');
    }
}
