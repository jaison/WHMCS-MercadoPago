<?php

namespace MercadoPagoPro;

/**
 * Cliente mínimo da API do Mercado Pago (api.mercadopago.com), com "Authorization: Bearer".
 */
final class Client
{
    public const BASE = 'https://api.mercadopago.com';

    /** @var string */
    private $token;
    /** @var Http */
    private $http;

    public function __construct(string $token, Http $http)
    {
        if ($token === '') {
            throw new ApiException(-1, 'Access Token não configurado');
        }
        $this->token = $token;
        $this->http = $http;
    }

    /** @return array<mixed> preferência criada (id, init_point…) */
    public function createPreference(array $payload, string $idempotencyKey): array
    {
        return $this->call('POST', '/checkout/preferences', $payload, $idempotencyKey);
    }

    /** @return array<mixed> */
    public function getPayment(string $id): array
    {
        if (!preg_match('/^\d{1,20}$/', $id)) {
            throw new ApiException(400, 'id de pagamento inválido');
        }
        return $this->call('GET', '/v1/payments/' . $id);
    }

    /** @return array<mixed> reembolso criado (id, status…) */
    public function refund(string $paymentId, float $amount, string $idempotencyKey): array
    {
        if (!preg_match('/^\d{1,20}$/', $paymentId)) {
            throw new ApiException(400, 'id de pagamento inválido');
        }
        return $this->call('POST', '/v1/payments/' . $paymentId . '/refunds', Payloads::refund($amount), $idempotencyKey);
    }

    /** @return array<mixed> */
    private function call(string $method, string $path, ?array $body = null, string $idempotencyKey = ''): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
        }
        if ($idempotencyKey !== '') {
            $headers['X-Idempotency-Key'] = $idempotencyKey;
        }
        $res = $this->http->request($method, self::BASE . $path, $headers, $body);
        $status = (int) $res['status'];
        if ($status >= 200 && $status < 300 && is_array($res['body'])) {
            return $res['body'];
        }
        $msg = $status === 0 ? 'falha de rede' : 'HTTP ' . $status;
        if (is_array($res['body']) && isset($res['body']['message']) && is_string($res['body']['message'])) {
            $msg .= ': ' . mb_substr(str_replace($this->token, '***', $res['body']['message']), 0, 200);
        }
        throw new ApiException($status, $msg);
    }

    /** UUID v4 para X-Idempotency-Key. */
    public static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
