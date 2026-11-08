<?php

namespace SqlToEloquent\Tests\Geracao;

use PHPUnit\Framework\TestCase;
use SqlToEloquent\Analise\AnalisadorMysql;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\Geradores\GeradorRequest;
use SqlToEloquent\Planejamento\OpcoesGeracao;

class GeradorRequestTest extends TestCase
{
    public function test_gera_store_e_update_requests(): void
    {
        $schema = (new AnalisadorMysql)->analisarArquivo(
            __DIR__.'/../fixtures/schema_basico.sql'
        );

        $arquivos = (new GeradorRequest)->gerar(
            $schema,
            new OpcoesGeracao(
                artefatos: [TipoArtefato::Request],
                caminhos: ['requests' => '/tmp/Requests'],
                tabelaUsers: 'usuarios',
            ),
        );

        $this->assertNotEmpty($arquivos);

        $storeUser = null;
        $updateUser = null;
        $updatePedido = null;

        foreach ($arquivos as $arquivo) {
            $this->assertTrue($this->sintaxeValida($arquivo->conteudo), $arquivo->caminho);

            if (str_contains($arquivo->caminho, 'StoreUserRequest.php')) {
                $storeUser = $arquivo->conteudo;
            }

            if (str_contains($arquivo->caminho, 'UpdateUserRequest.php')) {
                $updateUser = $arquivo->conteudo;
            }

            if (str_contains($arquivo->caminho, 'UpdatePedidoRequest.php')) {
                $updatePedido = $arquivo->conteudo;
            }
        }

        $this->assertNotNull($storeUser);
        $this->assertStringContainsString('class StoreUserRequest extends FormRequest', $storeUser);
        $this->assertStringContainsString("'email' => 'required|string|max:255|email|unique:usuarios,email'", $storeUser);

        $this->assertNotNull($updateUser);
        $this->assertStringContainsString('use Illuminate\\Validation\\Rule;', $updateUser);
        $this->assertStringContainsString("Rule::unique('usuarios', 'email')->ignore(\$this->route('user'))", $updateUser);

        $this->assertNotNull($updatePedido);
        $this->assertStringContainsString("'total' => 'sometimes|numeric'", $updatePedido);
        $this->assertStringContainsString("'usuario_id' => 'sometimes|integer|exists:usuarios,id'", $updatePedido);

        $storePedido = null;
        foreach ($arquivos as $arquivo) {
            if (str_contains($arquivo->caminho, 'StorePedidoRequest.php')) {
                $storePedido = $arquivo->conteudo;
            }
        }

        $this->assertNotNull($storePedido);
        $this->assertStringContainsString("'usuario_id' => 'required|integer|exists:usuarios,id'", $storePedido);
    }

    private function sintaxeValida(string $conteudo): bool
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'req_').'.php';
        file_put_contents($arquivo, $conteudo);
        exec('php -l '.escapeshellarg($arquivo).' 2>&1', $saida, $codigo);
        unlink($arquivo);

        return $codigo === 0;
    }
}
