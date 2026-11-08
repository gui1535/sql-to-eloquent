<?php

namespace SqlToEloquent\Tests\Geracao;

use PHPUnit\Framework\TestCase;
use SqlToEloquent\Analise\AnalisadorMysql;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\Geradores\GeradorController;
use SqlToEloquent\Planejamento\OpcoesGeracao;

class GeradorControllerTest extends TestCase
{
    public function test_gera_controller_crud_com_validacao(): void
    {
        $schema = (new AnalisadorMysql)->analisarArquivo(
            __DIR__.'/../fixtures/schema_basico.sql'
        );

        $arquivos = (new GeradorController)->gerar(
            $schema,
            new OpcoesGeracao(
                artefatos: [TipoArtefato::Controller],
                caminhos: ['controllers' => '/tmp/Controllers'],
                namespaceModels: 'App\\Models',
                namespaceControllers: 'App\\Http\\Controllers',
            ),
        );

        $porTabela = [];
        foreach ($arquivos as $arquivo) {
            $porTabela[$arquivo->tabela] = $arquivo->conteudo;
            $this->assertTrue($this->sintaxeValida($arquivo->conteudo), $arquivo->caminho);
        }

        $pedido = $porTabela['pedidos'];
        $this->assertStringContainsString('class PedidoController extends Controller', $pedido);
        $this->assertStringContainsString('use App\\Models\\Pedido;', $pedido);
        $this->assertStringContainsString('public function index()', $pedido);
        $this->assertStringContainsString('public function store(Request $request)', $pedido);
        $this->assertStringContainsString('public function show(Pedido $pedido)', $pedido);
        $this->assertStringContainsString('public function update(Request $request, Pedido $pedido)', $pedido);
        $this->assertStringContainsString('public function destroy(Pedido $pedido)', $pedido);
        $this->assertStringContainsString("'usuario_id' => 'required|integer'", $pedido);
        $this->assertStringContainsString("'total' => 'required|numeric'", $pedido);

        $usuario = $porTabela['usuarios'];
        $this->assertStringContainsString("'email' => 'required|string|max:255|email|unique:usuarios,email'", $usuario);
        $this->assertStringContainsString("'ativo' => 'nullable|boolean'", $usuario);
    }

    private function sintaxeValida(string $conteudo): bool
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'ctrl_').'.php';
        file_put_contents($arquivo, $conteudo);
        exec('php -l '.escapeshellarg($arquivo).' 2>&1', $saida, $codigo);
        unlink($arquivo);

        return $codigo === 0;
    }
}
