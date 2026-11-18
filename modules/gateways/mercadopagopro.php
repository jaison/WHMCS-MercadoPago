<?php

/**
 * Mercado Pago Checkout Pro (API de Orders) para WHMCS 9.
 * Lógica em mercadopagopro/lib (MercadoPagoPro\*); aqui só as funções que o WHMCS chama.
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/mercadopagopro/lib/Autoload.php';

use MercadoPagoPro\CapsuleStore;
use MercadoPagoPro\ConfigScreen;
use MercadoPagoPro\CurlHttp;
use MercadoPagoPro\Gateway;
use MercadoPagoPro\Module;
use MercadoPagoPro\WhmcsBridge;
use MercadoPagoPro\WhmcsData;

function mercadopagopro_MetaData()
{
    return [
        'DisplayName' => 'Mercado Pago Checkout Pro',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    ];
}

function mercadopagopro_config()
{
    $systemUrl = '';
    $docFields = [];
    try {
        $systemUrl = (string) \WHMCS\Config\Setting::getValue('SystemURL');
        $docFields = (new WhmcsData())->clientCustomFields();
    } catch (\Throwable $e) {
        // fora do WHMCS ou sem banco: URL de exemplo e só as opções fixas
    }
    return ConfigScreen::fields($systemUrl, $docFields, ConfigScreen::lang($GLOBALS['_ADMINLANG'] ?? null));
}

function mercadopagopro_link($params)
{
    try {
        return (new Gateway(new CurlHttp(), new CapsuleStore(), new WhmcsBridge(), new WhmcsData()))->link($params, time());
    } catch (\Throwable $e) {
        logTransaction((string) ($params['name'] ?? Module::ID), ['fatura' => (string) ($params['invoiceid'] ?? ''), 'erro' => get_class($e)], 'Erro inesperado no link');
        return '';
    }
}

function mercadopagopro_refund($params)
{
    try {
        return (new Gateway(new CurlHttp(), new CapsuleStore(), new WhmcsBridge(), new WhmcsData()))->refund($params);
    } catch (\Throwable $e) {
        return ['status' => 'error', 'rawdata' => ['erro' => get_class($e)]];
    }
}
