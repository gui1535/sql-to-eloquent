<?php

namespace SqlToEloquent\Geracao\Suporte;

use SqlToEloquent\Dominio\Tabela;

class ConstrutorRota
{
    public function __construct(
        private readonly NomeadorModel $nomeador = new NomeadorModel,
        private readonly ConstrutorController $construtorController = new ConstrutorController,
    ) {
    }

    /**
     * @param  list<Tabela>  $tabelas
     */
    public function montar(
        array $tabelas,
        string $namespaceControllers,
        ?string $tabelaUsers = null,
        bool $authApi = false,
    ): string {
        $uses = [];
        $rotasCrud = [];

        if ($authApi) {
            $uses['AuthController'] = "use {$namespaceControllers}\\AuthController;";
        }

        foreach ($tabelas as $tabela) {
            $controller = $this->construtorController->nomeClasse($tabela->nome, $tabelaUsers);
            $recurso = $this->nomeador->recurso($tabela->nome);
            $variavel = $this->nomeador->variavel($tabela->nome, $tabelaUsers);
            $parametro = $this->nomeador->singular($tabela->nome);

            $uses[$controller] = "use {$namespaceControllers}\\{$controller};";

            if ($parametro !== $variavel) {
                $rotasCrud[] = "    Route::apiResource('{$recurso}', {$controller}::class)"
                    ."->parameters(['{$recurso}' => '{$variavel}']);";
            } else {
                $rotasCrud[] = "    Route::apiResource('{$recurso}', {$controller}::class);";
            }
        }

        ksort($uses);
        $blocoUses = implode("\n", array_values($uses));
        $partes = [];

        if ($authApi) {
            $partes[] = <<<'PHP'
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});
PHP;
        }

        if ($rotasCrud !== []) {
            $blocoCrud = implode("\n", $rotasCrud);

            if ($authApi) {
                $partes[] = "Route::middleware('auth:sanctum')->group(function () {\n{$blocoCrud}\n});";
            } else {
                $partes[] = implode("\n", array_map(
                    fn (string $linha) => ltrim($linha),
                    $rotasCrud,
                ));
            }
        }

        $blocoRotas = implode("\n\n", $partes);

        return <<<PHP
<?php

use Illuminate\Support\Facades\Route;
{$blocoUses}

{$blocoRotas}

PHP;
    }
}
