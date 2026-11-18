<?php

namespace MercadoPagoPro;

final class Module
{
    public const ID = 'mercadopagopro';
    public const VERSION = '1.1.0';
    /** Sobe quando o corpo da preferência muda, para não reaproveitar links antigos. */
    public const PREFERENCE_VERSION = 3;
    /** statement_descriptor quando não há nome configurado nem nome da empresa. */
    public const DEFAULT_DESCRIPTOR = 'MERCADOPAGO';
    public const MODE_LIVE = 'live';
    public const MODE_TEST = 'test';

    /** URL do callback (webhook e retorno do cliente) a partir da URL do sistema. */
    public static function callbackUrl(string $systemUrl): string
    {
        return rtrim($systemUrl, '/') . '/modules/gateways/callback/' . self::ID . '.php';
    }
}
