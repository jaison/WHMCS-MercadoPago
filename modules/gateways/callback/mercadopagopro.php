<?php

/**
 * Callback do Mercado Pago Checkout Pro: webhook/IPN e retorno do cliente (?return=1).
 * Regras em ../mercadopagopro/lib/Callback.php.
 */

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once __DIR__ . '/../mercadopagopro/lib/Autoload.php';

use MercadoPagoPro\ApiException;
use MercadoPagoPro\Callback;
use MercadoPagoPro\Client;
use MercadoPagoPro\Config;
use MercadoPagoPro\CurlHttp;
use MercadoPagoPro\Module;
use MercadoPagoPro\WhmcsBridge;

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

// No WHMCS 9, gateway desativado faz getGatewayVariables lançar exceção.
try {
    $gateway = getGatewayVariables(Module::ID);
} catch (\Throwable $e) {
    $gateway = [];
}
if (empty($gateway['type'])) {
    http_response_code(200);
    echo 'inactive';
    exit;
}

$config = Config::fromParams($gateway);
try {
    $client = new Client($config->token, new CurlHttp());
} catch (ApiException $e) {
    $client = null;
}
$systemUrl = (string) ($gateway['systemurl'] ?? '');
if ($systemUrl === '') {
    $systemUrl = (string) \WHMCS\Config\Setting::getValue('SystemURL');
}

$callback = new Callback($config, $client, new WhmcsBridge(), $systemUrl);

// Retorno do cliente: se o WHMCS encerrar o script no meio (transação já registrada pelo
// webhook, por exemplo), ainda assim manda o cliente para a fatura.
$done = false;
register_shutdown_function(static function () use ($callback, &$done) {
    if (!$done && $callback->pendingRedirect !== null && !headers_sent()) {
        header('Location: ' . $callback->pendingRedirect, true, 302);
    }
});

try {
    $res = $callback->handle($_GET, $_SERVER, (string) file_get_contents('php://input'));
} catch (\Throwable $e) {
    logTransaction($config->name, ['erro' => get_class($e)], 'Erro inesperado no callback');
    $res = $callback->pendingRedirect !== null
        ? ['status' => 302, 'body' => '', 'location' => $callback->pendingRedirect]
        : ['status' => 503, 'body' => 'retry', 'location' => null];
}
$done = true;
if ($res['location'] !== null) {
    header('Location: ' . $res['location'], true, 302);
    exit;
}
http_response_code($res['status']);
header('Content-Type: text/plain; charset=utf-8');
echo $res['body'];
