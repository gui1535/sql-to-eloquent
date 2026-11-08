<?php

namespace SqlToEloquent\Tests\Geracao;

use PHPUnit\Framework\TestCase;
use SqlToEloquent\Analise\AnalisadorMysql;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\Geradores\GeradorMigration;
use SqlToEloquent\Planejamento\OpcoesGeracao;

class GeradorMigrationTest extends TestCase
{
    public function test_gera_migrations_ordenadas_por_dependencia(): void
    {
        $schema = (new AnalisadorMysql)->analisarArquivo(
            __DIR__.'/../fixtures/schema_basico.sql'
        );

        $arquivos = (new GeradorMigration)->gerar(
            $schema,
            new OpcoesGeracao(
                artefatos: [TipoArtefato::Migration],
                caminhos: ['migrations' => '/tmp/migrations'],
            ),
        );

        $this->assertCount(4, $arquivos);

        $tabelas = array_map(fn ($arquivo) => $arquivo->tabela, $arquivos);
        $this->assertSame(['usuarios', 'produtos', 'pedidos', 'itens_pedido'], $tabelas);

        $this->assertSame(
            '/tmp/migrations/2020_01_01_000001_create_usuarios_table.php',
            $arquivos[0]->caminho
        );

        $usuarios = $arquivos[0]->conteudo;
        $this->assertStringContainsString('$table->id();', $usuarios);
        $this->assertStringContainsString("\$table->string('nome')", $usuarios);
        $this->assertStringContainsString("\$table->boolean('ativo')->default(true)", $usuarios);
        $this->assertStringContainsString("\$table->enum('perfil', ['admin', 'cliente'])->default('cliente')", $usuarios);
        $this->assertStringContainsString("\$table->unique('email', 'usuarios_email_unique')", $usuarios);
        $this->assertStringContainsString("Schema::dropIfExists('usuarios')", $usuarios);

        $produtos = $arquivos[1]->conteudo;
        $this->assertStringContainsString("\$table->decimal('preco', 10, 2)->default(0.00)", $produtos);
        $this->assertStringContainsString("\$table->unique('nome'", $produtos);

        $pedidos = $arquivos[2]->conteudo;
        $this->assertStringContainsString("\$table->foreign('usuario_id', 'pedidos_usuario_id_foreign')", $pedidos);
        $this->assertStringContainsString("->on('usuarios')", $pedidos);
        $this->assertStringContainsString("->onDelete('cascade')", $pedidos);
        $this->assertStringContainsString("->onUpdate('restrict')", $pedidos);
        $this->assertStringNotContainsString("\$table->index('usuario_id'", $pedidos);

        foreach ($arquivos as $arquivo) {
            $this->assertTrue($this->sintaxeValida($arquivo->conteudo), $arquivo->caminho);
        }
    }

    public function test_usa_timestamps_quando_colunas_padrao_existem(): void
    {
        $schema = (new AnalisadorMysql)->analisar(<<<'SQL'
            CREATE TABLE posts (
              id bigint unsigned NOT NULL AUTO_INCREMENT,
              titulo varchar(255) NOT NULL,
              created_at timestamp NULL DEFAULT NULL,
              updated_at timestamp NULL DEFAULT NULL,
              PRIMARY KEY (id)
            );
        SQL);

        $arquivo = (new GeradorMigration)->gerar(
            $schema,
            new OpcoesGeracao(
                artefatos: [TipoArtefato::Migration],
                caminhos: ['migrations' => 'database/migrations'],
            ),
        )[0];

        $this->assertStringContainsString('$table->timestamps();', $arquivo->conteudo);
        $this->assertStringNotContainsString("\$table->timestamp('created_at')", $arquivo->conteudo);
        $this->assertStringNotContainsString("\$table->timestamp('updated_at')", $arquivo->conteudo);
    }

    private function sintaxeValida(string $conteudo): bool
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'mig_').'.php';
        file_put_contents($arquivo, $conteudo);
        exec('php -l '.escapeshellarg($arquivo).' 2>&1', $saida, $codigo);
        unlink($arquivo);

        return $codigo === 0;
    }
}
