<?php

/**
 * Mercado Pago Checkout Pro para WHMCS 9.
 * Lógica em mercadopagopro/lib (MercadoPagoPro\*); aqui só as funções que o WHMCS chama.
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/mercadopagopro/lib/Autoload.php';

use MercadoPagoPro\CapsuleStore;
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
    try {
        $systemUrl = (string) \WHMCS\Config\Setting::getValue('SystemURL');
    } catch (\Throwable $e) {
        $systemUrl = '';
    }
    // Origem do CPF/CNPJ: Tax ID ou um campo personalizado de cliente.
    $docOptions = ['auto' => 'Automático (Tax ID; senão campo com CPF/CNPJ no nome)', 'tax_id' => 'Tax ID do WHMCS'];
    try {
        foreach ((new \MercadoPagoPro\WhmcsData())->clientCustomFields() as $id => $name) {
            $docOptions['cf:' . $id] = 'Campo personalizado: ' . $name;
        }
    } catch (\Throwable $e) {
        // sem a lista de campos: só as opções fixas
    }
    $webhook = htmlspecialchars(Module::callbackUrl($systemUrl !== '' ? $systemUrl : 'https://SEU-WHMCS'), ENT_QUOTES, 'UTF-8');

    return [
        'FriendlyName' => ['Type' => 'System', 'Value' => 'Mercado Pago'],
        'mode' => [
            'FriendlyName' => 'Modo',
            'Type' => 'dropdown',
            'Options' => [Module::MODE_LIVE => 'Produção', Module::MODE_TEST => 'Teste'],
            'Default' => Module::MODE_LIVE,
            'Description' => 'Em Teste, use as credenciais de teste e um comprador de teste do Mercado Pago.',
        ],
        'token_live' => ['FriendlyName' => 'Access Token de produção', 'Type' => 'password', 'Size' => '80'],
        'token_test' => ['FriendlyName' => 'Access Token de teste', 'Type' => 'password', 'Size' => '80'],
        'secret_live' => [
            'FriendlyName' => 'Secret do webhook (produção)',
            'Type' => 'password',
            'Size' => '80',
            'Description' => 'Opcional. Se preenchido, webhooks sem assinatura válida são recusados (401).',
        ],
        'secret_test' => ['FriendlyName' => 'Secret do webhook (teste)', 'Type' => 'password', 'Size' => '80', 'Description' => 'Opcional.'],
        'test_payer_email' => [
            'FriendlyName' => 'E-mail do comprador de teste',
            'Type' => 'text',
            'Size' => '50',
            'Description' => 'Opcional, só no modo Teste: e-mail da conta de comprador de teste do Mercado Pago. Em Teste, o e-mail do cliente do WHMCS nunca é enviado.',
        ],
        'doc_field' => [
            'FriendlyName' => 'Campo do CPF/CNPJ',
            'Type' => 'dropdown',
            'Options' => $docOptions,
            'Default' => 'auto',
            'Description' => 'De onde vem o documento do comprador enviado ao Mercado Pago (só se for CPF ou CNPJ válido).',
        ],
        'installments' => ['FriendlyName' => 'Parcelas máximas', 'Type' => 'text', 'Size' => '4', 'Default' => '12', 'Description' => 'De 1 a 24.'],
        'descriptor' => [
            'FriendlyName' => 'Descrição na fatura do cartão',
            'Type' => 'text',
            'Size' => '13',
            'Description' => 'statement_descriptor, até 13 caracteres (maiúsculas, sem acento). Vazio: nome da empresa do WHMCS.',
        ],
        'exclude_ticket' => ['FriendlyName' => 'Excluir boleto', 'Type' => 'yesno', 'Default' => 'on', 'Description' => 'Não oferecer boleto no Mercado Pago.'],
        'link_hours' => ['FriendlyName' => 'Validade do link (horas)', 'Type' => 'text', 'Size' => '4', 'Default' => '72', 'Description' => 'De 1 a 720.'],
        // Nota exibida no topo da configuração do gateway.
        'UsageNotes' => [
            'Type' => 'System',
            'Value' => 'URL do webhook: <code style="user-select:all">' . $webhook . '</code>. '
                . 'Cadastre no Mercado Pago (Suas integrações › sua aplicação › Webhooks, evento Pagamentos) e copie a assinatura secreta para o campo Secret do webhook. '
                . 'Cada link de pagamento também envia essa URL (notification_url).',
        ],
    ];
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
