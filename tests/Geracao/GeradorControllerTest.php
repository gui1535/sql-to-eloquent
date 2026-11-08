<?php

namespace SqlToEloquent\Tests\Geracao;

use PHPUnit\Framework\TestCase;
use SqlToEloquent\Analise\AnalisadorMysql;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\Geradores\GeradorController;
use SqlToEloquent\Planejamento\OpcoesGeracao;

class GeradorControllerTest extends TestCase
{
    public function test_gera_controller_crud_com_form_requests(): void
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
                namespaceRequests: 'App\\Http\\Requests',
                tabelaUsers: 'usuarios',
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
        $this->assertStringContainsString('use App\\Http\\Requests\\StorePedidoRequest;', $pedido);
        $this->assertStringContainsString('use App\\Http\\Requests\\UpdatePedidoRequest;', $pedido);
        $this->assertStringContainsString('public function store(StorePedidoRequest $request)', $pedido);
        $this->assertStringContainsString('public function update(UpdatePedidoRequest $request, Pedido $pedido)', $pedido);
        $this->assertStringContainsString('$request->validated()', $pedido);
        $this->assertStringContainsString("->with(['usuario'])->paginate()", $pedido);
        $this->assertStringContainsString("return \$pedido->load(['usuario']);", $pedido);

        $usuario = $porTabela['usuarios'];
        $this->assertStringContainsString('class UserController extends Controller', $usuario);
        $this->assertStringContainsString('use App\\Models\\User;', $usuario);
        $this->assertStringContainsString('public function show(User $user)', $usuario);
        $this->assertStringContainsString('StoreUserRequest', $usuario);
        $this->assertStringContainsString('UpdateUserRequest', $usuario);
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
