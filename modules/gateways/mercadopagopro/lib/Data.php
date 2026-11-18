<?php

namespace MercadoPagoPro;

/**
 * Dados do WHMCS que não vêm nos $params do gateway. Real: WhmcsData (Capsule, só leitura).
 */
interface Data
{
    /** Descrições das linhas da fatura. @return string[] */
    public function invoiceLines(int $invoiceId): array;

    /** Data de cadastro do cliente (Y-m-d ou Y-m-d H:i:s), '' se não houver. */
    public function clientCreated(int $clientId): string;

    /** Valor de um campo personalizado do cliente, '' se não houver. */
    public function customFieldValue(int $clientId, int $fieldId): string;

    /** Campos personalizados de cliente: [id => nome]. */
    public function clientCustomFields(): array;
}
