<?php

namespace MercadoPagoPro;

/**
 * Link de pagamento de uma fatura: reaproveita a preferência guardada enquanto o valor e o modo
 * forem os mesmos e faltar mais de MARGIN segundos para expirar; senão cria outra e guarda.
 */
final class PreferenceCache
{
    /** Não entrega link que expira em menos de 30 min. */
    public const MARGIN = 1800;

    /** @var Store */
    private $store;

    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    /**
     * @param callable(): array $create devolve a preferência criada na API (id, init_point)
     * @return string init_point
     */
    public function link(int $invoiceId, float $amount, Config $c, int $now, callable $create): string
    {
        $amountText = number_format($amount, 2, '.', '');
        $row = $this->store->find($invoiceId);
        if ($row && $row['mode'] === $c->mode() && number_format((float) $row['amount'], 2, '.', '') === $amountText
            && $row['expires_at'] - $now > self::MARGIN && $row['init_point'] !== '') {
            return $row['init_point'];
        }
        $pref = $create();
        $url = (string) ($pref['init_point'] ?? '');
        if ($url === '') {
            $url = (string) ($pref['sandbox_init_point'] ?? '');
        }
        if ($url === '' || strpos($url, 'https://') !== 0) {
            throw new ApiException(-1, 'preferência sem init_point');
        }
        $this->store->save($invoiceId, $c->mode(), $amountText, (string) ($pref['id'] ?? ''), $url, $now + $c->hours * 3600, $now);
        return $url;
    }
}
