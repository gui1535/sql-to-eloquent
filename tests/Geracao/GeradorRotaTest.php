<?php

namespace SqlToEloquent\Tests\Geracao;

use PHPUnit\Framework\TestCase;
use SqlToEloquent\Analise\AnalisadorMysql;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\Geradores\GeradorRota;
use SqlToEloquent\Planejamento\OpcoesGeracao;

class GeradorRotaTest extends TestCase
{
    public function test_gera_api_resource_com_parametro_de_user(): void
    {
        $schema = (new AnalisadorMysql)->analisarArquivo(
            __DIR__.'/../fixtures/schema_basico.sql'
        );

        $arquivo = (new GeradorRota)->gerar(
            $schema,
            new OpcoesGeracao(
                artefatos: [TipoArtefato::Controller, TipoArtefato::Rota],
                caminhos: ['rotas' => '/tmp/routes'],
                tabelaUsers: 'usuarios',
                tabelasCrud: ['usuarios', 'pedidos'],
            ),
        )[0];

        $this->assertSame('/tmp/routes/sql-to-eloquent.php', $arquivo->caminho);
        $this->assertStringContainsString('use App\\Http\\Controllers\\UserController;', $arquivo->conteudo);
        $this->assertStringContainsString('use App\\Http\\Controllers\\PedidoController;', $arquivo->conteudo);
        $this->assertStringContainsString(
            "Route::apiResource('usuarios', UserController::class)->parameters(['usuarios' => 'user']);",
            $arquivo->conteudo,
        );
        $this->assertStringContainsString(
            "Route::apiResource('pedidos', PedidoController::class);",
            $arquivo->conteudo,
        );
        $this->assertStringNotContainsString('auth:sanctum', $arquivo->conteudo);
        $this->assertTrue($this->sintaxeValida($arquivo->conteudo));
    }

    private function sintaxeValida(string $conteudo): bool
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'rota_').'.php';
        file_put_contents($arquivo, $conteudo);
        exec('php -l '.escapeshellarg($arquivo).' 2>&1', $saida, $codigo);
        unlink($arquivo);

        return $codigo === 0;
    }
}
