# Changelog

## 2.0.2

- Caminhos da tela de configuração e do README conferidos no painel:
  - Access Tokens em Suas integrações › clique na integração › Credenciais › Produção / Teste;
  - o token de teste é a credencial de **teste** da própria integração, que funciona com a API de Orders;
  - Webhooks e Tipo de API seguem o mesmo padrão de caminho.
- Contas de teste citadas só no e-mail do comprador de teste e no passo de pagar em teste.
- Parcelas máximas: link para [Custos](https://www.mercadopago.com.br/costs-section), onde ficam juros e parcelamento sem juros.

## 2.0.1

- Tela de configuração reescrita:
  - rótulo curto e descrição de uma linha em cada campo, com o padrão e o caminho até a configuração no painel (link com nova aba quando há URL estável);
  - campos em blocos (Produção, Teste);
  - URL do webhook sempre visível e pronta para copiar, com o evento Order e o Tipo de API Orders;
  - textos em português ou inglês, conforme o idioma do admin.
- Teste · Access Token: deixa claro que é o token de produção do vendedor de teste (Contas de teste).
- README: tabela "Campo | O que é | Onde encontrar".

## 2.0.0

Migração para o **Checkout Pro com a API de Orders**. A aplicação no painel do Mercado Pago deve estar como "API de Orders", com o webhook do evento **Order (Mercado Pago)** cadastrado.

- Link de pagamento criado como order (`POST /v1/orders`: `type online`, `processing_mode manual`, valores em texto). Redireciona para o `checkout_url`.
- `payer` no formato de Orders (`first_name`, `last_name`, telefone, CPF/CNPJ, endereço com bairro e cidade) e `additional_info` com cadastro, primeira compra e última compra.
- `config`:
  - `statement_descriptor` (sempre preenchido);
  - URLs de retorno e `auto_return`;
  - parcelas e exclusão do boleto (`not_allowed_types`);
  - novo seletor "3DS": Desligado, Só com risco de fraude (padrão) ou Sempre. Envia `validation` (`on_fraud_risk` ou `always`) com `liability_shift: required`.
- Baixa:
  - pela order (`processed`/`accredited`): valor = soma dos pagamentos aprovados;
  - taxa buscada no pagamento de referência;
  - transid = id da order;
  - funciona pelo retorno do cliente e pelo webhook `order` (id alfanumérico, em minúsculas no manifesto da assinatura).
- Reembolso de order: total (sem corpo) ou parcial (pagamento da order) em `POST /v1/orders/{id}/refund`. Transações numéricas da 1.x continuam reembolsadas por `/v1/payments`.
- Compatibilidade:
  - notificações `payment` de links da 1.x continuam dando baixa;
  - quando a fatura tem order, elas são ignoradas, para não registrar o pagamento duas vezes.
- Erros da API de Orders (`errors[]` com code, message e details) vão legíveis ao Log de Gateways, com o mesmo mascaramento de dados pessoais.
- Links guardados das versões anteriores são refeitos como order.

## 1.1.0

Mais dados na preferência, para a medição de qualidade da integração do Mercado Pago:

- `payer` completo, com o que o WHMCS já tem: nome, sobrenome, e-mail (em Teste, só o do comprador de teste), telefone (Brasil), CPF/CNPJ (só se válido), endereço (CEP, rua e número) e data de cadastro.
- Novo campo "Campo do CPF/CNPJ": automático (Tax ID, senão campo personalizado com CPF/CNPJ no nome), só o Tax ID, ou um campo personalizado escolhido.
- `items[0]`: código `INV-<fatura>`, categoria `services` e descrição com as linhas da fatura (até 256 caracteres).
- `statement_descriptor` sempre preenchido, até 13 caracteres (limite do Mercado Pago no Brasil), em maiúsculas e sem acento. Usa o campo configurado; senão o nome da empresa do WHMCS.
- Mensagens de erro da API passam por um filtro que mascara e-mails e números longos antes de ir ao Log de Gateways.
- O link guardado passa a depender de todo o conteúdo da preferência (menos as datas de validade).

## 1.0.2

- A baixa não depende mais de `live_mode`, que vem `true` em pagamentos de usuários de teste. A garantia de modo é o token usado na consulta do pagamento. O `live_mode` continua no Log de Gateways.

## 1.0.1

- Modo Teste: o e-mail do cliente do WHMCS não vai mais em `payer.email`. O Mercado Pago recusa vendedor de teste com comprador real ("Uma das partes … é de teste").
- Novo campo opcional "E-mail do comprador de teste", enviado em `payer.email` só no modo Teste.
- O link guardado da fatura agora depende de uma impressão digital do payload (versão, comprador, parcelas, boleto e descrição). Links criados pela 1.0.0 são refeitos na próxima visualização.

## 1.0.0

Primeira versão.

- Checkout Pro: preferência criada via `POST /checkout/preferences`, com `Authorization: Bearer` e `X-Idempotency-Key`.
- Link reaproveitado enquanto o valor e o modo da fatura não mudam e o link não está perto de expirar (tabela `mod_mercadopagopro`).
- Volta automática do cliente (`auto_return`) e `notification_url` em cada preferência.
- Callback aceita Webhook (`type`/`data.id`, corpo JSON) e IPN (`topic`/`id`), com validação opcional de `x-signature`.
- Baixa pela consulta a `GET /v1/payments/{id}`: valor pago e taxas reais, apenas `approved` em BRL, no modo configurado.
- Reembolso pelo WHMCS (`POST /v1/payments/{id}/refunds`).
- Modos Produção e Teste com credenciais separadas.
