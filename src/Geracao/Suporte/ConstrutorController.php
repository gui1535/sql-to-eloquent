<?php

namespace SqlToEloquent\Geracao\Suporte;

use Illuminate\Support\Str;
use SqlToEloquent\Dominio\Coluna;
use SqlToEloquent\Dominio\Tabela;

class ConstrutorController
{
    public function __construct(
        private readonly NomeadorModel $nomeador = new NomeadorModel,
    ) {
    }

    public function nomeClasse(string $tabela): string
    {
        return $this->nomeador->classe($tabela).'Controller';
    }

    public function nomeArquivo(string $tabela): string
    {
        return $this->nomeClasse($tabela).'.php';
    }

    public function montar(
        Tabela $tabela,
        string $namespaceControllers,
        string $namespaceModels,
    ): string {
        $modelo = $this->nomeador->classe($tabela->nome);
        $controller = $this->nomeClasse($tabela->nome);
        $variavel = Str::camel($this->nomeador->singular($tabela->nome));
        $regras = $this->regras($tabela);
        $blocoRegras = $this->exportarRegras($regras);

        return <<<PHP
<?php

namespace {$namespaceControllers};

use {$namespaceModels}\\{$modelo};
use Illuminate\Http\Request;

class {$controller} extends Controller
{
    public function index()
    {
        return {$modelo}::query()->paginate();
    }

    public function store(Request \$request)
    {
        \$dados = \$request->validate({$blocoRegras});

        \$registro = {$modelo}::query()->create(\$dados);

        return response()->json(\$registro, 201);
    }

    public function show({$modelo} \${$variavel})
    {
        return \${$variavel};
    }

    public function update(Request \$request, {$modelo} \${$variavel})
    {
        \$dados = \$request->validate({$blocoRegras});

        \${$variavel}->update(\$dados);

        return \${$variavel};
    }

    public function destroy({$modelo} \${$variavel})
    {
        \${$variavel}->delete();

        return response()->noContent();
    }
}

PHP;
    }

    /** @return array<string, list<string>> */
    private function regras(Tabela $tabela): array
    {
        $excluir = ['id', 'created_at', 'updated_at', 'criado_em', 'atualizado_em', 'remember_token'];
        $regras = [];

        foreach ($tabela->colunas as $coluna) {
            if (in_array($coluna->nome, $excluir, true)) {
                continue;
            }

            if ($tabela->chavePrimaria === [$coluna->nome] && $coluna->autoIncremento) {
                continue;
            }

            $regras[$coluna->nome] = $this->regrasColuna($coluna, $tabela);
        }

        return $regras;
    }

    /** @return list<string> */
    private function regrasColuna(Coluna $coluna, Tabela $tabela): array
    {
        $obrigatorio = ! $coluna->nulo && $coluna->padrao === null;
        $regras = [$obrigatorio ? 'required' : 'nullable'];

        if (
            in_array($coluna->tipo, ['boolean', 'bool'], true)
            || ($coluna->tipo === 'tinyint' && $coluna->tamanho === 1)
        ) {
            $regras[] = 'boolean';

            return $regras;
        }

        $regras = array_merge($regras, match ($coluna->tipo) {
            'int', 'integer', 'bigint', 'mediumint', 'smallint', 'tinyint', 'year' => ['integer'],
            'decimal', 'numeric', 'float', 'double', 'real' => ['numeric'],
            'date' => ['date'],
            'datetime', 'timestamp' => ['date'],
            'json', 'jsonb' => ['array'],
            'enum' => ['in:'.implode(',', $coluna->valoresEnum)],
            'varchar', 'char' => array_filter([
                'string',
                $coluna->tamanho !== null ? 'max:'.$coluna->tamanho : null,
            ]),
            default => ['string'],
        });

        if (str_contains($coluna->nome, 'email')) {
            $regras[] = 'email';
        }

        foreach ($tabela->indices as $indice) {
            if ($indice->unico && $indice->colunas === [$coluna->nome]) {
                $regras[] = 'unique:'.$tabela->nome.','.$coluna->nome;
            }
        }

        return array_values(array_unique($regras));
    }

    /** @param array<string, list<string>> $regras */
    private function exportarRegras(array $regras): string
    {
        if ($regras === []) {
            return '[]';
        }

        $linhas = [];

        foreach ($regras as $campo => $lista) {
            $valor = implode('|', $lista);
            $linhas[] = "            '{$campo}' => '{$valor}',";
        }

        return "[\n".implode("\n", $linhas)."\n        ]";
    }
}
