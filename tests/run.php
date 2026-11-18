<?php
/**
 * Testes do gateway, sem WHMCS, sem banco e sem rede.
 *
 *   php tests/run.php            todos os casos de tests/cases/*.php
 *   php tests/run.php Signature  só os arquivos com esse trecho no nome
 *
 * Cada arquivo de caso devolve [nome => function () { … }]. Sai com código 1 se algum falhar.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('America/Sao_Paulo');

require dirname(__DIR__) . '/modules/gateways/mercadopagopro/lib/Autoload.php';
require __DIR__ . '/doubles.php';

final class AssertionFailed extends \RuntimeException
{
}

function ok($cond, string $msg = 'condição falsa'): void
{
    if (!$cond) {
        throw new AssertionFailed($msg);
    }
}

function eq($expected, $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new AssertionFailed(($msg !== '' ? $msg . ': ' : '') . 'esperado ' . var_export($expected, true) . ', veio ' . var_export($actual, true));
    }
}

$filter = $argv[1] ?? '';
$pass = 0;
$fail = 0;
foreach (glob(__DIR__ . '/cases/*.php') ?: [] as $file) {
    if ($filter !== '' && stripos(basename($file), $filter) === false) {
        continue;
    }
    $cases = require $file;
    echo basename($file, '.php'), "\n";
    foreach ($cases as $name => $test) {
        try {
            $test();
            $pass++;
            echo "  ok    ", $name, "\n";
        } catch (\Throwable $e) {
            $fail++;
            echo "  FALHA ", $name, "\n        ", get_class($e), ': ', $e->getMessage(), ' (', basename($e->getFile()), ':', $e->getLine(), ")\n";
        }
    }
}
echo "\n", $pass, ' passaram, ', $fail, " falharam\n";
exit($fail > 0 ? 1 : 0);
