<?php

namespace SqlToEloquent\Tests\Dominio;

use PHPUnit\Framework\TestCase;
use SqlToEloquent\Dominio\Coluna;
use SqlToEloquent\Dominio\Insercao;
use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Dominio\Tabela;

class SchemaTest extends TestCase
{
    public function test_busca_tabela_pelo_nome(): void
    {
        $schema = new Schema([
            new Tabela(
                nome: 'usuarios',
                colunas: [
                    new Coluna(nome: 'id', tipo: 'bigint', nulo: false, autoIncremento: true),
                ],
                chavePrimaria: ['id'],
            ),
        ]);

        $this->assertNotNull($schema->tabela('usuarios'));
        $this->assertNull($schema->tabela('pedidos'));
        $this->assertSame(['usuarios'], $schema->nomesTabelas());
    }

    public function test_filtra_tabelas_e_insercoes(): void
    {
        $schema = new Schema(
            tabelas: [
                new Tabela(nome: 'usuarios', colunas: [new Coluna('id', 'int')]),
                new Tabela(nome: 'pedidos', colunas: [new Coluna('id', 'int')]),
            ],
            insercoes: [
                new Insercao('usuarios', ['nome'], [['Ana']]),
                new Insercao('pedidos', ['total'], [[10]]),
            ],
        );

        $filtrado = $schema->filtrarTabelas(['usuarios']);

        $this->assertSame(['usuarios'], $filtrado->nomesTabelas());
        $this->assertCount(1, $filtrado->insercoes);
        $this->assertSame('usuarios', $filtrado->insercoes[0]->tabela);
        $this->assertSame(1, $filtrado->totalColunas());
        $this->assertSame(1, $filtrado->totalLinhasInsert());
    }
}
