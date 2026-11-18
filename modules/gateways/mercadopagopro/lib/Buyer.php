<?php

namespace MercadoPagoPro;

/**
 * Dados do comprador para a preferência (payer), a partir de clientdetails do WHMCS.
 * Tudo vai só ao Mercado Pago; nada disto é registrado em log.
 */
final class Buyer
{
    /** Só dígitos, ou '' se não for um CPF válido. */
    public static function cpf(string $s): string
    {
        $d = preg_replace('/\D+/', '', $s) ?? '';
        if (strlen($d) !== 11 || preg_match('/^(\d)\1{10}$/', $d)) {
            return '';
        }
        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $d[$i] * ($t + 1 - $i);
            }
            if ((int) $d[$t] !== ((10 * $sum) % 11) % 10) {
                return '';
            }
        }
        return $d;
    }

    /** Só dígitos, ou '' se não for um CNPJ válido. */
    public static function cnpj(string $s): string
    {
        $d = preg_replace('/\D+/', '', $s) ?? '';
        if (strlen($d) !== 14 || preg_match('/^(\d)\1{13}$/', $d)) {
            return '';
        }
        foreach ([12, 13] as $t) {
            $w = $t === 12 ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2] : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $d[$i] * $w[$i];
            }
            $r = $sum % 11;
            if ((int) $d[$t] !== ($r < 2 ? 0 : 11 - $r)) {
                return '';
            }
        }
        return $d;
    }

    /** @return array{type: string, number: string}|null */
    public static function identification(string $doc): ?array
    {
        if (($n = self::cpf($doc)) !== '') {
            return ['type' => 'CPF', 'number' => $n];
        }
        if (($n = self::cnpj($doc)) !== '') {
            return ['type' => 'CNPJ', 'number' => $n];
        }
        return null;
    }

    /**
     * Documento do cliente conforme o campo escolhido na configuração:
     * '' = automático (Tax ID; senão o primeiro campo personalizado com CPF/CNPJ/documento no nome),
     * 'tax_id' = só o Tax ID, 'cf:<id>' = esse campo personalizado.
     */
    public static function document(array $client, string $source, Data $data): string
    {
        $clientId = (int) ($client['userid'] ?? $client['id'] ?? 0);
        $tax = (string) ($client['tax_id'] ?? '');
        if ($source === 'tax_id') {
            return $tax;
        }
        if (preg_match('/^cf:(\d+)$/', $source, $m)) {
            return self::customField($client, (int) $m[1], $clientId, $data);
        }
        if (self::identification($tax) !== null) {
            return $tax;
        }
        foreach ($data->clientCustomFields() as $id => $name) {
            if (preg_match('/cpf|cnpj|documento|\bdoc\b/i', $name)) {
                $v = self::customField($client, (int) $id, $clientId, $data);
                if (self::identification($v) !== null) {
                    return $v;
                }
            }
        }
        return '';
    }

    private static function customField(array $client, int $fieldId, int $clientId, Data $data): string
    {
        foreach ((array) ($client['customfields'] ?? []) as $cf) {
            if (is_array($cf) && (int) ($cf['id'] ?? 0) === $fieldId) {
                return (string) ($cf['value'] ?? '');
            }
        }
        return $clientId > 0 ? $data->customFieldValue($clientId, $fieldId) : '';
    }

    /** Telefone brasileiro: {area_code: "47", number: "999998888"} ou null. */
    public static function phone(array $client): ?array
    {
        $cc = preg_replace('/\D+/', '', (string) ($client['phonecc'] ?? '')) ?? '';
        $raw = (string) ($client['phonenumber'] ?? '');
        $d = preg_replace('/\D+/', '', $raw) ?? '';
        $br = $cc === '55' || strtoupper((string) ($client['country'] ?? '')) === 'BR' || strpos(trim($raw), '+55') === 0;
        if (!$br) {
            return null;
        }
        if ((strlen($d) === 12 || strlen($d) === 13) && strpos($d, '55') === 0) {
            $d = substr($d, 2);
        }
        if ((strlen($d) === 11 || strlen($d) === 12) && $d[0] === '0') {
            $d = substr($d, 1);
        }
        if (strlen($d) !== 10 && strlen($d) !== 11) {
            return null;
        }
        return ['area_code' => substr($d, 0, 2), 'number' => substr($d, 2)];
    }

    /** Endereço (só Brasil): {zip_code, street_name[, street_number]} ou null. */
    public static function address(array $client): ?array
    {
        if (strtoupper((string) ($client['country'] ?? 'BR')) !== 'BR') {
            return null;
        }
        $zip = preg_replace('/\D+/', '', (string) ($client['postcode'] ?? '')) ?? '';
        $street = trim(preg_replace('/\s+/', ' ', (string) ($client['address1'] ?? '')) ?? '');
        if (strlen($zip) !== 8 || $street === '') {
            return null;
        }
        $a = ['zip_code' => $zip, 'street_name' => mb_substr($street, 0, 100)];
        if (preg_match('/^(.*?),\s*(\d{1,6})\b/u', $street, $m)) {
            $a['street_name'] = mb_substr(trim($m[1]), 0, 100);
            $a['street_number'] = $m[2];
        }
        return $a;
    }

    /**
     * Objeto payer da preferência. $email já vem decidido pelo modo (Config::payerEmail).
     * @param string $created data de cadastro do cliente ('' se não houver)
     */
    public static function payer(array $client, string $email, string $doc, string $created): array
    {
        $p = [];
        $first = trim((string) ($client['firstname'] ?? ''));
        $last = trim((string) ($client['lastname'] ?? ''));
        if ($first !== '') {
            $p['name'] = mb_substr($first, 0, 60);
        }
        if ($last !== '') {
            $p['surname'] = mb_substr($last, 0, 60);
        }
        if ($email !== '') {
            $p['email'] = $email;
        }
        if (($ph = self::phone($client)) !== null) {
            $p['phone'] = $ph;
        }
        if (($id = self::identification($doc)) !== null) {
            $p['identification'] = $id;
        }
        if (($ad = self::address($client)) !== null) {
            $p['address'] = $ad;
        }
        $ts = $created !== '' && strpos($created, '0000') !== 0 ? strtotime($created) : false;
        if ($ts !== false && $ts > 0) {
            $p['date_created'] = Payloads::date($ts);
        }
        return $p;
    }
}
