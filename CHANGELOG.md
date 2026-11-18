# Changelog

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
