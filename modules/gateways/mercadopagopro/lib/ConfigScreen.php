<?php

namespace MercadoPagoPro;

/**
 * Tela de configuração do gateway (_config): rótulos curtos, descrição de uma linha, padrão e
 * link para a página certa do painel do Mercado Pago. Só pt-BR (módulo para o Mercado Pago Brasil).
 */
final class ConfigScreen
{
    public const PANEL = 'https://www.mercadopago.com.br/developers/panel/app';
    public const TEST_USERS = 'https://www.mercadopago.com.br/developers/panel/app/test-users';
    public const COSTS = 'https://www.mercadopago.com.br/costs-section';

    /**
     * @param array<int, string> $docFields campos personalizados de cliente [id => nome]
     */
    public static function fields(string $systemUrl, array $docFields): array
    {
        $t = self::texts();
        $url = Module::callbackUrl($systemUrl !== '' ? $systemUrl : 'https://SEU-WHMCS');
        $panel = self::a(self::PANEL, $t['panel']);
        $testUsers = self::a(self::TEST_USERS, $t['test_users']);
        $costs = self::a(self::COSTS, $t['costs']);

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
            'token_test' => ['FriendlyName' => $t['token_test'], 'Type' => 'password', 'Size' => '80', 'Description' => sprintf($t['token_test_desc'], $panel)],
            'secret_test' => ['FriendlyName' => $t['secret_test'], 'Type' => 'password', 'Size' => '80', 'Description' => sprintf($t['secret_test_desc'], $panel)],
            'test_payer_email' => ['FriendlyName' => $t['payer_test'], 'Type' => 'text', 'Size' => '50', 'Description' => sprintf($t['payer_test_desc'], $testUsers)],
            'three_ds' => [
                'FriendlyName' => '3DS',
                'Type' => 'dropdown',
                'Options' => ['on_fraud_risk' => $t['3ds_risk'], 'always' => $t['3ds_always'], 'off' => $t['3ds_off']],
                'Default' => 'on_fraud_risk',
                'Description' => $t['3ds_desc'],
            ],
            'installments' => ['FriendlyName' => $t['inst'], 'Type' => 'text', 'Size' => '4', 'Default' => '12', 'Description' => sprintf($t['inst_desc'], $costs)],
            'exclude_ticket' => ['FriendlyName' => $t['ticket'], 'Type' => 'yesno', 'Default' => 'on', 'Description' => $t['ticket_desc']],
            'descriptor' => ['FriendlyName' => $t['desc'], 'Type' => 'text', 'Size' => '13', 'Description' => $t['desc_desc']],
            'doc_field' => ['FriendlyName' => $t['doc'], 'Type' => 'dropdown', 'Options' => $docOptions, 'Default' => 'auto', 'Description' => $t['doc_desc']],
            'link_hours' => ['FriendlyName' => $t['hours'], 'Type' => 'text', 'Size' => '4', 'Default' => '72', 'Description' => $t['hours_desc']],
        ];
    }

    /** @return array<string, string> */
    public static function texts(): array
    {
        return [
            'panel' => 'Suas integrações', 'test_users' => 'Contas de teste', 'costs' => 'Custos',
            'webhook' => 'URL do webhook:',
            'webhook_desc' => 'Onde: %s › clique na integração › Webhooks › Configurar notificações. Cadastre esta URL com o evento <b>Order (Mercado Pago)</b>, no modo Produção e no modo Teste. A integração deve estar com Tipo de API = <b>Orders</b> (clique na integração › Editar dados › Tipo de API).',
            'mode' => 'Modo', 'mode_live' => 'Produção', 'mode_test' => 'Teste',
            'mode_desc' => 'Em Teste, valem só os campos de Teste abaixo. Padrão: Produção.',
            'token_live' => 'Produção · Access Token',
            'token_live_desc' => 'Onde: %s › clique na integração › Credenciais › Produção › Access Token.',
            'secret_live' => 'Produção · Secret do webhook',
            'secret_desc' => 'Opcional; com ele, webhook sem assinatura válida é recusado (401). Onde: %s › clique na integração › Webhooks › Configurar notificações (modo Produção) › Assinatura secreta, que aparece depois de salvar a URL.',
            'token_test' => 'Teste · Access Token',
            'token_test_desc' => 'Onde: %s › clique na integração › Credenciais › Teste › Access Token.',
            'secret_test' => 'Teste · Secret do webhook',
            'secret_test_desc' => 'Opcional. Onde: %s › clique na integração › Webhooks › Configurar notificações (modo Teste) › Assinatura secreta.',
            'payer_test' => 'Teste · E-mail do comprador de teste',
            'payer_test_desc' => 'Opcional; em Teste, o e-mail do cliente nunca é enviado. Onde: %s › comprador de teste › e-mail.',
            '3ds_risk' => 'Só com risco de fraude', '3ds_always' => 'Sempre', '3ds_off' => 'Desligado',
            '3ds_desc' => 'Autenticação 3DS com liability shift, enviada em cada order: "Sempre" protege mais e aprova menos. Padrão: só com risco de fraude.',
            'inst' => 'Parcelas máximas', 'inst_desc' => 'De 1 a 24, enviado em cada order (limita as parcelas deste checkout). Juros e parcelamento sem juros seguem a sua conta, em %s. Padrão: 12.',
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
