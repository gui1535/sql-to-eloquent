<?php

namespace SqlToEloquent\Tests\Analise;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlToEloquent\Analise\AnalisadorMysql;

class AnalisadorMysqlTest extends TestCase
{
    public function test_falha_quando_arquivo_nao_existe(): void
    {
        $analisador = new AnalisadorMysql;

        $this->expectException(RuntimeException::class);

        $analisador->analisarArquivo('/caminho/inexistente.sql');
    }
}
