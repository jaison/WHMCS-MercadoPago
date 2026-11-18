<?php

use MercadoPagoPro\Signature;

return [
    'Manifesto no formato do Mercado Pago; id em minúsculas; partes ausentes ficam de fora' => function () {
        eq('id:123;request-id:abc-1;ts:1700000000;', Signature::manifest('123', 'abc-1', '1700000000'));
        eq('id:abcdef;request-id:r;ts:1;', Signature::manifest('ABCDEF', 'r', '1'));
        eq('request-id:r;ts:1;', Signature::manifest('', 'r', '1'));
        eq('id:9;ts:1;', Signature::manifest('9', '', '1'));
    },
    'Header: lê ts e v1 em qualquer ordem, com espaços; recusa formatos ruins' => function () {
        $h = 'v1= ' . str_repeat('a', 64) . ' , ts=1700000000';
        eq(['ts' => '1700000000', 'v1' => str_repeat('a', 64)], Signature::parse($h));
        eq(null, Signature::parse(''));
        eq(null, Signature::parse('ts=1700000000'));
        eq(null, Signature::parse('ts=abc,v1=' . str_repeat('a', 64)));
        eq(null, Signature::parse('ts=1,v1=' . str_repeat('z', 64)));
    },
    'Válida: HMAC-SHA256 do manifesto com o secret (valor de referência calculado à parte)' => function () {
        $secret = 'segredo';
        $expected = hash_hmac('sha256', 'id:123;request-id:req-1;ts:1700000000;', $secret);
        $h = 'ts=1700000000,v1=' . $expected;
        ok(Signature::verify($secret, $h, 'req-1', '123'));
        eq($h, Signature::sign($secret, '123', 'req-1', '1700000000'));
    },
    'Inválida: outro secret, outro id, outro request-id, header vazio ou secret vazio' => function () {
        $h = Signature::sign('segredo', '123', 'req-1', '1700000000');
        eq(false, Signature::verify('outro', $h, 'req-1', '123'));
        eq(false, Signature::verify('segredo', $h, 'req-1', '124'));
        eq(false, Signature::verify('segredo', $h, 'req-2', '123'));
        eq(false, Signature::verify('segredo', '', 'req-1', '123'));
        eq(false, Signature::verify('', $h, 'req-1', '123'));
    },
];
