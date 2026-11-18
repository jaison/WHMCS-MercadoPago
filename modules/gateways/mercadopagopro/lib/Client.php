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

    /** @return array<mixed> order criada (id, status, checkout_url…) */
    public function createOrder(array $payload, string $idempotencyKey): array
    {
        return $this->call('POST', '/v1/orders', $payload, $idempotencyKey);
    }

    /** @return array<mixed> */
    public function getOrder(string $id): array
    {
        if (!Module::isOrderId($id)) {
            throw new ApiException(400, 'id de order inválido');
        }
        return $this->call('GET', '/v1/orders/' . $id);
    }

    /**
     * Reembolso de order: total sem corpo; parcial com uma transação (id do pagamento da order).
     * @return array<mixed>
     */
    public function refundOrder(string $orderId, string $idempotencyKey, ?string $paymentId = null, ?float $amount = null): array
    {
        if (!Module::isOrderId($orderId)) {
            throw new ApiException(400, 'id de order inválido');
        }
        $body = $paymentId !== null && $amount !== null ? Payloads::orderRefund($paymentId, $amount) : null;
        return $this->call('POST', '/v1/orders/' . $orderId . '/refund', $body, $idempotencyKey);
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
        if ($body !== null || $method === 'POST') {
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
        $detail = self::errorText($res['body']);
        if ($detail !== '') {
            $msg .= ': ' . self::redact(mb_substr(str_replace($this->token, '***', $detail), 0, 400));
        }
        throw new ApiException($status, $msg);
    }

    /**
     * Texto do erro: "message" (API de pagamentos) e/ou "errors[]" {code, message, details[]}
     * (API de Orders).
     */
    public static function errorText($body): string
    {
        if (!is_array($body)) {
            return '';
        }
        $parts = [];
        if (isset($body['message']) && is_string($body['message'])) {
            $parts[] = $body['message'];
        }
        foreach (array_slice((array) ($body['errors'] ?? []), 0, 3) as $e) {
            if (!is_array($e)) {
                continue;
            }
            $t = trim((string) ($e['code'] ?? '') . ' ' . (string) ($e['message'] ?? ''));
            $details = array_filter(array_map(static function ($d) {
                return is_scalar($d) ? (string) $d : '';
            }, array_slice((array) ($e['details'] ?? []), 0, 3)));
            if ($details) {
                $t .= ' (' . implode('; ', $details) . ')';
            }
            if ($t !== '') {
                $parts[] = $t;
            }
        }
        return implode(' | ', $parts);
    }

    /** Tira da mensagem da API o que pode ser dado pessoal ecoado (e-mail, documento, telefone). */
    public static function redact(string $s): string
    {
        $s = preg_replace('/[^\s@:,;"\'<>]+@[^\s@:,;"\'<>]+/', '***', $s) ?? '';
        return preg_replace('/\d[\d.\-\/ ]{6,}\d/', '***', $s) ?? '';
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
