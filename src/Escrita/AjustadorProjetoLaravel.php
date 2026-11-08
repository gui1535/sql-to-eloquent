<?php

namespace SqlToEloquent\Escrita;

use Illuminate\Console\Command;

class AjustadorProjetoLaravel
{
    public function garantirApiPhp(Command $comando): string
    {
        $api = base_path('routes/api.php');

        if (! is_file($api)) {
            file_put_contents($api, <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

PHP);
            $comando->info('Criado routes/api.php.');
            $this->habilitarApiNoBootstrap($comando);
        }

        return $api;
    }

    public function registrarRequire(Command $comando, string $api): void
    {
        $conteudo = (string) file_get_contents($api);

        if (str_contains($conteudo, 'sql-to-eloquent.php')) {
            return;
        }

        $require = "require base_path('routes/sql-to-eloquent.php');";
        file_put_contents($api, rtrim($conteudo).PHP_EOL.PHP_EOL.$require.PHP_EOL);
        $comando->info('Rotas registradas em routes/api.php.');
    }

    /**
     * Em --sem-interacao aplica os ajustes sem perguntar.
     *
     * @return array{migrationUsers: bool, factory: bool}
     */
    public function ajustarUsersPadrao(Command $comando, ?string $tabelaUsers, bool $interativo): array
    {
        if ($tabelaUsers === null) {
            return ['migrationUsers' => false, 'factory' => false];
        }

        return [
            'migrationUsers' => $this->desativarMigrationUsersPadrao($comando, $interativo),
            'factory' => $this->ajustarUserFactory($comando, $interativo),
        ];
    }

    private function habilitarApiNoBootstrap(Command $comando): void
    {
        $bootstrap = base_path('bootstrap/app.php');

        if (! is_file($bootstrap)) {
            return;
        }

        $conteudo = (string) file_get_contents($bootstrap);

        if (str_contains($conteudo, '// api: ')) {
            file_put_contents($bootstrap, str_replace('// api: ', 'api: ', $conteudo));
            $comando->info('API habilitada em bootstrap/app.php.');

            return;
        }

        if (str_contains($conteudo, "web: __DIR__.'/../routes/web.php',")
            && ! str_contains($conteudo, 'routes/api.php')
        ) {
            file_put_contents(
                $bootstrap,
                str_replace(
                    "web: __DIR__.'/../routes/web.php',",
                    "web: __DIR__.'/../routes/web.php',".PHP_EOL."        api: __DIR__.'/../routes/api.php',",
                    $conteudo,
                ),
            );
            $comando->info('API registrada em bootstrap/app.php.');
        }
    }

    private function desativarMigrationUsersPadrao(Command $comando, bool $interativo): bool
    {
        $arquivos = glob(database_path('migrations/*create_users_table.php')) ?: [];
        $ajustou = false;

        foreach ($arquivos as $arquivo) {
            $nome = basename($arquivo);

            if (str_starts_with($nome, '2020_01_01_')) {
                continue;
            }

            $destino = $arquivo.'.bak';

            if (is_file($destino)) {
                $ajustou = true;
                continue;
            }

            if ($interativo && ! $comando->confirm("Desativar migration padrão {$nome} (renomear para .bak)?", true)) {
                continue;
            }

            rename($arquivo, $destino);
            $comando->info("Migration padrão movida para {$nome}.bak");
            $ajustou = true;
        }

        if ($ajustou) {
            return true;
        }

        return (glob(database_path('migrations/*create_users_table.php.bak')) ?: []) !== [];
    }

    private function ajustarUserFactory(Command $comando, bool $interativo): bool
    {
        $factory = database_path('factories/UserFactory.php');

        if (! is_file($factory)) {
            return true;
        }

        $conteudo = (string) file_get_contents($factory);

        if (str_contains($conteudo, "'nome'") && str_contains($conteudo, "'senha'")) {
            return true;
        }

        if ($interativo && ! $comando->confirm('Ajustar UserFactory.php para os campos gerados (nome/senha)?', true)) {
            return false;
        }

        $novo = preg_replace("/'name'\s*=>/", "'nome' =>", $conteudo) ?? $conteudo;
        $novo = preg_replace("/'password'\s*=>/", "'senha' =>", $novo) ?? $novo;

        if ($novo === $conteudo) {
            $comando->warn('UserFactory.php não foi alterada automaticamente. Revise manualmente.');

            return false;
        }

        file_put_contents($factory, $novo);
        $comando->info('UserFactory.php ajustada (name→nome, password→senha).');

        return true;
    }
}
