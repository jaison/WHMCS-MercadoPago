<?php

namespace MercadoPagoPro;

/**
 * Onde a preferência de cada fatura fica guardada. Real: CapsuleStore (tabela mod_mercadopagopro).
 */
interface Store
{
    /** @return array{invoice_id: int, mode: string, amount: string, pref_id: string, init_point: string, expires_at: int}|null */
    public function find(int $invoiceId): ?array;

    public function save(int $invoiceId, string $mode, string $amount, string $prefId, string $initPoint, int $expiresAt, int $now): void;
}
