# Mercado Pago Checkout Pro para WHMCS

Gateway de pagamento do [Mercado Pago](https://www.mercadopago.com.br/developers/pt/docs/checkout-pro/overview) (Checkout Pro) para o WHMCS 9.

O cliente clica em **Pagar com Mercado Pago** na fatura e paga no ambiente do Mercado Pago com Pix, cartão (parcelado), saldo em conta e, se você quiser, boleto. Ao final, ele volta para a fatura. O pagamento é confirmado no WHMCS automaticamente, pelo webhook ou no próprio retorno do cliente.

## Recursos

- Preferência criada na API atual (`api.mercadopago.com`) com `Authorization: Bearer`.
- Um link por fatura, reaproveitado enquanto o valor não muda e o link não está perto de expirar.
- Volta automática para a fatura após o pagamento aprovado (`auto_return`).
- Baixa automática pelo webhook (formato novo e IPN), com validação opcional da assinatura `x-signature`.
- Valor pago e taxas do Mercado Pago registrados na transação do WHMCS.
- Reembolso total ou parcial pelo botão de reembolso do WHMCS.
- Modos Produção e Teste com credenciais separadas.
- Nenhum dado do pagador e nenhum token no Log de Gateways.

## Requisitos

- WHMCS 9.x e PHP 8.1 ou mais novo, com cURL.
- Faturas em **BRL**. O Checkout Pro do Brasil só recebe em reais; em outra moeda o gateway mostra um aviso no lugar do botão.
- Uma aplicação criada em [Suas integrações](https://www.mercadopago.com.br/developers/panel/app) do Mercado Pago.

## Instalação

Copie para a raiz do WHMCS:

```
modules/gateways/mercadopagopro.php
modules/gateways/mercadopagopro/
modules/gateways/callback/mercadopagopro.php
```

Ou, com acesso ao servidor, use o script (ele faz backup antes de copiar):

```bash
./deploy.sh --dry-run                       # mostra o que vai mudar
WHMCS=/caminho/do/whmcs ./deploy.sh         # copia (pede confirmação)
```

Depois ative em **Configurações › Pagamentos › Gateways de pagamento › Todos os gateways › Mercado Pago Checkout Pro**.

O id do gateway é `mercadopagopro`. Ele convive com outros módulos do Mercado Pago que já estejam instalados.

## Configuração

| Campo | O que é |
|---|---|
| Modo | **Produção** ou **Teste**. Cada modo usa as próprias credenciais. |
| Access Token de produção / de teste | Em *Suas integrações › sua aplicação › Credenciais*. |
| Secret do webhook (produção / teste) | Opcional. É a "assinatura secreta" mostrada na tela de Webhooks. Se preenchido, notificações de webhook sem assinatura válida recebem 401. |
| Parcelas máximas | De 1 a 24 (padrão 12). |
| Descrição na fatura do cartão | Opcional (`statement_descriptor`), até 22 caracteres. |
| Excluir boleto | Marcado por padrão: o boleto não aparece no Mercado Pago. |
| Validade do link (horas) | Padrão 72. Depois disso, ao abrir a fatura, um link novo é criado. |

## Webhook

Cada link de pagamento já informa ao Mercado Pago para onde mandar as notificações (`notification_url`). Mesmo assim, recomendamos cadastrar a URL também no painel, porque o cadastro permite usar a assinatura secreta.

1. Em *Suas integrações › sua aplicação › Webhooks*, informe a URL mostrada na configuração do gateway:
   `https://SEU-WHMCS/modules/gateways/callback/mercadopagopro.php`
2. Marque o evento **Pagamentos**.
3. Copie a **assinatura secreta** para o campo *Secret do webhook* do modo correspondente.

O callback responde:

- **200**: notificação processada, ou ignorada de propósito (outros tópicos como `merchant_order`, pagamento pendente ou recusado, transação já registrada);
- **401**: assinatura inválida, quando o secret está configurado;
- **503**: falha temporária na API do Mercado Pago, para ele tentar de novo.

Notificações IPN (`?topic=payment&id=…`) não têm assinatura. Elas são aceitas, e o pagamento é sempre consultado na API antes de qualquer baixa.

## Modo teste

1. Em *Suas integrações › sua aplicação › Contas de teste*, crie um vendedor e um comprador de teste.
2. Use o **Access Token de teste** e escolha o modo **Teste**.
3. Pague a fatura logado como o **comprador de teste**, com os [cartões de teste](https://www.mercadopago.com.br/developers/pt/docs/checkout-pro/additional-content/your-integrations/test/cards).

Pagamentos de teste (`live_mode: false`) com o gateway em Produção, e o inverso, são registrados no Log de Gateways e ignorados.

## Como a baixa funciona

1. Chega uma notificação ou o cliente volta para a loja.
2. O gateway consulta `GET /v1/payments/{id}` com o token do modo.
3. A baixa só acontece se o status for `approved`, a moeda for `BRL` e o `external_reference` (número da fatura) existir.
4. Pagamentos já registrados não são registrados de novo (`checkCbTransID`).
5. O valor é o `transaction_amount` e a taxa é a soma de `fee_details`.

Estornos e contestações só aparecem no Log de Gateways; o ajuste no WHMCS é manual.

## Testes

```bash
php tests/run.php
```

Os testes rodam sem WHMCS, sem banco e sem rede (com dublês).

## Licença

MIT. Veja [LICENSE](LICENSE).
