<?php

namespace MercadoPagoPro;

final class Module
{
    public const ID = 'mercadopagopro';
    public const VERSION = '1.0.0';
    public const MODE_LIVE = 'live';
    public const MODE_TEST = 'test';

    /** URL do callback (webhook e retorno do cliente) a partir da URL do sistema. */
    public static function callbackUrl(string $systemUrl): string
    {
        return rtrim($systemUrl, '/') . '/modules/gateways/callback/' . self::ID . '.php';
    }
}
