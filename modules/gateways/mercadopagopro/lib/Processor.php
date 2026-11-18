<?php

namespace MercadoPagoPro;

/**
 * Baixa de um pagamento já consultado em GET /v1/payments/{id}.
 * Só "approved" em BRL, no modo configurado, com external_reference numérico vira addInvoicePayment.
 * Estorno e chargeback só vão para o Log de Gateways. Nada do pagador é registrado.
 */
final class Processor
{
    public const PAID = 'paid';
    public const IGNORED = 'ignored';
    public const LOGGED = 'logged';

    /** @var Bridge */
    private $bridge;
    /** @var Config */
    private $config;

    public function __construct(Bridge $bridge, Config $config)
    {
        $this->bridge = $bridge;
        $this->config = $config;
    }

    /** approved → paid; pending/in_process/authorized → pending; rejected/cancelled → failed; refunded/charged_back/in_mediation → reversed. */
    public static function classify(string $status): string
    {
        switch ($status) {
            case 'approved':
                return 'paid';
            case 'pending':
            case 'in_process':
            case 'authorized':
                return 'pending';
            case 'rejected':
            case 'cancelled':
                return 'failed';
            case 'refunded':
            case 'charged_back':
            case 'in_mediation':
                return 'reversed';
        }
        return 'unknown';
    }

    public static function fee(array $payment): float
    {
        $fee = 0.0;
        foreach ((array) ($payment['fee_details'] ?? []) as $f) {
            if (is_array($f) && is_numeric($f['amount'] ?? null)) {
                $fee += (float) $f['amount'];
            }
        }
        return round($fee, 2);
    }

    /** Campos que vão ao Log de Gateways (sem pagador, sem token). */
    public static function summary(array $p): array
    {
        return [
            'id' => (string) ($p['id'] ?? ''),
            'status' => (string) ($p['status'] ?? ''),
            'status_detail' => (string) ($p['status_detail'] ?? ''),
            'valor' => is_numeric($p['transaction_amount'] ?? null) ? (string) $p['transaction_amount'] : '',
            'moeda' => (string) ($p['currency_id'] ?? ''),
            'taxa' => (string) self::fee($p),
            'metodo' => trim((string) ($p['payment_type_id'] ?? '') . '/' . (string) ($p['payment_method_id'] ?? ''), '/'),
            'live_mode' => array_key_exists('live_mode', $p) ? ($p['live_mode'] ? 'sim' : 'não') : '',
            'fatura' => (string) ($p['external_reference'] ?? ''),
        ];
    }

    public function settle(array $p): string
    {
        $name = $this->config->name;
        $log = self::summary($p);
        $id = (string) ($p['id'] ?? '');
        if (!preg_match('/^\d{1,20}$/', $id)) {
            $this->bridge->log($name, $log, 'Ignorado: pagamento sem id');
            return self::IGNORED;
        }
        if (array_key_exists('live_mode', $p) && (bool) $p['live_mode'] === $this->config->test) {
            $this->bridge->log($name, $log, 'Ignorado: modo do pagamento diferente do configurado');
            return self::IGNORED;
        }
        $kind = self::classify((string) ($p['status'] ?? ''));
        if ($kind === 'reversed') {
            $this->bridge->log($name, $log, 'Estorno ou contestação (sem baixa automática)');
            return self::LOGGED;
        }
        if ($kind !== 'paid') {
            return self::IGNORED;
        }
        if (($p['currency_id'] ?? '') !== 'BRL') {
            $this->bridge->log($name, $log, 'Ignorado: moeda diferente de BRL');
            return self::IGNORED;
        }
        $ref = (string) ($p['external_reference'] ?? '');
        if (!preg_match('/^\d{1,10}$/', $ref) || !is_numeric($p['transaction_amount'] ?? null)) {
            $this->bridge->log($name, $log, 'Ignorado: sem fatura ou valor');
            return self::IGNORED;
        }
        $invoiceId = $this->bridge->checkInvoice((int) $ref, $name);
        $this->bridge->checkTransaction($id);
        $this->bridge->addPayment($invoiceId, $id, round((float) $p['transaction_amount'], 2), self::fee($p));
        $this->bridge->log($name, $log, 'Successful');
        return self::PAID;
    }
}
