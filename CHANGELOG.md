# Changelog

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
