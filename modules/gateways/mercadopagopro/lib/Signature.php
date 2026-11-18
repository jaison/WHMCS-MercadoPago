<?php

namespace MercadoPagoPro;

/**
 * Assinatura dos webhooks (header x-signature: "ts=...,v1=...").
 * Manifesto: "id:{data.id};request-id:{x-request-id};ts:{ts};" (partes ausentes ficam de fora),
 * com data.id em minúsculas; v1 = HMAC-SHA256(manifesto, secret) em hexadecimal.
 */
final class Signature
{
    /** @return array{ts: string, v1: string}|null */
    public static function parse(string $header): ?array
    {
        $ts = '';
        $v1 = '';
        foreach (explode(',', $header) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) !== 2) {
                continue;
            }
            $k = strtolower(trim($kv[0]));
            if ($k === 'ts') {
                $ts = trim($kv[1]);
            } elseif ($k === 'v1') {
                $v1 = strtolower(trim($kv[1]));
            }
        }
        if (!preg_match('/^\d{1,20}$/', $ts) || !preg_match('/^[0-9a-f]{64}$/', $v1)) {
            return null;
        }
        return ['ts' => $ts, 'v1' => $v1];
    }

    public static function manifest(string $dataId, string $requestId, string $ts): string
    {
        $m = '';
        if ($dataId !== '') {
            $m .= 'id:' . strtolower($dataId) . ';';
        }
        if ($requestId !== '') {
            $m .= 'request-id:' . $requestId . ';';
        }
        return $m . 'ts:' . $ts . ';';
    }

    public static function verify(string $secret, string $header, string $requestId, string $dataId): bool
    {
        if ($secret === '') {
            return false;
        }
        $sig = self::parse($header);
        if ($sig === null) {
            return false;
        }
        $expected = hash_hmac('sha256', self::manifest($dataId, $requestId, $sig['ts']), $secret);
        return hash_equals($expected, $sig['v1']);
    }

    /** Para os testes: gera o header como o Mercado Pago gera. */
    public static function sign(string $secret, string $dataId, string $requestId, string $ts): string
    {
        return 'ts=' . $ts . ',v1=' . hash_hmac('sha256', self::manifest($dataId, $requestId, $ts), $secret);
    }
}
