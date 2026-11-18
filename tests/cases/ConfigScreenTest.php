<?php

use MercadoPagoPro\ConfigScreen;

return [
    'Ordem dos campos: modo, produção, teste, 3DS, parcelas, boleto, descrição, CPF/CNPJ, validade' => function () {
        $f = ConfigScreen::fields('https://loja.example.com/', []);
        eq(['FriendlyName', 'UsageNotes', 'mode', 'token_live', 'secret_live', 'token_test', 'secret_test', 'test_payer_email', 'three_ds', 'installments', 'exclude_ticket', 'descriptor', 'doc_field', 'link_hours'], array_keys($f));
    },
    'URL do webhook sempre visível, só leitura, com evento Order e Tipo de API Orders' => function () {
        $n = ConfigScreen::fields('https://loja.example.com/', [])['UsageNotes']['Value'];
        ok(strpos($n, 'readonly value="https://loja.example.com/modules/gateways/callback/mercadopagopro.php"') !== false, $n);
        ok(strpos($n, 'Order (Mercado Pago)') !== false && strpos($n, 'Orders') !== false);
        ok(strpos(ConfigScreen::fields('', [])['UsageNotes']['Value'], 'https://SEU-WHMCS/modules/gateways/callback/mercadopagopro.php') !== false, 'sem SystemURL: exemplo');
    },
    'Links abrem em nova aba; tokens em Credenciais › Produção/Teste; contas de teste só no comprador; Custos nas parcelas' => function () {
        $f = ConfigScreen::fields('https://x.example', []);
        foreach (['token_live', 'secret_live', 'token_test', 'secret_test'] as $k) {
            ok(strpos($f[$k]['Description'], 'href="' . ConfigScreen::PANEL . '" target="_blank" rel="noopener"') !== false, $k);
        }
        ok(strpos($f['test_payer_email']['Description'], 'href="' . ConfigScreen::TEST_USERS . '" target="_blank" rel="noopener"') !== false);
        foreach (['token_live', 'token_test', 'secret_live', 'secret_test'] as $k) {
            ok(strpos($f[$k]['Description'], ConfigScreen::TEST_USERS) === false, 'contas de teste não aparecem em ' . $k);
        }
        ok(strpos($f['token_test']['Description'], 'Credenciais › Teste › Access Token') !== false);
        ok(strpos($f['token_live']['Description'], 'Credenciais › Produção › Access Token') !== false);
        ok(strpos($f['installments']['Description'], 'href="' . ConfigScreen::COSTS . '" target="_blank" rel="noopener"') !== false);
        ok(strpos($f['test_payer_email']['Description'], 'comprador de teste') !== false);
    },
    'Cada campo com padrão indica o padrão; 3DS padrão "só com risco de fraude"' => function () {
        $f = ConfigScreen::fields('https://x.example', []);
        foreach (['mode', 'three_ds', 'installments', 'exclude_ticket', 'descriptor', 'doc_field', 'link_hours'] as $k) {
            ok(strpos($f[$k]['Description'], 'Padrão') !== false, $k);
        }
        eq('on_fraud_risk', $f['three_ds']['Default']);
        ok(strpos($f['three_ds']['Description'], 'só com risco de fraude') !== false);
        ok(strpos($f['descriptor']['Description'], 'statement_descriptor') !== false);
        ok(strpos($f['doc_field']['Description'], 'Campos personalizados do cliente') !== false);
    },
    'Campos personalizados entram no seletor de CPF/CNPJ' => function () {
        $o = ConfigScreen::fields('https://x.example', [7 => 'CPF/CNPJ'])['doc_field']['Options'];
        eq(['auto', 'tax_id', 'cf:7'], array_keys($o));
        eq('Campo personalizado: CPF/CNPJ', $o['cf:7']);
    },
    'Só pt-BR: nenhum placeholder sobrando' => function () {
        foreach (ConfigScreen::fields('https://x.example', [7 => 'CPF']) as $k => $v) {
            ok(strpos((string) ($v['Description'] ?? '') . (string) ($v['Value'] ?? ''), '%s') === false, 'placeholder sobrando em ' . $k);
        }
    },
];
