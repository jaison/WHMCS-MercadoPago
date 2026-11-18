<?php

namespace MercadoPagoPro;

final class WhmcsBridge implements Bridge
{
    public function checkInvoice(int $invoiceId, string $gatewayName): int
    {
        return (int) checkCbInvoiceID($invoiceId, $gatewayName);
    }

    public function checkTransaction(string $transId): void
    {
        checkCbTransID($transId);
    }

    public function addPayment(int $invoiceId, string $transId, float $amount, float $fee): void
    {
        addInvoicePayment($invoiceId, $transId, $amount, $fee, Module::ID);
    }

    public function log(string $gatewayName, array $data, string $result): void
    {
        logTransaction($gatewayName, $data, $result);
    }
}
