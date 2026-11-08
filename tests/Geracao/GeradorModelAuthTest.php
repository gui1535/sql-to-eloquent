<?php

namespace SqlToEloquent\Tests\Geracao;

use PHPUnit\Framework\TestCase;
use SqlToEloquent\Analise\AnalisadorMysql;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\Geradores\GeradorModel;
use SqlToEloquent\Planejamento\OpcoesGeracao;

class GeradorModelAuthTest extends TestCase
{
    public function test_gera_get_auth_password_name_quando_senha_customizada(): void
    {
        $schema = (new AnalisadorMysql)->analisarArquivo(
            __DIR__.'/../fixtures/schema_basico.sql'
        );

        $arquivo = null;
        foreach ((new GeradorModel)->gerar(
            $schema,
            new OpcoesGeracao(
                artefatos: [TipoArtefato::Model],
                caminhos: ['models' => '/tmp/Models'],
                tabelaUsers: 'usuarios',
                campoEmail: 'email',
                campoSenha: 'senha',
            ),
        ) as $gerado) {
            if ($gerado->tabela === 'usuarios') {
                $arquivo = $gerado->conteudo;
            }
        }

        $this->assertNotNull($arquivo);
        $this->assertStringContainsString('class User extends Authenticatable', $arquivo);
        $this->assertStringContainsString('function getAuthPasswordName(): string', $arquivo);
        $this->assertStringContainsString("return 'senha';", $arquivo);
        $this->assertStringContainsString("'senha' => 'hashed'", $arquivo);
        $this->assertStringContainsString("'senha',", $arquivo);
    }
}
