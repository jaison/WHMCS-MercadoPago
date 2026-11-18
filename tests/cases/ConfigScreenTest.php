<?php

use MercadoPagoPro\ConfigScreen;

return [
    'Ordem dos campos: modo, produção, teste, 3DS, parcelas, boleto, descrição, CPF/CNPJ, validade' => function () {
        $f = ConfigScreen::fields('https://loja.example.com/', [], 'pt');
        eq(['FriendlyName', 'UsageNotes', 'mode', 'token_live', 'secret_live', 'token_test', 'secret_test', 'test_payer_email', 'three_ds', 'installments', 'exclude_ticket', 'descriptor', 'doc_field', 'link_hours'], array_keys($f));
    },
    'URL do webhook sempre visível, só leitura, com evento Order e Tipo de API Orders' => function () {
        $n = ConfigScreen::fields('https://loja.example.com/', [], 'pt')['UsageNotes']['Value'];
        ok(strpos($n, 'readonly value="https://loja.example.com/modules/gateways/callback/mercadopagopro.php"') !== false, $n);
        ok(strpos($n, 'Order (Mercado Pago)') !== false && strpos($n, 'Orders') !== false);
        ok(strpos(ConfigScreen::fields('', [], 'pt')['UsageNotes']['Value'], 'https://SEU-WHMCS/modules/gateways/callback/mercadopagopro.php') !== false, 'sem SystemURL: exemplo');
    },
    'Links do painel abrem em nova aba; contas de teste nos campos de teste' => function () {
        $f = ConfigScreen::fields('https://x.example', [], 'pt');
        foreach (['token_live', 'secret_live', 'secret_test'] as $k) {
            ok(strpos($f[$k]['Description'], 'href="' . ConfigScreen::PANEL . '" target="_blank" rel="noopener"') !== false, $k);
        }
        foreach (['token_test', 'test_payer_email'] as $k) {
            ok(strpos($f[$k]['Description'], 'href="' . ConfigScreen::TEST_USERS . '" target="_blank" rel="noopener"') !== false, $k);
        }
        ok(strpos($f['token_test']['Description'], 'vendedor de teste') !== false);
        ok(strpos($f['test_payer_email']['Description'], 'comprador de teste') !== false);
    },
    'Cada campo com padrão indica o padrão; 3DS padrão "só com risco de fraude"' => function () {
        $f = ConfigScreen::fields('https://x.example', [], 'pt');
        foreach (['mode', 'three_ds', 'installments', 'exclude_ticket', 'descriptor', 'doc_field', 'link_hours'] as $k) {
            ok(strpos($f[$k]['Description'], 'Padrão') !== false, $k);
        }
        eq('on_fraud_risk', $f['three_ds']['Default']);
        ok(strpos($f['three_ds']['Description'], 'só com risco de fraude') !== false);
        ok(strpos($f['descriptor']['Description'], 'statement_descriptor') !== false);
        ok(strpos($f['doc_field']['Description'], 'Campos personalizados do cliente') !== false);
    },
    'Campos personalizados entram no seletor de CPF/CNPJ' => function () {
        $o = ConfigScreen::fields('https://x.example', [7 => 'CPF/CNPJ'], 'pt')['doc_field']['Options'];
        eq(['auto', 'tax_id', 'cf:7'], array_keys($o));
        eq('Campo personalizado: CPF/CNPJ', $o['cf:7']);
    },
    'Idioma: pt-BR no admin em português ou desconhecido; en nos outros; mesmas chaves nos dois' => function () {
        eq('pt', ConfigScreen::lang(['locale' => 'pt_BR']));
        eq('pt', ConfigScreen::lang(null));
        eq('en', ConfigScreen::lang(['locale' => 'en_GB']));
        eq(array_keys(ConfigScreen::texts('pt')), array_keys(ConfigScreen::texts('en')));
        $en = ConfigScreen::fields('https://x.example', [], 'en');
        eq('Production · Access Token', $en['token_live']['FriendlyName']);
        ok(strpos($en['three_ds']['Description'], 'Default: only on fraud risk') !== false);
        foreach ($en as $k => $v) {
            ok(strpos((string) ($v['Description'] ?? '') . (string) ($v['Value'] ?? ''), '%s') === false, 'placeholder sobrando em ' . $k);
        }
    },
];
