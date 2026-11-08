<?php

namespace SqlToEloquent\Tests\Geracao;

use PHPUnit\Framework\TestCase;
use SqlToEloquent\Analise\AnalisadorMysql;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\Geradores\GeradorRota;
use SqlToEloquent\Planejamento\OpcoesGeracao;

class GeradorRotaInflexaoTest extends TestCase
{
    public function test_parameters_usa_singular_portugues(): void
    {
        $schema = (new AnalisadorMysql)->analisar(<<<'SQL'
            CREATE TABLE cupons (
              id bigint unsigned NOT NULL AUTO_INCREMENT,
              codigo varchar(40) NOT NULL,
              PRIMARY KEY (id)
            );

            CREATE TABLE fornecedores (
              id bigint unsigned NOT NULL AUTO_INCREMENT,
              nome varchar(120) NOT NULL,
              PRIMARY KEY (id)
            );
        SQL);

        $conteudo = (new GeradorRota)->gerar(
            $schema,
            new OpcoesGeracao(
                artefatos: [TipoArtefato::Controller, TipoArtefato::Rota],
                caminhos: ['rotas' => '/tmp/routes'],
            ),
        )[0]->conteudo;

        $this->assertStringContainsString(
            "Route::apiResource('cupons', CupomController::class)->parameters(['cupons' => 'cupom']);",
            $conteudo,
        );
        $this->assertStringContainsString(
            "Route::apiResource('fornecedores', FornecedorController::class)->parameters(['fornecedores' => 'fornecedor']);",
            $conteudo,
        );
    }
}
