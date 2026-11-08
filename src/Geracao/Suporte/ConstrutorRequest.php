<?php

namespace SqlToEloquent\Geracao\Suporte;

use SqlToEloquent\Dominio\Tabela;

class ConstrutorRequest
{
    public function __construct(
        private readonly NomeadorModel $nomeador = new NomeadorModel,
        private readonly MontadorRegras $montadorRegras = new MontadorRegras,
    ) {
    }

    public function nomeClasseStore(string $tabela, ?string $tabelaUsers = null): string
    {
        return 'Store'.$this->nomeador->classe($tabela, $tabelaUsers).'Request';
    }

    public function nomeClasseUpdate(string $tabela, ?string $tabelaUsers = null): string
    {
        return 'Update'.$this->nomeador->classe($tabela, $tabelaUsers).'Request';
    }

    public function nomeArquivoStore(string $tabela, ?string $tabelaUsers = null): string
    {
        return $this->nomeClasseStore($tabela, $tabelaUsers).'.php';
    }

    public function nomeArquivoUpdate(string $tabela, ?string $tabelaUsers = null): string
    {
        return $this->nomeClasseUpdate($tabela, $tabelaUsers).'.php';
    }

    public function montarStore(Tabela $tabela, string $namespace, ?string $tabelaUsers = null): string
    {
        $classe = $this->nomeClasseStore($tabela->nome, $tabelaUsers);
        $regras = $this->exportarRegrasStore($this->montadorRegras->regras($tabela));

        return <<<PHP
<?php

namespace {$namespace};

use Illuminate\Foundation\Http\FormRequest;

class {$classe} extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return {$regras};
    }
}

PHP;
    }

    public function montarUpdate(Tabela $tabela, string $namespace, ?string $tabelaUsers = null): string
    {
        $classe = $this->nomeClasseUpdate($tabela->nome, $tabelaUsers);
        $variavel = $this->nomeador->variavel($tabela->nome, $tabelaUsers);
        $unicos = $this->montadorRegras->unicos($tabela);
        $usaRule = $unicos !== [];
        $regras = $this->exportarRegrasUpdate(
            $this->montadorRegras->regras($tabela),
            $unicos,
            $tabela->nome,
            $variavel,
        );

        $importRule = $usaRule ? "\nuse Illuminate\\Validation\\Rule;" : '';

        return <<<PHP
<?php

namespace {$namespace};

use Illuminate\Foundation\Http\FormRequest;{$importRule}

class {$classe} extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return {$regras};
    }
}

PHP;
    }

    /** @param array<string, list<string>> $regras */
    private function exportarRegrasStore(array $regras): string
    {
        if ($regras === []) {
            return '[]';
        }

        $linhas = [];

        foreach ($regras as $campo => $lista) {
            $linhas[] = "            '{$campo}' => '".implode('|', $lista)."',";
        }

        return "[\n".implode("\n", $linhas)."\n        ]";
    }

    /**
     * @param  array<string, list<string>>  $regras
     * @param  list<string>  $unicos
     */
    private function exportarRegrasUpdate(array $regras, array $unicos, string $tabela, string $variavel): string
    {
        if ($regras === []) {
            return '[]';
        }

        $linhas = [];

        foreach ($regras as $campo => $lista) {
            if (in_array($campo, $unicos, true)) {
                $base = array_values(array_filter(
                    $lista,
                    fn (string $regra) => ! str_starts_with($regra, 'unique:'),
                ));
                $itens = array_map(
                    fn (string $regra) => "'{$regra}'",
                    $base,
                );
                $itens[] = "Rule::unique('{$tabela}', '{$campo}')->ignore(\$this->route('{$variavel}'))";
                $linhas[] = "            '{$campo}' => [".implode(', ', $itens).'],';
                continue;
            }

            $linhas[] = "            '{$campo}' => '".implode('|', $lista)."',";
        }

        return "[\n".implode("\n", $linhas)."\n        ]";
    }
}
