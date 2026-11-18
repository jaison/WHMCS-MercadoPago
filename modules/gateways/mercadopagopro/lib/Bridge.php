<?php

namespace MercadoPagoPro;

/**
 * Funções do WHMCS usadas na baixa. Real: WhmcsBridge; os testes usam um dublê.
 * checkInvoice/checkTransaction podem encerrar o script (é o que o WHMCS faz quando a fatura não
 * existe ou a transação já foi registrada).
 */
interface Bridge
{
    /** checkCbInvoiceID: devolve o id da fatura. */
    public function checkInvoice(int $invoiceId, string $gatewayName): int;

    /** checkCbTransID: encerra se a transação já existe. */
    public function checkTransaction(string $transId): void;

    public function addPayment(int $invoiceId, string $transId, float $amount, float $fee): void;

    /** @param array<string, scalar|null> $data */
    public function log(string $gatewayName, array $data, string $result): void;
}
