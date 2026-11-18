<?php

use MercadoPagoPro\Buyer;

return [
    'CPF e CNPJ: aceita com ou sem máscara, recusa dígito errado, repetido e tamanho errado' => function () {
        eq('52998224725', Buyer::cpf('529.982.247-25'));
        eq('', Buyer::cpf('529.982.247-24'));
        eq('', Buyer::cpf('111.111.111-11'));
        eq('', Buyer::cpf('1234'));
        eq('11222333000181', Buyer::cnpj('11.222.333/0001-81'));
        eq('', Buyer::cnpj('11.222.333/0001-80'));
        eq(['type' => 'CPF', 'number' => '52998224725'], Buyer::identification('52998224725'));
        eq(['type' => 'CNPJ', 'number' => '11222333000181'], Buyer::identification('11222333000181'));
        eq(null, Buyer::identification('abc'));
    },
    'Documento: automático usa o Tax ID; senão o campo com CPF/CNPJ no nome; ou o campo escolhido' => function () {
        $d = new FakeData();
        eq('529.982.247-25', Buyer::document(client(), '', $d));
        $d->values['50:7'] = '11.222.333/0001-81';
        eq('11.222.333/0001-81', Buyer::document(client(['tax_id' => '']), '', $d), 'campo "CPF/CNPJ" no banco');
        eq('11.222.333/0001-81', Buyer::document(client(), 'cf:7', $d), 'campo escolhido vence o Tax ID');
        eq('', Buyer::document(client(['tax_id' => '']), 'tax_id', $d));
        eq('52998224725', Buyer::document(client(['tax_id' => '', 'customfields' => [['id' => 7, 'value' => '52998224725']]]), 'cf:7', $d), 'valor vindo nos params');
        $d->values = ['50:3' => '52998224725'];
        eq('', Buyer::document(client(['tax_id' => '']), '', $d), 'campo sem CPF/CNPJ no nome não é usado no automático');
    },
    'Telefone: só Brasil, com ou sem 55, DDD + 8 ou 9 dígitos' => function () {
        eq(['area_code' => '47', 'number' => '999998888'], Buyer::phone(client()));
        eq(['area_code' => '11', 'number' => '33334444'], Buyer::phone(client(['phonenumber' => '(11) 3333-4444'])));
        eq(['area_code' => '47', 'number' => '999998888'], Buyer::phone(client(['phonecc' => '', 'phonenumber' => '047 99999-8888'])));
        eq(null, Buyer::phone(client(['phonecc' => '1', 'country' => 'US', 'phonenumber' => '+1.2025550100'])));
        eq(null, Buyer::phone(client(['phonenumber' => '123'])));
    },
    'Endereço: CEP de 8 dígitos e rua; número separado quando vem depois da vírgula; fora do Brasil não vai' => function () {
        eq(['zip_code' => '89000000', 'street_name' => 'Rua das Flores', 'street_number' => '100'], Buyer::address(client()));
        eq(['zip_code' => '89000000', 'street_name' => 'Avenida Brasil'], Buyer::address(client(['address1' => 'Avenida Brasil'])));
        eq(null, Buyer::address(client(['postcode' => '123'])));
        eq(null, Buyer::address(client(['country' => 'PT'])));
    },
];
