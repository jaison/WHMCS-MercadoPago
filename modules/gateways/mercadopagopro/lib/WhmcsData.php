<?php

namespace MercadoPagoPro;

use WHMCS\Database\Capsule;

/** Consultas leves e somente leitura nas tabelas do WHMCS. */
final class WhmcsData implements Data
{
    public function invoiceLines(int $invoiceId): array
    {
        return array_map('strval', Capsule::table('tblinvoiceitems')->where('invoiceid', $invoiceId)->orderBy('id')->limit(20)->pluck('description')->all());
    }

    public function clientCreated(int $clientId): string
    {
        return (string) Capsule::table('tblclients')->where('id', $clientId)->value('datecreated');
    }

    public function lastPaidInvoice(int $clientId, int $exceptInvoiceId): string
    {
        return (string) Capsule::table('tblinvoices')->where('userid', $clientId)->where('status', 'Paid')
            ->where('id', '!=', $exceptInvoiceId)->where('total', '>', 0)->max('datepaid');
    }

    public function customFieldValue(int $clientId, int $fieldId): string
    {
        return (string) Capsule::table('tblcustomfieldsvalues')->where('fieldid', $fieldId)->where('relid', $clientId)->value('value');
    }

    public function clientCustomFields(): array
    {
        $out = [];
        foreach (Capsule::table('tblcustomfields')->where('type', 'client')->orderBy('sortorder')->orderBy('id')->get(['id', 'fieldname']) as $f) {
            $out[(int) $f->id] = (string) $f->fieldname;
        }
        return $out;
    }
}
