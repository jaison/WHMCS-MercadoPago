<?php

namespace MercadoPagoPro;

/**
 * Erro da API do Mercado Pago. temporary() = vale tentar de novo (rede, 429 ou 5xx).
 * A mensagem nunca contém o token.
 */
final class ApiException extends \RuntimeException
{
    /** @var int */
    public $status;

    public function __construct(int $status, string $message)
    {
        parent::__construct($message);
        $this->status = $status;
    }

    public function temporary(): bool
    {
        return $this->status === 0 || $this->status === 429 || $this->status >= 500;
    }
}
