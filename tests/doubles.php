<?php

use MercadoPagoPro\Bridge;
use MercadoPagoPro\Http;
use MercadoPagoPro\Store;

/** HTTP falso: responde com a fila de respostas e guarda as requisições. */
final class FakeHttp implements Http
{
    /** @var array<int, array{status: int, body: array<mixed>|null}> */
    public $responses = [];
    /** @var array<int, array{method: string, url: string, headers: array<string, string>, body: array<mixed>|null}> */
    public $requests = [];

    public function __construct(array ...$responses)
    {
        $this->responses = $responses;
    }

    public function request(string $method, string $url, array $headers, ?array $body = null): array
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        if (!$this->responses) {
            throw new \LogicException('requisição inesperada: ' . $method . ' ' . $url);
        }
        return array_shift($this->responses);
    }
}

final class MemoryStore implements Store
{
    /** @var array<int, array<string, mixed>> */
    public $rows = [];

    public function find(int $invoiceId): ?array
    {
        return $this->rows[$invoiceId] ?? null;
    }

    public function save(int $invoiceId, string $mode, string $amount, string $prefId, string $initPoint, int $expiresAt, int $now): void
    {
        $this->rows[$invoiceId] = ['invoice_id' => $invoiceId, 'mode' => $mode, 'amount' => $amount, 'pref_id' => $prefId, 'init_point' => $initPoint, 'expires_at' => $expiresAt];
    }
}

/** O "exit" das funções do WHMCS, como exceção. */
final class WhmcsExit extends \RuntimeException
{
}

/** Funções do WHMCS: faturas existentes, transações já registradas, pagamentos e log. */
final class FakeBridge implements Bridge
{
    /** @var int[] */
    public $invoices = [];
    /** @var string[] */
    public $transactions = [];
    /** @var array<int, array{0: int, 1: string, 2: float, 3: float}> */
    public $payments = [];
    /** @var array<int, array{0: string, 1: array<mixed>, 2: string}> */
    public $logs = [];

    public function __construct(array $invoices = [])
    {
        $this->invoices = $invoices;
    }

    public function checkInvoice(int $invoiceId, string $gatewayName): int
    {
        if (!in_array($invoiceId, $this->invoices, true)) {
            $this->logs[] = [$gatewayName, ['invoiceid' => $invoiceId], 'Invoice ID Not Found'];
            throw new WhmcsExit('fatura inexistente');
        }
        return $invoiceId;
    }

    public function checkTransaction(string $transId): void
    {
        if (in_array($transId, $this->transactions, true)) {
            throw new WhmcsExit('transação já registrada');
        }
    }

    public function addPayment(int $invoiceId, string $transId, float $amount, float $fee): void
    {
        $this->payments[] = [$invoiceId, $transId, $amount, $fee];
        $this->transactions[] = $transId;
    }

    public function log(string $gatewayName, array $data, string $result): void
    {
        $this->logs[] = [$gatewayName, $data, $result];
    }

    public function results(): array
    {
        return array_column($this->logs, 2);
    }
}

/** Parâmetros do gateway como o WHMCS entrega (modo Teste por padrão). */
function gw(array $over = []): array
{
    return $over + [
        'name' => 'mercadopagopro',
        'type' => 'Invoices',
        'mode' => 'test',
        'token_live' => 'APP_USR-live-token',
        'token_test' => 'TEST-test-token',
        'secret_live' => '',
        'secret_test' => '',
        'installments' => '12',
        'descriptor' => '',
        'exclude_ticket' => 'on',
        'link_hours' => '72',
    ];
}

/** Pagamento como a API devolve em GET /v1/payments/{id}. */
function payment(array $over = []): array
{
    return $over + [
        'id' => 1234567890,
        'status' => 'approved',
        'status_detail' => 'accredited',
        'currency_id' => 'BRL',
        'transaction_amount' => 59.9,
        'external_reference' => '501',
        'live_mode' => false,
        'payment_type_id' => 'credit_card',
        'payment_method_id' => 'visa',
        'fee_details' => [['type' => 'mercadopago_fee', 'amount' => 2.99], ['type' => 'financing_fee', 'amount' => 1.01]],
        'payer' => ['email' => 'comprador@example.com', 'identification' => ['number' => '12345678909']],
    ];
}

/** Garante que nada sensível (token, secret, dados do pagador) aparece no Log de Gateways. */
function assertCleanLogs(FakeBridge $b): void
{
    $all = json_encode($b->logs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    foreach (['APP_USR-live-token', 'TEST-test-token', 'segredo', 'comprador@example.com', '12345678909'] as $s) {
        ok(strpos((string) $all, $s) === false, 'log contém dado sensível: ' . $s);
    }
}
