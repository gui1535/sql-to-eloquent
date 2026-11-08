<?php

namespace SqlToEloquent\Tests\Planejamento;

use PHPUnit\Framework\TestCase;
use SqlToEloquent\Analise\AnalisadorMysql;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\Orquestrador;
use SqlToEloquent\Planejamento\OpcoesGeracao;
use SqlToEloquent\Planejamento\Planejador;

class PlanejadorTest extends TestCase
{
    public function test_filtra_tabelas_no_plano(): void
    {
        $schema = (new AnalisadorMysql)->analisarArquivo(
            __DIR__.'/../fixtures/schema_basico.sql'
        );

        $plano = (new Planejador(new Orquestrador))->montar(
            $schema,
            new OpcoesGeracao(
                artefatos: [TipoArtefato::Model],
                caminhos: ['models' => '/tmp/Models'],
                tabelas: ['usuarios', 'pedidos'],
            ),
        );

        $tabelas = array_map(fn ($arquivo) => $arquivo->tabela, $plano->arquivos);

        $this->assertSame(['usuarios', 'pedidos'], $tabelas);
        $this->assertArrayHasKey('models', $plano->porTipo());
    }
}
