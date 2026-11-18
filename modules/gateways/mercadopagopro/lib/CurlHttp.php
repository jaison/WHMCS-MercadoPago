<?php

namespace MercadoPagoPro;

/**
 * HTTP com cURL: TLS verificado, timeouts curtos, JSON na ida e na volta.
 */
final class CurlHttp implements Http
{
    public function request(string $method, string $url, array $headers, ?array $body = null): array
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        $ch = curl_init($url);
        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'WHMCS-MercadoPagoPro/' . Module::VERSION,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $status = $raw === false ? 0 : (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        return ['status' => $status, 'body' => is_array($decoded) ? $decoded : null];
    }
}
