<?php

namespace MercadoPagoPro;

/**
 * Notificação recebida no callback, nos dois formatos:
 * - Webhook: ?type=payment&data.id=X (o PHP entrega como data_id) e corpo JSON {type, action, data: {id}};
 * - IPN: ?topic=payment&id=X.
 * $idFromQuery é o id que entra no manifesto da assinatura (o da URL).
 */
final class Notification
{
    /** @var string payment, merchant_order, … ou '' */
    public $topic = '';
    /** @var string */
    public $id = '';
    /** @var string */
    public $idFromQuery = '';
    /** @var string */
    public $action = '';
    /** @var bool true = formato webhook (tem assinatura) */
    public $webhook = false;

    public static function parse(array $get, string $body): self
    {
        $n = new self();
        $json = $body !== '' ? json_decode($body, true) : null;
        $json = is_array($json) ? $json : [];

        $qType = self::str($get['type'] ?? '');
        $qDataId = self::str($get['data_id'] ?? ($get['data.id'] ?? ''));
        $qTopic = self::str($get['topic'] ?? '');
        $qId = self::str($get['id'] ?? '');
        $bType = self::str($json['type'] ?? '');
        $bId = is_array($json['data'] ?? null) ? self::str($json['data']['id'] ?? '') : '';

        if ($qType !== '' || $qDataId !== '' || $bType !== '' || $bId !== '') {
            $n->webhook = true;
            $n->topic = strtolower($qType !== '' ? $qType : $bType);
            $n->idFromQuery = $qDataId;
            $n->id = $qDataId !== '' ? $qDataId : $bId;
            $n->action = self::str($json['action'] ?? '');
        } elseif ($qTopic !== '') {
            $n->topic = strtolower($qTopic);
            $n->id = $qId;
            $n->idFromQuery = $qId;
        }
        return $n;
    }

    /** Só pagamentos com id numérico são processados; o resto é ignorado com 200. */
    public function isPayment(): bool
    {
        return $this->topic === 'payment' && preg_match('/^\d{1,20}$/', $this->id) === 1;
    }

    private static function str($v): string
    {
        return is_scalar($v) ? substr(trim((string) $v), 0, 64) : '';
    }
}
