<?php

namespace MercadoPagoPro;

/**
 * Tela de configuração do gateway (_config): rótulos curtos, descrição de uma linha, padrão e
 * link para a página certa do painel do Mercado Pago. pt-BR, ou en quando o admin não é português.
 */
final class ConfigScreen
{
    public const PANEL = 'https://www.mercadopago.com.br/developers/panel/app';
    public const TEST_USERS = 'https://www.mercadopago.com.br/developers/panel/app/test-users';

    /** 'pt' se o idioma do admin for português (ou desconhecido), senão 'en'. */
    public static function lang(?array $adminLang): string
    {
        $locale = strtolower((string) ($adminLang['locale'] ?? ''));
        return $locale === '' || strpos($locale, 'pt') === 0 ? 'pt' : 'en';
    }

    /**
     * @param array<int, string> $docFields campos personalizados de cliente [id => nome]
     */
    public static function fields(string $systemUrl, array $docFields, string $lang): array
    {
        $t = self::texts($lang === 'en' ? 'en' : 'pt');
        $url = Module::callbackUrl($systemUrl !== '' ? $systemUrl : 'https://SEU-WHMCS');
        $panel = self::a(self::PANEL, $t['panel']);
        $testUsers = self::a(self::TEST_USERS, $t['test_users']);

        $docOptions = ['auto' => $t['doc_auto'], 'tax_id' => $t['doc_tax']];
        foreach ($docFields as $id => $name) {
            $docOptions['cf:' . (int) $id] = $t['doc_cf'] . ' ' . $name;
        }

        return [
            'FriendlyName' => ['Type' => 'System', 'Value' => 'Mercado Pago'],
            // Quadro no topo da configuração: URL do webhook pronta para copiar.
            'UsageNotes' => [
                'Type' => 'System',
                'Value' => '<b>' . $t['webhook'] . '</b> '
                    . '<input type="text" readonly value="' . self::h($url) . '" onclick="this.select()" style="width:100%;max-width:640px;font-family:monospace">'
                    . '<br>' . sprintf($t['webhook_desc'], $panel),
            ],
            'mode' => [
                'FriendlyName' => $t['mode'],
                'Type' => 'dropdown',
                'Options' => [Module::MODE_LIVE => $t['mode_live'], Module::MODE_TEST => $t['mode_test']],
                'Default' => Module::MODE_LIVE,
                'Description' => $t['mode_desc'],
            ],
            'token_live' => ['FriendlyName' => $t['token_live'], 'Type' => 'password', 'Size' => '80', 'Description' => sprintf($t['token_live_desc'], $panel)],
            'secret_live' => ['FriendlyName' => $t['secret_live'], 'Type' => 'password', 'Size' => '80', 'Description' => sprintf($t['secret_desc'], $panel)],
            'token_test' => ['FriendlyName' => $t['token_test'], 'Type' => 'password', 'Size' => '80', 'Description' => sprintf($t['token_test_desc'], $testUsers)],
            'secret_test' => ['FriendlyName' => $t['secret_test'], 'Type' => 'password', 'Size' => '80', 'Description' => sprintf($t['secret_test_desc'], $panel)],
            'test_payer_email' => ['FriendlyName' => $t['payer_test'], 'Type' => 'text', 'Size' => '50', 'Description' => sprintf($t['payer_test_desc'], $testUsers)],
            'three_ds' => [
                'FriendlyName' => '3DS',
                'Type' => 'dropdown',
                'Options' => ['on_fraud_risk' => $t['3ds_risk'], 'always' => $t['3ds_always'], 'off' => $t['3ds_off']],
                'Default' => 'on_fraud_risk',
                'Description' => $t['3ds_desc'],
            ],
            'installments' => ['FriendlyName' => $t['inst'], 'Type' => 'text', 'Size' => '4', 'Default' => '12', 'Description' => $t['inst_desc']],
            'exclude_ticket' => ['FriendlyName' => $t['ticket'], 'Type' => 'yesno', 'Default' => 'on', 'Description' => $t['ticket_desc']],
            'descriptor' => ['FriendlyName' => $t['desc'], 'Type' => 'text', 'Size' => '13', 'Description' => $t['desc_desc']],
            'doc_field' => ['FriendlyName' => $t['doc'], 'Type' => 'dropdown', 'Options' => $docOptions, 'Default' => 'auto', 'Description' => $t['doc_desc']],
            'link_hours' => ['FriendlyName' => $t['hours'], 'Type' => 'text', 'Size' => '4', 'Default' => '72', 'Description' => $t['hours_desc']],
        ];
    }

    /** @return array<string, string> */
    public static function texts(string $lang): array
    {
        if ($lang === 'en') {
            return [
                'panel' => 'Your integrations', 'test_users' => 'Test accounts',
                'webhook' => 'Webhook URL:',
                'webhook_desc' => 'Where: %s › your application › Webhooks › Configure notifications. Add this URL with the <b>Order (Mercado Pago)</b> event, in Production and in Test mode. The application must use API type = <b>Orders</b> (your application › Edit data › API type).',
                'mode' => 'Mode', 'mode_live' => 'Production', 'mode_test' => 'Test',
                'mode_desc' => 'Test uses only the Test fields below. Default: Production.',
                'token_live' => 'Production · Access Token',
                'token_live_desc' => 'Where: %s › your application › Production credentials › Access Token.',
                'secret_live' => 'Production · Webhook secret',
                'secret_desc' => 'Optional; with it, webhooks without a valid signature are refused (401). Where: %s › your application › Webhooks › Configure notifications (Production) › Secret signature, shown after saving the URL.',
                'token_test' => 'Test · Access Token',
                'token_test_desc' => 'Production Access Token of the <b>test seller</b>, not the application\'s test credentials. Where: %s › test seller › Production credentials.',
                'secret_test' => 'Test · Webhook secret',
                'secret_test_desc' => 'Optional. Where: %s › your application › Webhooks › Configure notifications (Test) › Secret signature.',
                'payer_test' => 'Test · Test buyer e-mail',
                'payer_test_desc' => 'Optional; in Test mode the customer\'s e-mail is never sent. Where: %s › test buyer › e-mail.',
                '3ds_risk' => 'Only on fraud risk', '3ds_always' => 'Always', '3ds_off' => 'Off',
                '3ds_desc' => '3DS authentication with liability shift, sent with each order: "Always" protects more and approves less. Default: only on fraud risk.',
                'inst' => 'Max installments', 'inst_desc' => '1 to 24, sent with each order (limits installments in this checkout). Who pays interest follows your Mercado Pago account (Your business › Costs). Default: 12.',
                'ticket' => 'Exclude boleto', 'ticket_desc' => 'Do not offer boleto in this checkout (sent with each order). Default: on.',
                'desc' => 'Card statement text',
                'desc_desc' => 'statement_descriptor: text on the buyer\'s card statement, up to 13 characters (uppercase, no accents). Sent with each order, instead of your account name. Default (empty): WHMCS company name.',
                'doc' => 'CPF/CNPJ field', 'doc_auto' => 'Automatic (Tax ID, then a field named CPF/CNPJ)', 'doc_tax' => 'WHMCS Tax ID', 'doc_cf' => 'Custom field:',
                'doc_desc' => 'Buyer document, sent only if it is a valid CPF or CNPJ. Where, in WHMCS: Tax ID on the client profile (Setup › Tax › Tax ID) or Setup › Custom Client Fields. Default: automatic.',
                'hours' => 'Link validity (hours)', 'hours_desc' => '1 to 720. After that, a new order is created. Default: 72.',
            ];
        }
        return [
            'panel' => 'Suas integrações', 'test_users' => 'Contas de teste',
            'webhook' => 'URL do webhook:',
            'webhook_desc' => 'Onde: %s › sua aplicação › Webhooks › Configurar notificações. Cadastre esta URL com o evento <b>Order (Mercado Pago)</b>, no modo Produção e no modo Teste. A aplicação deve estar com Tipo de API = <b>Orders</b> (sua aplicação › Editar dados › Tipo de API).',
            'mode' => 'Modo', 'mode_live' => 'Produção', 'mode_test' => 'Teste',
            'mode_desc' => 'Em Teste, valem só os campos de Teste abaixo. Padrão: Produção.',
            'token_live' => 'Produção · Access Token',
            'token_live_desc' => 'Onde: %s › sua aplicação › Credenciais de produção › Access Token.',
            'secret_live' => 'Produção · Secret do webhook',
            'secret_desc' => 'Opcional; com ele, webhook sem assinatura válida é recusado (401). Onde: %s › sua aplicação › Webhooks › Configurar notificações (modo Produção) › Assinatura secreta, que aparece depois de salvar a URL.',
            'token_test' => 'Teste · Access Token',
            'token_test_desc' => 'Access Token de produção do <b>vendedor de teste</b>, não as credenciais de teste da aplicação. Onde: %s › vendedor de teste › Credenciais de produção.',
            'secret_test' => 'Teste · Secret do webhook',
            'secret_test_desc' => 'Opcional. Onde: %s › sua aplicação › Webhooks › Configurar notificações (modo Teste) › Assinatura secreta.',
            'payer_test' => 'Teste · E-mail do comprador de teste',
            'payer_test_desc' => 'Opcional; em Teste, o e-mail do cliente nunca é enviado. Onde: %s › comprador de teste › e-mail.',
            '3ds_risk' => 'Só com risco de fraude', '3ds_always' => 'Sempre', '3ds_off' => 'Desligado',
            '3ds_desc' => 'Autenticação 3DS com liability shift, enviada em cada order: "Sempre" protege mais e aprova menos. Padrão: só com risco de fraude.',
            'inst' => 'Parcelas máximas', 'inst_desc' => 'De 1 a 24, enviado em cada order (limita as parcelas deste checkout). Quem paga os juros segue a sua conta no Mercado Pago (Seu negócio › Custos). Padrão: 12.',
            'ticket' => 'Excluir boleto', 'ticket_desc' => 'Não oferecer boleto neste checkout (enviado em cada order). Padrão: marcado.',
            'desc' => 'Descrição na fatura do cartão',
            'desc_desc' => 'statement_descriptor: texto na fatura do cartão do comprador, até 13 caracteres (maiúsculas, sem acento). Enviado em cada order, no lugar do nome da sua conta. Padrão (vazio): nome da empresa no WHMCS.',
            'doc' => 'Campo do CPF/CNPJ', 'doc_auto' => 'Automático (Tax ID; senão campo com CPF/CNPJ no nome)', 'doc_tax' => 'Tax ID do WHMCS', 'doc_cf' => 'Campo personalizado:',
            'doc_desc' => 'Documento do comprador, só se CPF ou CNPJ válido. Onde, no WHMCS: Tax ID no perfil do cliente (Configurações › Impostos › Tax ID) ou Configurações › Campos personalizados do cliente. Padrão: automático.',
            'hours' => 'Validade do link (horas)', 'hours_desc' => 'De 1 a 720. Depois disso, uma order nova é criada. Padrão: 72.',
        ];
    }

    private static function a(string $href, string $label): string
    {
        return '<a href="' . self::h($href) . '" target="_blank" rel="noopener">' . self::h($label) . '</a>';
    }

    private static function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
