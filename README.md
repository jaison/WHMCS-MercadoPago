# Mercado Pago Checkout Pro para WHMCS

Gateway de pagamento do [Mercado Pago](https://www.mercadopago.com.br/developers/pt/docs/checkout-pro-orders/create-order) para o WHMCS 9, no **Checkout Pro com a API de Orders**.

O cliente clica em **Pagar com Mercado Pago** na fatura e paga no ambiente do Mercado Pago com Pix, cartão (parcelado), saldo em conta e, se você quiser, boleto. Ao final, ele volta para a fatura. O pagamento é confirmado no WHMCS automaticamente, no retorno do cliente e pelo webhook, sem baixa em dobro.

## Recursos

- Order criada em `POST /v1/orders`, com `Authorization: Bearer` e `X-Idempotency-Key`.
- Uma order por fatura, reaproveitada enquanto o conteúdo não muda e o link não está perto de expirar.
- Volta automática para a fatura após o pagamento aprovado.
- Baixa automática pelo retorno do cliente e pelo webhook (evento Order), com validação opcional da assinatura `x-signature`.
- Valor pago e taxa do Mercado Pago registrados na transação do WHMCS.
- Reembolso total ou parcial pelo botão de reembolso do WHMCS.
- Modos Produção e Teste com credenciais separadas.
- Dados completos do comprador e do item, como pede a medição de qualidade do Mercado Pago. Esses dados vão só ao Mercado Pago (veja [Dados enviados](#dados-enviados-ao-mercado-pago)).
- Compatível com pagamentos de links da versão 1.x (Preferências).

## Requisitos

- WHMCS 9.x e PHP 8.1 ou mais novo, com cURL.
- Faturas em **BRL**. Em outra moeda, o gateway mostra um aviso no lugar do botão.
- Uma aplicação em [Suas integrações](https://www.mercadopago.com.br/developers/panel/app), com **Tipo de API = API de Orders** e produto **Checkout Pro**.

## Instalação

Copie para a raiz do WHMCS:

```
modules/gateways/mercadopagopro.php
modules/gateways/mercadopagopro/
modules/gateways/callback/mercadopagopro.php
```

Ou, com acesso ao servidor, use o script, que faz backup antes de copiar:

```bash
./deploy.sh --dry-run                       # mostra o que vai mudar
WHMCS=/caminho/do/whmcs ./deploy.sh         # copia (pede confirmação)
```

Depois ative em **Configurações › Pagamentos › Gateways de pagamento › Todos os gateways › Mercado Pago Checkout Pro**.

O id do gateway é `mercadopagopro`. Ele convive com outros módulos do Mercado Pago já instalados.

## Configuração

| Campo | O que é |
|---|---|
| Modo | **Produção** ou **Teste**. Cada modo usa as próprias credenciais e o próprio secret. |
| Access Token de produção / de teste | Em *Suas integrações › sua aplicação › Credenciais*. |
| Secret do webhook (produção / teste) | Opcional. É a assinatura secreta da tela de Webhooks. Se preenchido, webhooks sem assinatura válida recebem 401. |
| E-mail do comprador de teste | Opcional, só no modo Teste. Veja [Modo teste](#modo-teste). |
| Campo do CPF/CNPJ | De onde vem o documento do comprador. *Automático*: o Tax ID; se ele não tiver um documento válido, o primeiro campo personalizado de cliente com "CPF", "CNPJ" ou "documento" no nome. Também é possível escolher só o *Tax ID* ou um campo personalizado específico. O documento só é enviado se for um CPF ou CNPJ válido. |
| Parcelas máximas | De 1 a 24 (padrão 12). |
| Descrição na fatura do cartão | `statement_descriptor`: o texto que aparece na fatura do cartão do comprador. Até 13 caracteres, em maiúsculas e sem acento. **Sempre enviado**: se o campo estiver vazio, usa o nome da empresa do WHMCS (normalizado); sem ele, `MERCADOPAGO`. |
| 3DS | Autenticação 3DS no cartão (`config.online.transaction_security`), com `liability_shift: required`: a responsabilidade por chargeback de fraude passa ao emissor.<br>• **Só com risco de fraude** (padrão): `validation: on_fraud_risk`. O desafio 3DS aparece só quando o Mercado Pago vê risco.<br>• **Sempre**: `validation: always`. Todo pagamento com cartão passa por 3DS.<br>• **Desligado**: não envia `transaction_security`.<br>**Efeito na aprovação:** cartões ou emissores que não completam o 3DS, e compradores que desistem do desafio, são recusados. "Sempre" protege mais e aprova menos; "Só com risco de fraude" é o equilíbrio. |
| Excluir boleto | Marcado por padrão: o boleto não aparece no Mercado Pago (`not_allowed_types: ticket`). |
| Validade do link (horas) | Padrão 72 (`expiration_time`). Depois disso, ao abrir a fatura, uma order nova é criada. |

## Painel do Mercado Pago

1. Em *Suas integrações › sua aplicação*, confira **Tipo de API = API de Orders** e o produto **Checkout Pro**.
2. Em *Webhooks › Configuração*, nos modos **Teste** e **Produção**:
   - URL: a mostrada no topo da configuração do gateway, `https://SEU-WHMCS/modules/gateways/callback/mercadopagopro.php`;
   - evento: **Order (Mercado Pago)**;
   - copie a **assinatura secreta** para o campo *Secret do webhook* do modo correspondente.

A API de Orders não aceita uma URL de notificação por pedido, então o cadastro no painel é obrigatório para a baixa pelo webhook.

O callback responde:

- **200**: notificação processada ou ignorada de propósito (outros tópicos, order não paga, transação já registrada);
- **401**: assinatura inválida, com o secret configurado;
- **503**: falha temporária na API do Mercado Pago, para ele tentar de novo.

## Dados enviados ao Mercado Pago

Tudo vem do cadastro e da fatura no WHMCS. Cada campo só vai se existir e for válido.

**Comprador (`payer`)**: e-mail, nome (`first_name`), sobrenome (`last_name`), telefone (DDD e número, só Brasil), CPF ou CNPJ (`identification`, só se válido) e endereço (CEP, rua, número, bairro e cidade, só Brasil). A API exige o e-mail quando há `payer`. Sem e-mail (modo Teste sem comprador de teste), o `payer` não é enviado.

**Item (`items[0]`)**: código `INV-<fatura>`, título "Fatura #N", descrição com as linhas da fatura (até 256 caracteres), categoria `services`, quantidade 1 e valor.

**Histórico de compras (`additional_info`)**: data de cadastro do cliente (`payer.registration_date`), se é a primeira compra (`payer.is_first_purchase_online`: nenhuma fatura paga antes desta) e a data da última fatura paga (`payer.last_purchase`).

**Pedido**: número da fatura (`external_reference`), valor, validade, descrição na fatura do cartão, parcelas, exclusão do boleto, 3DS e URLs de retorno.

**LGPD**: os dados pessoais vão **somente ao Mercado Pago**, que precisa deles para analisar o risco do pagamento. Eles **nunca** são gravados no Log de Gateways, em tabelas do módulo ou em arquivos. Mensagens de erro da API passam por um filtro que mascara e-mails e números longos antes de ir ao log. A tabela `mod_mercadopagopro` guarda só fatura, chave, valor, id da order, link e validade.

## Baixa

1. A baixa acontece por dois caminhos:
   - **retorno do cliente**: a URL de retorno leva `?return=1&inv=N`, e o gateway consulta a order (pelo `order_id` recebido ou pela order guardada da fatura);
   - **webhook** do evento Order.
2. A order é sempre consultada em `GET /v1/orders/{id}`, com o token do modo escolhido.
3. Há baixa só se a order estiver `processed` / `accredited`, em `BRL`, com o número de fatura (`external_reference`).
4. **Valor**: a soma dos pagamentos aprovados da order. **Taxa**: buscada no pagamento correspondente (`/v1/payments/{reference_id}`). Se não estiver disponível, fica 0.
5. **Id da transação no WHMCS**: o id da order (`ORD…`), que é o que o reembolso usa. O id do pagamento fica no Log de Gateways.
6. **Idempotência**: `checkCbTransID` impede registrar a mesma order duas vezes, mesmo com o retorno e o webhook chegando juntos. Nesse caso, o cliente é redirecionado normalmente para a fatura.
7. Estornos, contestações e reembolsos feitos fora do WHMCS só aparecem no Log de Gateways; o ajuste no WHMCS é manual.

**Compatibilidade com a 1.x**: notificações `payment` (webhook e IPN) de links criados pela versão 1.x (Preferências) continuam dando baixa pelo pagamento, com o id numérico como transação. Quando a fatura já tem uma order, a notificação `payment` é ignorada, porque a baixa sai pela order. Assim o mesmo dinheiro não é registrado duas vezes.

## Reembolso

Pelo botão de reembolso da fatura no WHMCS, com o gateway Mercado Pago:

- **Transação de order (`ORD…`)**: `POST /v1/orders/{id}/refund`.
  - Valor igual ao pago: reembolso total, sem corpo.
  - Valor menor: reembolso parcial do pagamento da order.
  - Valor maior que o pago: recusado sem chamar a API.
- **Transação numérica (pagamento da 1.x)**: `POST /v1/payments/{id}/refunds`.

Os dois usam `X-Idempotency-Key`. O Mercado Pago aceita reembolsos em até 180 dias da aprovação.

## Modo teste

1. Em *Suas integrações › sua aplicação*, use as **credenciais de teste** e escolha o modo **Teste** no gateway.
   - A referência da API de Orders também menciona usar usuários de teste com credenciais de produção. Se a criação da order falhar com 401 no modo Teste, siga essa orientação.
2. Cadastre o webhook no modo **Teste** do painel (evento Order).
3. Abra a fatura e pague numa **janela anônima**, sem estar logado no Mercado Pago, com um [cartão de teste](https://www.mercadopago.com.br/developers/pt/docs/checkout-pro-orders/integration-test/test-purchase-with-card). Por exemplo: Mastercard 5480 8328 0103 3311, CVV 123, validade 11/30, CPF 12345678909.
4. O **nome do titular** define o resultado:
   - `APRO`: aprovado;
   - `OTHE`: recusado;
   - `CONT`: pendente.

**E-mail do comprador de teste.** No modo Teste, o e-mail do cliente do WHMCS **nunca** é enviado. Se o vendedor é de teste e o e-mail é de uma pessoa real, o Mercado Pago recusa o pagamento com *"Uma das partes com as quais você está tentando efetuar o pagamento é de teste"*. Se você usa uma conta de comprador de teste, informe o e-mail dela nesse campo; ele vai como e-mail do comprador só no modo Teste.

**`live_mode`.** Pagamentos de teste podem chegar com `live_mode: true`. O gateway não usa esse campo para decidir a baixa: a garantia de modo é o token usado na consulta. O `live_mode` aparece no Log de Gateways só como informação.

## Medição de qualidade

O painel do Mercado Pago (*Detalhes da aplicação › Qualidade da integração*) avalia um pagamento real. O mínimo exigido é 73 pontos. O módulo cobre:

- **Obrigatórios**:
  - notificações (webhook do evento Order);
  - referência externa (número da fatura);
  - URLs de retorno e `auto_return`;
  - descrição na fatura do cartão;
  - 3DS / liability shift ("Só com risco de fraude" por padrão).
- **Recomendados**:
  - dados do comprador (e-mail, nome, sobrenome, telefone, documento, endereço);
  - dados do item (código, título, descrição, categoria, quantidade, preço);
  - histórico de compras (cadastro, primeira compra, última compra).

Não se aplicam:

- **`platform_id` / `integration_data`**: são para plataformas parceiras certificadas pelo Mercado Pago, que integram várias contas. Uma loja usando o próprio token não envia.
- **Modo binário**: não é ativado, para não recusar pagamentos que ficariam pendentes (Pix, análise).
- **Logos oficiais**: o botão usa só texto, porque o pacote não inclui a marca do Mercado Pago.

## Testes

```bash
php tests/run.php
```

Os testes rodam sem WHMCS, sem banco e sem rede (com dublês).

## Licença

MIT. Veja [LICENSE](LICENSE).
