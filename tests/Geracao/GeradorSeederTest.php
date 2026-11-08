<?php

namespace SqlToEloquent\Tests\Geracao;

use PHPUnit\Framework\TestCase;
use SqlToEloquent\Analise\AnalisadorMysql;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\Geradores\GeradorSeeder;
use SqlToEloquent\Planejamento\OpcoesGeracao;

class GeradorSeederTest extends TestCase
{
    public function test_gera_seeder_apenas_quando_ha_insert(): void
    {
        $comDados = (new AnalisadorMysql)->analisarArquivo(
            __DIR__.'/../fixtures/schema_basico.sql'
        );

        $arquivos = (new GeradorSeeder)->gerar(
            $comDados,
            new OpcoesGeracao(
                artefatos: [TipoArtefato::Seeder],
                caminhos: ['seeders' => '/tmp/seeders'],
            ),
        );

        $this->assertCount(1, $arquivos);
        $this->assertSame('usuarios', $arquivos[0]->tabela);
        $this->assertStringContainsString('class UsuarioSeeder extends Seeder', $arquivos[0]->conteudo);
        $this->assertStringContainsString("'nome' => 'Ana'", $arquivos[0]->conteudo);
        $this->assertStringContainsString("'email' => 'ana@teste.com'", $arquivos[0]->conteudo);
        $this->assertTrue($this->sintaxeValida($arquivos[0]->conteudo));

        $semDados = (new AnalisadorMysql)->analisar(<<<'SQL'
            CREATE TABLE produtos (
              id bigint unsigned NOT NULL AUTO_INCREMENT,
              nome varchar(120) NOT NULL,
              PRIMARY KEY (id)
            );
        SQL);

        $this->assertSame(
            [],
            (new GeradorSeeder)->gerar(
                $semDados,
                new OpcoesGeracao(
                    artefatos: [TipoArtefato::Seeder],
                    caminhos: ['seeders' => '/tmp/seeders'],
                ),
            )
        );
    }

    private function sintaxeValida(string $conteudo): bool
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'seed_').'.php';
        file_put_contents($arquivo, $conteudo);
        exec('php -l '.escapeshellarg($arquivo).' 2>&1', $saida, $codigo);
        unlink($arquivo);

        return $codigo === 0;
    }
}
