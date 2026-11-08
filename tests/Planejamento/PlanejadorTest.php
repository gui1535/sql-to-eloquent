<?php

namespace SqlToEloquent\Tests\Planejamento;

use PHPUnit\Framework\TestCase;
use SqlToEloquent\Analise\AnalisadorMysql;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\Orquestrador;
use SqlToEloquent\Planejamento\OpcoesGeracao;
use SqlToEloquent\Planejamento\Planejador;

class PlanejadorTest extends TestCase
{
    public function test_filtra_tabelas_no_plano(): void
    {
        $schema = (new AnalisadorMysql)->analisarArquivo(
            __DIR__.'/../fixtures/schema_basico.sql'
        );

        $plano = (new Planejador(new Orquestrador))->montar(
            $schema,
            new OpcoesGeracao(
                artefatos: [TipoArtefato::Model],
                caminhos: ['models' => '/tmp/Models'],
                tabelas: ['usuarios', 'pedidos'],
            ),
        );

        $tabelas = array_map(fn ($arquivo) => $arquivo->tabela, $plano->arquivos);

        $this->assertSame(['usuarios', 'pedidos'], $tabelas);
        $this->assertArrayHasKey('models', $plano->porTipo());
    }

    public function test_nao_marca_conflito_nos_arquivos_da_tabela_users(): void
    {
        $dir = sys_get_temp_dir().'/sql_to_eloquent_users_'.uniqid();
        mkdir($dir, 0755, true);
        file_put_contents($dir.'/User.php', '<?php // padrao laravel');
        file_put_contents($dir.'/Pedido.php', '<?php // existente');

        $schema = (new AnalisadorMysql)->analisarArquivo(
            __DIR__.'/../fixtures/schema_basico.sql'
        );

        $plano = (new Planejador(new Orquestrador))->montar(
            $schema,
            new OpcoesGeracao(
                artefatos: [TipoArtefato::Model],
                caminhos: ['models' => $dir],
                tabelas: ['usuarios', 'pedidos'],
                tabelaUsers: 'usuarios',
            ),
        );

        $porTabela = [];
        foreach ($plano->arquivos as $arquivo) {
            $porTabela[$arquivo->tabela] = $arquivo;
        }

        $this->assertFalse($porTabela['usuarios']->conflito);
        $this->assertTrue($porTabela['pedidos']->conflito);

        array_map('unlink', glob($dir.'/*') ?: []);
        rmdir($dir);
    }

    public function test_filtra_tabelas_crud_em_controllers_e_requests(): void
    {
        $schema = (new AnalisadorMysql)->analisarArquivo(
            __DIR__.'/../fixtures/schema_basico.sql'
        );

        $plano = (new Planejador(new Orquestrador))->montar(
            $schema,
            new OpcoesGeracao(
                artefatos: [TipoArtefato::Model, TipoArtefato::Controller],
                caminhos: [
                    'models' => '/tmp/Models',
                    'controllers' => '/tmp/Controllers',
                    'requests' => '/tmp/Requests',
                ],
                tabelasCrud: ['pedidos'],
            ),
        );

        $models = array_map(
            fn ($arquivo) => $arquivo->tabela,
            $plano->porTipo()['models'] ?? [],
        );
        $controllers = array_map(
            fn ($arquivo) => $arquivo->tabela,
            $plano->porTipo()['controllers'] ?? [],
        );
        $requests = array_map(
            fn ($arquivo) => $arquivo->tabela,
            $plano->porTipo()['requests'] ?? [],
        );

        $this->assertContains('usuarios', $models);
        $this->assertContains('pedidos', $models);
        $this->assertSame(['pedidos'], $controllers);
        $this->assertSame(['pedidos', 'pedidos'], $requests);
    }
}
