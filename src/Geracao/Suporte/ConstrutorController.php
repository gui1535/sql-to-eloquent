<?php

namespace SqlToEloquent\Geracao\Suporte;

use SqlToEloquent\Dominio\Relacionamento;
use SqlToEloquent\Dominio\Tabela;

class ConstrutorController
{
    public function __construct(
        private readonly NomeadorModel $nomeador = new NomeadorModel,
        private readonly ConstrutorRequest $construtorRequest = new ConstrutorRequest,
    ) {
    }

    public function nomeClasse(string $tabela, ?string $tabelaUsers = null): string
    {
        return $this->nomeador->classe($tabela, $tabelaUsers).'Controller';
    }

    public function nomeArquivo(string $tabela, ?string $tabelaUsers = null): string
    {
        return $this->nomeClasse($tabela, $tabelaUsers).'.php';
    }

    /**
     * @param  list<Relacionamento>  $relacionamentos
     */
    public function montar(
        Tabela $tabela,
        string $namespaceControllers,
        string $namespaceModels,
        string $namespaceRequests,
        ?string $tabelaUsers = null,
        array $relacionamentos = [],
    ): string {
        $modelo = $this->nomeador->classe($tabela->nome, $tabelaUsers);
        $controller = $this->nomeClasse($tabela->nome, $tabelaUsers);
        $variavel = $this->nomeador->variavel($tabela->nome, $tabelaUsers);
        $store = $this->construtorRequest->nomeClasseStore($tabela->nome, $tabelaUsers);
        $update = $this->construtorRequest->nomeClasseUpdate($tabela->nome, $tabelaUsers);
        $with = $this->metodosEager($relacionamentos);
        $withIndex = $with === [] ? '' : '->with(['.$this->listaStrings($with).'])';
        $withShow = $with === []
            ? "return \${$variavel};"
            : "return \${$variavel}->load([".$this->listaStrings($with).']);';

        return <<<PHP
<?php

namespace {$namespaceControllers};

use {$namespaceModels}\\{$modelo};
use {$namespaceRequests}\\{$store};
use {$namespaceRequests}\\{$update};

class {$controller} extends Controller
{
    public function index()
    {
        return {$modelo}::query(){$withIndex}->paginate();
    }

    public function store({$store} \$request)
    {
        \$registro = {$modelo}::query()->create(\$request->validated());

        return response()->json(\$registro, 201);
    }

    public function show({$modelo} \${$variavel})
    {
        {$withShow}
    }

    public function update({$update} \$request, {$modelo} \${$variavel})
    {
        \${$variavel}->update(\$request->validated());

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

    /**
     * @param  list<Relacionamento>  $relacionamentos
     * @return list<string>
     */
    private function metodosEager(array $relacionamentos): array
    {
        $metodos = [];

        foreach ($relacionamentos as $rel) {
            if (in_array($rel->tipo, ['belongsTo', 'belongsToMany'], true)) {
                $metodos[] = $rel->metodo;
            }
        }

        return array_values(array_unique($metodos));
    }

    /** @param list<string> $valores */
    private function listaStrings(array $valores): string
    {
        return implode(', ', array_map(
            fn (string $valor) => "'{$valor}'",
            $valores,
        ));
    }
}
