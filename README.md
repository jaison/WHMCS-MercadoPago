# Mercado Pago Checkout Pro para WHMCS

Gateway de pagamento do [Mercado Pago](https://www.mercadopago.com.br/developers/pt/docs/checkout-pro-orders/create-order) para o WHMCS 9, no **Checkout Pro com a API de Orders**.

O cliente clica em **Pagar com Mercado Pago** na fatura e paga no ambiente do Mercado Pago com Pix, cartão (parcelado), saldo em conta e, se você quiser, boleto. Ao final, ele volta para a fatura. O pagamento é confirmado no WHMCS automaticamente, no retorno do cliente e pelo webhook, sem baixa em dobro.

**Índice:** [Recursos](#recursos) · [Requisitos](#requisitos) · [Instalação](#instalação) · [Configuração](#configuração) · [Painel do Mercado Pago](#painel-do-mercado-pago) · [Dados enviados](#dados-enviados-ao-mercado-pago) · [Baixa](#baixa) · [Pix e pagamentos pendentes](#pix-e-pagamentos-pendentes) · [Reembolso](#reembolso) · [Modo teste](#modo-teste) · [Colocar em produção](#colocar-em-produção) · [Migrando de outro módulo](#migrando-de-outro-módulo-do-mercado-pago) · [Atualização](#atualização) · [Problemas comuns](#problemas-comuns) · [Remoção](#remoção) · [Medição de qualidade](#medição-de-qualidade) · [Testes](#testes)

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
- Conta do **Mercado Pago Brasil** (mercadopago.com.br). O módulo é só em português e só cobra em **BRL**. Em fatura de outra moeda, o gateway mostra um aviso no lugar do botão.
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

A tela de configuração do gateway explica cada campo em uma linha, com link para o painel do Mercado Pago. No topo fica a URL do webhook, pronta para copiar.

Links do painel: [Suas integrações](https://www.mercadopago.com.br/developers/panel/app), [Contas de teste](https://www.mercadopago.com.br/developers/panel/app/test-users) e [Custos](https://www.mercadopago.com.br/costs-section).

| Campo | O que é | Onde encontrar |
|---|---|---|
| Modo | **Produção** ou **Teste**. Em Teste, valem só os campos de Teste. Padrão: Produção. | — |
| Produção · Access Token | Token de produção da integração. | Suas integrações › clique na integração › Credenciais › Produção › Access Token |
| Produção · Secret do webhook | Opcional. Com ele, webhook sem assinatura válida recebe 401. | Suas integrações › clique na integração › Webhooks › Configurar notificações (modo Produção) › Assinatura secreta (aparece depois de salvar a URL) |
| Teste · Access Token | Token de teste da integração. | Suas integrações › clique na integração › Credenciais › Teste › Access Token |
| Teste · Secret do webhook | Opcional, como o de produção. | Suas integrações › clique na integração › Webhooks › Configurar notificações (modo Teste) › Assinatura secreta |
| Teste · E-mail do comprador de teste | Opcional. Vai como e-mail do comprador só no modo Teste (veja [Modo teste](#modo-teste)). | [Contas de teste](https://www.mercadopago.com.br/developers/panel/app/test-users) › comprador de teste |
| URL do webhook (topo da tela) | `https://SEU-WHMCS/modules/gateways/callback/mercadopagopro.php`, com o evento **Order (Mercado Pago)**, nos modos Produção e Teste. A integração deve estar com **Tipo de API = Orders**. | Suas integrações › clique na integração › Webhooks › Configurar notificações; Tipo de API em clique na integração › Editar dados › Tipo de API |
| 3DS | Autenticação 3DS no cartão (`config.online.transaction_security`), com `liability_shift: required`: a responsabilidade por chargeback de fraude passa ao emissor.<br>• **Só com risco de fraude** (padrão): `validation: on_fraud_risk`.<br>• **Sempre**: `validation: always`.<br>• **Desligado**: não envia.<br>**Efeito na aprovação:** cartões ou emissores que não completam o 3DS, e compradores que desistem do desafio, são recusados. "Sempre" protege mais e aprova menos. | Vai em cada order; vale para esta cobrança |
| Parcelas máximas | De 1 a 24 (padrão 12). Limita as parcelas deste checkout. | Vai em cada order. Juros e parcelamento sem juros seguem a sua conta, em [Custos](https://www.mercadopago.com.br/costs-section) |
| Excluir boleto | Marcado por padrão: o boleto não aparece neste checkout (`not_allowed_types: ticket`). | Vai em cada order |
| Descrição na fatura do cartão | `statement_descriptor`: o texto na fatura do cartão do comprador. Até 13 caracteres, em maiúsculas e sem acento. **Sempre enviado**: se vazio, usa o nome da empresa do WHMCS (normalizado); sem ele, `MERCADOPAGO`. | Vai em cada order, no lugar do nome configurado na sua conta |
| Campo do CPF/CNPJ | De onde vem o documento do comprador. *Automático* (padrão): o Tax ID; se ele não tiver um documento válido, o primeiro campo personalizado de cliente com "CPF", "CNPJ" ou "documento" no nome. Também é possível escolher só o *Tax ID* ou um campo específico. Só vai se for CPF ou CNPJ válido. | No WHMCS: Tax ID no perfil do cliente (Configurações › Impostos) ou Configurações › Campos personalizados do cliente |
| Validade do link (horas) | De 1 a 720 (padrão 72, `expiration_time`). Depois disso, ao abrir a fatura, uma order nova é criada. | — |

Os campos de 3DS, parcelas, boleto e descrição vão em cada order e valem para a cobrança. Configurações gerais da conta no Mercado Pago (como quem paga os juros do parcelamento) continuam valendo.

## Painel do Mercado Pago

1. Em [Suas integrações](https://www.mercadopago.com.br/developers/panel/app) › clique na integração › Editar dados, confira **Tipo de API = Orders** e o produto **Checkout Pro**.
2. Em Suas integrações › clique na integração › Webhooks › Configurar notificações, nos modos **Teste** e **Produção**:
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

## Pix e pagamentos pendentes

- A volta automática para a fatura (`auto_return`) só acontece com pagamento **aprovado**. Quando o pagamento é recusado ou fica pendente, o cliente volta pelo botão "Voltar" do Mercado Pago, e a fatura mostra o aviso de falha, ou nenhum aviso no caso de pendente.
- Pix, boleto e pagamentos em análise ficam pendentes no início. Eles são baixados pelo **webhook** quando o pagamento é concluído, mesmo que o cliente já tenha fechado a página. Por isso o webhook do evento Order é **essencial para o Pix**. Sem ele, só o retorno do cliente com pagamento aprovado gera baixa.

## Reembolso

Pelo botão de reembolso da fatura no WHMCS, com o gateway Mercado Pago:

- **Transação de order (`ORD…`)**: `POST /v1/orders/{id}/refund`.
  - Valor igual ao pago: reembolso total, sem corpo.
  - Valor menor: reembolso parcial do pagamento da order.
  - Valor maior que o pago: recusado sem chamar a API.
- **Transação numérica (pagamento da 1.x)**: `POST /v1/payments/{id}/refunds`.

Os dois usam `X-Idempotency-Key`. O Mercado Pago aceita reembolsos em até 180 dias da aprovação.

## Modo teste

1. Em Suas integrações › clique na integração › Credenciais › **Teste**, copie o Access Token para o campo *Teste · Access Token* e escolha o modo **Teste** no gateway.
2. Cadastre o webhook no modo **Teste** do painel (evento Order).
3. Abra a fatura e pague numa **janela anônima**, sem estar logado no Mercado Pago, ou logado como um **comprador de teste** criado em [Contas de teste](https://www.mercadopago.com.br/developers/panel/app/test-users). Use um [cartão de teste](https://www.mercadopago.com.br/developers/pt/docs/checkout-pro-orders/integration-test/test-purchase-with-card). Por exemplo: Mastercard 5480 8328 0103 3311, CVV 123, validade 11/30, CPF 12345678909.
4. O **nome do titular** define o resultado:
   - `APRO`: aprovado;
   - `OTHE`: recusado;
   - `CONT`: pendente.

**E-mail do comprador de teste.** No modo Teste, o e-mail do cliente do WHMCS **nunca** é enviado. Se o vendedor é de teste e o e-mail é de uma pessoa real, o Mercado Pago recusa o pagamento com *"Uma das partes com as quais você está tentando efetuar o pagamento é de teste"*. Se você usa uma conta de comprador de teste, informe o e-mail dela nesse campo; ele vai como e-mail do comprador só no modo Teste.

**`live_mode`.** Pagamentos de teste podem chegar com `live_mode: true`. O gateway não usa esse campo para decidir a baixa: a garantia de modo é o token usado na consulta. O `live_mode` aparece no Log de Gateways só como informação.

## Colocar em produção

1. Em Suas integrações › clique na integração › Editar dados, confira **Tipo de API = Orders**.
2. Copie as credenciais de **Produção** (Credenciais › Produção › Access Token) para *Produção · Access Token*.
3. Em Webhooks › Configurar notificações, no modo **Produção**, cadastre a URL do topo da tela do gateway com o evento **Order (Mercado Pago)**. Salve e copie a assinatura secreta para *Produção · Secret do webhook*.
4. No gateway, escolha **Modo = Produção** e salve.
5. Faça um **pagamento real pequeno** numa fatura sua e confira:
   - a volta para a fatura;
   - a fatura paga, com uma transação `ORD…`;
   - a taxa.
   Se quiser, reembolse esse pagamento pelo WHMCS em seguida.
6. Em **Faturamento › Log de Gateways**, confira a entrada "Successful" desse pagamento e veja se não há "Erro ao criar a order" nem "Assinatura inválida".

## Migrando de outro módulo do Mercado Pago

1. O `mercadopagopro` **convive** com outros módulos do Mercado Pago. Instale e ative o novo sem desativar o antigo.
2. Teste o novo numa fatura (veja [Colocar em produção](#colocar-em-produção)).
3. Faturas em aberto que estão com o gateway antigo podem passar para o novo:
   - **pelo admin**: abra a fatura, troque a *Forma de pagamento* e salve;
   - **pelo cliente**: na fatura, ele escolhe outra forma de pagamento, se o WHMCS estiver configurado para permitir isso (opção de troca da forma de pagamento em Configurações gerais › Faturas).
4. Desative o módulo antigo. Ao desativar um gateway, o WHMCS pede para escolher o gateway que vai substituí-lo nos serviços e nas faturas que o usavam. Escolha o **Mercado Pago Checkout Pro**.
5. **Links já gerados pelo módulo antigo** não são reconhecidos por este módulo. Um pagamento feito por um desses links depois da troca não gera baixa automática aqui e precisa de baixa manual. Para evitar isso, mantenha o módulo antigo ativo (oculto para novas faturas, se preferir) até os links antigos expirarem. Só os links da versão 1.x **deste** módulo continuam reconhecidos.

## Atualização

- Copie os arquivos por cima, ou rode `./deploy.sh`. Não há passo de banco de dados.
- A tabela `mod_mercadopagopro` é criada sozinha no primeiro uso.
- Quando uma versão muda o formato do pedido (versão do fingerprint), as orders guardadas são refeitas na próxima visualização de cada fatura. O link antigo deixa de ser oferecido.

## Problemas comuns

| Sintoma | Causa provável | Solução |
|---|---|---|
| A fatura mostra "Não foi possível gerar o link de pagamento…" | O Mercado Pago recusou a criação da order. | Em Faturamento › Log de Gateways, abra a entrada "Erro ao criar a order" e leia o campo **erro**, que traz o código e o campo recusado. |
| "Uma das partes com as quais você está tentando efetuar o pagamento é de teste" | Vendedor de teste com comprador real, ou o contrário. | Em Teste, pague em janela anônima ou logado como comprador de teste, e use o campo *Teste · E-mail do comprador de teste*. |
| A fatura não é baixada | Webhook sem o evento **Order**, Tipo de API diferente de **Orders**, ou URL errada. | Confira o [Painel do Mercado Pago](#painel-do-mercado-pago). O retorno do cliente com pagamento aprovado dá baixa mesmo sem webhook, mas Pix e pendentes dependem dele. |
| Webhook recebe 401 (Log de Gateways: "Assinatura inválida") | Secret do modo errado (o de Produção com o gateway em Teste, ou o contrário). | Copie a assinatura secreta do modo certo para o campo de Secret do mesmo modo. |
| Cartões recusados pelo 3DS | O 3DS exigido não foi completado pelo emissor ou pelo comprador. | Use "Só com risco de fraude" ou "Desligado" no seletor **3DS**. |
| Aviso "O Mercado Pago só aceita pagamentos em reais (BRL)" | Fatura em outra moeda. | O módulo só cobra em BRL; use outro gateway para essas faturas. |

## Remoção

1. Desative o gateway em Configurações › Pagamentos › Gateways de pagamento. O WHMCS pede um gateway substituto para as faturas e os serviços.
2. Apague os arquivos `modules/gateways/mercadopagopro.php`, `modules/gateways/mercadopagopro/` e `modules/gateways/callback/mercadopagopro.php`.
3. Desativar o gateway **não** apaga a tabela `mod_mercadopagopro`, que guarda os links por fatura. Para removê-la, rode no banco do WHMCS (faça backup antes):
   ```sql
   DROP TABLE mod_mercadopagopro;
   ```
   As transações já registradas nas faturas continuam no WHMCS.

## Medição de qualidade

O painel do Mercado Pago (*Detalhes da aplicação › Qualidade da integração*) avalia um pagamento real. Segundo a [página de qualidade da documentação](https://www.mercadopago.com.br/developers/pt/docs/checkout-pro-orders/integration-quality), a pontuação mínima é 73. O módulo cobre:

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
