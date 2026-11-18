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

A tela de configuração do gateway explica cada campo em uma linha, com link para o painel do Mercado Pago, e segue o idioma do admin (português ou inglês). No topo fica a URL do webhook, pronta para copiar.

Links do painel: [Suas integrações](https://www.mercadopago.com.br/developers/panel/app) e [Contas de teste](https://www.mercadopago.com.br/developers/panel/app/test-users).

| Campo | O que é | Onde encontrar |
|---|---|---|
| Modo | **Produção** ou **Teste**. Em Teste, valem só os campos de Teste. Padrão: Produção. | — |
| Produção · Access Token | Token da sua aplicação. | Suas integrações › sua aplicação › Credenciais de produção › Access Token |
| Produção · Secret do webhook | Opcional. Com ele, webhook sem assinatura válida recebe 401. | Suas integrações › sua aplicação › Webhooks › Configurar notificações (modo Produção) › Assinatura secreta (aparece depois de salvar a URL) |
| Teste · Access Token | Access Token **de produção do vendedor de teste**, e não as "credenciais de teste" da aplicação (a API de Orders não aceita essas). | Contas de teste › vendedor de teste › Credenciais de produção |
| Teste · Secret do webhook | Opcional, como o de produção. | Suas integrações › sua aplicação › Webhooks › Configurar notificações (modo Teste) › Assinatura secreta |
| Teste · E-mail do comprador de teste | Opcional. Vai como e-mail do comprador só no modo Teste (veja [Modo teste](#modo-teste)). | Contas de teste › comprador de teste |
| URL do webhook (topo da tela) | `https://SEU-WHMCS/modules/gateways/callback/mercadopagopro.php`, com o evento **Order (Mercado Pago)**, nos modos Produção e Teste. A aplicação deve estar com **Tipo de API = Orders**. | Suas integrações › sua aplicação › Webhooks › Configurar notificações; Tipo de API em sua aplicação › Editar dados |
| 3DS | Autenticação 3DS no cartão (`config.online.transaction_security`), com `liability_shift: required`: a responsabilidade por chargeback de fraude passa ao emissor.<br>• **Só com risco de fraude** (padrão): `validation: on_fraud_risk`.<br>• **Sempre**: `validation: always`.<br>• **Desligado**: não envia.<br>**Efeito na aprovação:** cartões ou emissores que não completam o 3DS, e compradores que desistem do desafio, são recusados. "Sempre" protege mais e aprova menos. | Vai em cada order; vale para esta cobrança |
| Parcelas máximas | De 1 a 24 (padrão 12). Limita as parcelas deste checkout. | Vai em cada order. Quem paga os juros segue a sua conta (Seu negócio › Custos) |
| Excluir boleto | Marcado por padrão: o boleto não aparece neste checkout (`not_allowed_types: ticket`). | Vai em cada order |
| Descrição na fatura do cartão | `statement_descriptor`: o texto na fatura do cartão do comprador. Até 13 caracteres, em maiúsculas e sem acento. **Sempre enviado**: se vazio, usa o nome da empresa do WHMCS (normalizado); sem ele, `MERCADOPAGO`. | Vai em cada order, no lugar do nome configurado na sua conta |
| Campo do CPF/CNPJ | De onde vem o documento do comprador. *Automático* (padrão): o Tax ID; se ele não tiver um documento válido, o primeiro campo personalizado de cliente com "CPF", "CNPJ" ou "documento" no nome. Também é possível escolher só o *Tax ID* ou um campo específico. Só vai se for CPF ou CNPJ válido. | No WHMCS: Tax ID no perfil do cliente (Configurações › Impostos) ou Configurações › Campos personalizados do cliente |
| Validade do link (horas) | De 1 a 720 (padrão 72, `expiration_time`). Depois disso, ao abrir a fatura, uma order nova é criada. | — |

Os campos de 3DS, parcelas, boleto e descrição vão em cada order e valem para a cobrança. Configurações gerais da conta no Mercado Pago (como quem paga os juros do parcelamento) continuam valendo.

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

1. Em [Contas de teste](https://www.mercadopago.com.br/developers/panel/app/test-users), crie um **vendedor** e um **comprador** de teste. A API de Orders não aceita as "credenciais de teste" da aplicação: use o **Access Token de produção do vendedor de teste** no campo *Teste · Access Token* e escolha o modo **Teste** no gateway.
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

Com esta versão, a integração alcançou **90/100** na medição do painel (Checkout Pro com API de Orders).

Os pontos que faltam são deliberados:

- **`platform_id`**: não se aplica. É só para plataformas parceiras certificadas pelo Mercado Pago, que integram várias contas de vendedores. Uma loja usando o próprio token não envia.
- **SDK de backend**: não é usado, de propósito. O módulo fala direto com a API REST do Mercado Pago (cURL) para não depender de Composer nem de bibliotecas externas dentro do WHMCS.

Também não são usados:

- **Modo binário**: não é ativado, para não recusar pagamentos que ficariam pendentes (Pix, análise).
- **Logos oficiais**: o botão usa só texto, porque o pacote não inclui a marca do Mercado Pago.

## Testes

```bash
php tests/run.php
```

Os testes rodam sem WHMCS, sem banco e sem rede (com dublês).

## Licença

MIT. Veja [LICENSE](LICENSE).
