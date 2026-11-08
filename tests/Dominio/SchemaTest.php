<?php

namespace SqlToEloquent\Tests\Dominio;

use PHPUnit\Framework\TestCase;
use SqlToEloquent\Dominio\Coluna;
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
}
