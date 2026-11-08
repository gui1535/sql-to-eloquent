<?php

namespace SqlToEloquent\Tests\Geracao;

use PHPUnit\Framework\TestCase;
use SqlToEloquent\Analise\AnalisadorMysql;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\Geradores\GeradorAuth;
use SqlToEloquent\Geracao\Geradores\GeradorModel;
use SqlToEloquent\Geracao\Geradores\GeradorRota;
use SqlToEloquent\Planejamento\OpcoesGeracao;

class GeradorAuthTest extends TestCase
{
    public function test_gera_auth_controller_requests_e_rotas_sanctum(): void
    {
        $schema = (new AnalisadorMysql)->analisarArquivo(
            __DIR__.'/../fixtures/schema_basico.sql'
        );

        $opcoes = new OpcoesGeracao(
            artefatos: [TipoArtefato::Auth, TipoArtefato::Rota, TipoArtefato::Model, TipoArtefato::Controller],
            caminhos: [
                'controllers' => '/tmp/Controllers',
                'requests' => '/tmp/Requests',
                'rotas' => '/tmp/routes',
                'models' => '/tmp/Models',
            ],
            tabelaUsers: 'usuarios',
            tabelasCrud: ['usuarios', 'pedidos'],
            campoEmail: 'email',
            campoSenha: 'senha',
        );

        $auth = (new GeradorAuth)->gerar($schema, $opcoes);
        $this->assertCount(3, $auth);

        $controller = $auth[0]->conteudo;
        $this->assertStringContainsString('class AuthController extends Controller', $controller);
        $this->assertStringContainsString('function register(RegisterRequest $request)', $controller);
        $this->assertStringContainsString('function login(LoginRequest $request)', $controller);
        $this->assertStringContainsString("Auth::attempt(\$credenciais)", $controller);
        $this->assertStringContainsString("createToken('api')", $controller);
        $this->assertTrue($this->sintaxeValida($controller));

        $login = $auth[1]->conteudo;
        $this->assertStringContainsString("'email' => 'required|email'", $login);
        $this->assertStringContainsString("'senha' => 'required|string'", $login);

        $register = $auth[2]->conteudo;
        $this->assertStringContainsString('class RegisterRequest extends FormRequest', $register);
        $this->assertStringContainsString("'senha' => '", $register);
        $this->assertStringContainsString('min:8', $register);
        $this->assertStringContainsString('required', $register);

        $user = null;
        foreach ((new GeradorModel)->gerar($schema, $opcoes) as $arquivo) {
            if ($arquivo->tabela === 'usuarios') {
                $user = $arquivo->conteudo;
            }
        }

        $this->assertNotNull($user);
        $this->assertStringContainsString('HasApiTokens', $user);
        $this->assertStringContainsString('Notifiable', $user);
        $this->assertStringContainsString('use Laravel\\Sanctum\\HasApiTokens;', $user);

        $rota = (new GeradorRota)->gerar($schema, $opcoes)[0]->conteudo;
        $this->assertStringContainsString("Route::prefix('auth')", $rota);
        $this->assertStringContainsString("Route::post('login', [AuthController::class, 'login']);", $rota);
        $this->assertStringContainsString("Route::middleware('auth:sanctum')->group", $rota);
        $this->assertStringContainsString('PedidoController::class', $rota);
        $this->assertTrue($this->sintaxeValida($rota));
    }

    private function sintaxeValida(string $conteudo): bool
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'auth_').'.php';
        file_put_contents($arquivo, $conteudo);
        exec('php -l '.escapeshellarg($arquivo).' 2>&1', $saida, $codigo);
        unlink($arquivo);

        return $codigo === 0;
    }
}
