<?php

namespace MercadoPagoPro;

use WHMCS\Database\Capsule;

/**
 * Tabela mod_mercadopagopro, criada na primeira vez que for usada.
 * Uma linha por fatura com a última preferência criada.
 */
final class CapsuleStore implements Store
{
    public const TABLE = 'mod_mercadopagopro';

    /** @var bool */
    private static $ready = false;

    public function find(int $invoiceId): ?array
    {
        $this->ensure();
        $r = Capsule::table(self::TABLE)->where('invoice_id', $invoiceId)->first();
        if (!$r) {
            return null;
        }
        return [
            'invoice_id' => (int) $r->invoice_id,
            'mode' => (string) $r->mode,
            'amount' => (string) $r->amount,
            'pref_id' => (string) $r->pref_id,
            'init_point' => (string) $r->init_point,
            'expires_at' => (int) $r->expires_at,
        ];
    }

    public function save(int $invoiceId, string $mode, string $amount, string $prefId, string $initPoint, int $expiresAt, int $now): void
    {
        $this->ensure();
        $row = ['mode' => $mode, 'amount' => $amount, 'pref_id' => $prefId, 'init_point' => $initPoint, 'expires_at' => $expiresAt, 'updated_at' => $now];
        if (Capsule::table(self::TABLE)->where('invoice_id', $invoiceId)->exists()) {
            Capsule::table(self::TABLE)->where('invoice_id', $invoiceId)->update($row);
        } else {
            Capsule::table(self::TABLE)->insert($row + ['invoice_id' => $invoiceId]);
        }
    }

    private function ensure(): void
    {
        if (self::$ready) {
            return;
        }
        $schema = Capsule::schema();
        if (!$schema->hasTable(self::TABLE)) {
            $schema->create(self::TABLE, static function ($t) {
                $t->increments('id');
                $t->unsignedInteger('invoice_id')->unique();
                $t->string('mode', 8);
                $t->decimal('amount', 12, 2);
                $t->string('pref_id', 80);
                $t->string('init_point', 500);
                $t->unsignedInteger('expires_at');
                $t->unsignedInteger('updated_at');
            });
        }
        self::$ready = true;
    }
}
