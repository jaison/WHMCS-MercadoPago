<?php

namespace MercadoPagoPro;

final class Module
{
    public const ID = 'mercadopagopro';
    public const VERSION = '2.0.1';
    /** Sobe quando o corpo da preferência muda, para não reaproveitar links antigos. */
    public const PREFERENCE_VERSION = 4;
    /** statement_descriptor quando não há nome configurado nem nome da empresa. */
    public const DEFAULT_DESCRIPTOR = 'MERCADOPAGO';
    public const MODE_LIVE = 'live';
    public const MODE_TEST = 'test';

    /** Id de order do Mercado Pago (ORD + letras e números). */
    public static function isOrderId(string $id): bool
    {
        return preg_match('/^ORD[A-Za-z0-9]{6,61}$/', $id) === 1;
    }

    /** URL do callback (webhook e retorno do cliente) a partir da URL do sistema. */
    public static function callbackUrl(string $systemUrl): string
    {
        return rtrim($systemUrl, '/') . '/modules/gateways/callback/' . self::ID . '.php';
    }
}
