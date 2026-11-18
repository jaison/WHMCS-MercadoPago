<?php

namespace MercadoPagoPro;

/**
 * Transporte HTTP. A implementação real é CurlHttp; os testes usam um dublê.
 */
interface Http
{
    /**
     * @param array<string, string> $headers
     * @param array<mixed>|null     $body    enviado como JSON
     * @return array{status: int, body: array<mixed>|null}  status 0 = falha de rede
     */
    public function request(string $method, string $url, array $headers, ?array $body = null): array;
}
