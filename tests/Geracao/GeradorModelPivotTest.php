<?php

namespace SqlToEloquent\Tests\Geracao;

use PHPUnit\Framework\TestCase;
use SqlToEloquent\Analise\AnalisadorMysql;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\Geradores\GeradorController;
use SqlToEloquent\Geracao\Geradores\GeradorModel;
use SqlToEloquent\Planejamento\OpcoesGeracao;

class GeradorModelPivotTest extends TestCase
{
    public function test_gera_belongs_to_many_e_ignora_model_de_pivot_pura(): void
    {
        $schema = (new AnalisadorMysql)->analisar(<<<'SQL'
            CREATE TABLE produtos (
              id bigint unsigned NOT NULL AUTO_INCREMENT,
              nome varchar(120) NOT NULL,
              PRIMARY KEY (id)
            );

            CREATE TABLE categorias (
              id bigint unsigned NOT NULL AUTO_INCREMENT,
              nome varchar(120) NOT NULL,
              PRIMARY KEY (id)
            );

            CREATE TABLE produto_categoria (
              produto_id bigint unsigned NOT NULL,
              categoria_id bigint unsigned NOT NULL,
              PRIMARY KEY (produto_id, categoria_id),
              CONSTRAINT produto_categoria_produto_id_foreign FOREIGN KEY (produto_id) REFERENCES produtos (id),
              CONSTRAINT produto_categoria_categoria_id_foreign FOREIGN KEY (categoria_id) REFERENCES categorias (id)
            );
        SQL);

        $models = (new GeradorModel)->gerar(
            $schema,
            new OpcoesGeracao(
                artefatos: [TipoArtefato::Model],
                caminhos: ['models' => '/tmp/Models'],
            ),
        );

        $porTabela = [];
        foreach ($models as $arquivo) {
            $porTabela[$arquivo->tabela] = $arquivo->conteudo;
        }

        $this->assertArrayHasKey('produtos', $porTabela);
        $this->assertArrayHasKey('categorias', $porTabela);
        $this->assertArrayNotHasKey('produto_categoria', $porTabela);

        $this->assertStringContainsString('function categorias(): BelongsToMany', $porTabela['produtos']);
        $this->assertStringContainsString(
            "return \$this->belongsToMany(Categoria::class, 'produto_categoria', 'produto_id', 'categoria_id');",
            $porTabela['produtos'],
        );
        $this->assertStringNotContainsString('function produtoCategoria()', $porTabela['produtos']);

        $this->assertStringContainsString('function produtos(): BelongsToMany', $porTabela['categorias']);

        $controllers = (new GeradorController)->gerar(
            $schema,
            new OpcoesGeracao(
                artefatos: [TipoArtefato::Controller],
                caminhos: ['controllers' => '/tmp/Controllers'],
            ),
        );

        $tabelasController = array_map(fn ($arquivo) => $arquivo->tabela, $controllers);
        $this->assertSame(['produtos', 'categorias'], $tabelasController);
    }
}
