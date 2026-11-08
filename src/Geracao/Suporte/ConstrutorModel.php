<?php

namespace SqlToEloquent\Geracao\Suporte;

use SqlToEloquent\Dominio\Coluna;
use SqlToEloquent\Dominio\Relacionamento;
use SqlToEloquent\Dominio\Tabela;

class ConstrutorModel
{
    public function __construct(
        private readonly NomeadorModel $nomeador = new NomeadorModel,
    ) {
    }

    public function nomeArquivo(string $tabela, ?string $tabelaUsers = null): string
    {
        return $this->nomeador->classe($tabela, $tabelaUsers).'.php';
    }

    /**
     * @param  list<Relacionamento>  $relacionamentos
     */
    public function montar(
        Tabela $tabela,
        string $namespace,
        array $relacionamentos = [],
        ?string $tabelaUsers = null,
        bool $softDelete = false,
    ): string {
        $classe = $this->nomeador->classe($tabela->nome, $tabelaUsers);
        $ehUsers = $this->nomeador->ehTabelaUsers($tabela->nome, $tabelaUsers);
        $corpo = $this->corpo($tabela, $relacionamentos, $ehUsers, $softDelete);
        $imports = $this->montarImports($relacionamentos, $softDelete);
        $blocoImports = $imports === '' ? '' : $imports."\n";
        $extends = $ehUsers ? 'Authenticatable' : 'Model';
        $traits = $this->traits($ehUsers, $softDelete);

        $useBase = $ehUsers
            ? "use Illuminate\\Foundation\\Auth\\User as Authenticatable;\nuse Illuminate\\Notifications\\Notifiable;"
            : 'use Illuminate\\Database\\Eloquent\\Model;';

        return <<<PHP
<?php

namespace {$namespace};

{$useBase}
{$blocoImports}
class {$classe} extends {$extends}
{{$traits}
{$corpo}
}

PHP;
    }

    private function traits(bool $ehUsers, bool $softDelete): string
    {
        $usados = [];

        if ($ehUsers) {
            $usados[] = 'Notifiable';
        }

        if ($softDelete) {
            $usados[] = 'SoftDeletes';
        }

        if ($usados === []) {
            return '';
        }

        return "\n    use ".implode(', ', $usados).";\n";
    }

    /**
     * @param  list<Relacionamento>  $relacionamentos
     */
    private function montarImports(array $relacionamentos, bool $softDelete): string
    {
        $linhas = [];

        if ($softDelete) {
            $linhas[] = 'use Illuminate\\Database\\Eloquent\\SoftDeletes;';
        }

        $tipos = [];

        foreach ($relacionamentos as $rel) {
            $tipos[$rel->tipo] = match ($rel->tipo) {
                'belongsTo' => 'use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;',
                'hasOne' => 'use Illuminate\\Database\\Eloquent\\Relations\\HasOne;',
                default => 'use Illuminate\\Database\\Eloquent\\Relations\\HasMany;',
            };
        }

        ksort($tipos);

        return implode("\n", [...$linhas, ...array_values($tipos)]);
    }

    /**
     * @param  list<Relacionamento>  $relacionamentos
     */
    private function corpo(Tabela $tabela, array $relacionamentos, bool $ehUsers, bool $softDelete): string
    {
        $blocos = [];

        $blocos[] = "    protected \$table = '{$tabela->nome}';";

        $pk = $tabela->chavePrimaria;
        if (count($pk) === 1 && $pk[0] !== 'id') {
            $blocos[] = "    protected \$primaryKey = '{$pk[0]}';";
        }

        if (count($pk) === 1) {
            $colunaPk = $tabela->coluna($pk[0]);

            if ($colunaPk !== null && ! $colunaPk->autoIncremento) {
                $blocos[] = '    public $incrementing = false;';
            }

            if ($colunaPk !== null && in_array($colunaPk->tipo, ['varchar', 'char', 'uuid', 'string'], true)) {
                $blocos[] = "    protected \$keyType = 'string';";
            }
        }

        foreach ($this->configTimestamps($tabela) as $linha) {
            $blocos[] = $linha;
        }

        $fillable = $this->fillable($tabela, $softDelete);
        if ($fillable !== []) {
            $blocos[] = $this->arrayPropriedade('fillable', $fillable);
        }

        $hidden = $this->hidden($tabela);
        if ($hidden !== []) {
            $blocos[] = $this->arrayPropriedade('hidden', $hidden);
        }

        $casts = $this->casts($tabela, $ehUsers);
        if ($casts !== []) {
            $blocos[] = $this->arrayAssociativo('casts', $casts);
        }

        foreach ($relacionamentos as $rel) {
            $blocos[] = $this->metodoRelacionamento($rel);
        }

        return implode("\n\n", $blocos);
    }

    /** @return list<string> */
    private function configTimestamps(Tabela $tabela): array
    {
        if ($tabela->coluna('created_at') !== null && $tabela->coluna('updated_at') !== null) {
            return [];
        }

        if ($tabela->coluna('criado_em') !== null && $tabela->coluna('atualizado_em') !== null) {
            return [
                "    public const CREATED_AT = 'criado_em';",
                "    public const UPDATED_AT = 'atualizado_em';",
            ];
        }

        return ['    public $timestamps = false;'];
    }

    /** @return list<string> */
    private function fillable(Tabela $tabela, bool $softDelete): array
    {
        $excluir = ['id', 'created_at', 'updated_at', 'criado_em', 'atualizado_em', 'remember_token', 'deleted_at'];
        $campos = [];

        foreach ($tabela->colunas as $coluna) {
            if (in_array($coluna->nome, $excluir, true)) {
                continue;
            }

            if ($softDelete && $coluna->nome === 'deleted_at') {
                continue;
            }

            if ($tabela->chavePrimaria === [$coluna->nome] && $coluna->autoIncremento) {
                continue;
            }

            $campos[] = $coluna->nome;
        }

        return $campos;
    }

    /** @return list<string> */
    private function hidden(Tabela $tabela): array
    {
        $hidden = [];

        foreach (['password', 'senha', 'remember_token'] as $campo) {
            if ($tabela->coluna($campo) !== null) {
                $hidden[] = $campo;
            }
        }

        return $hidden;
    }

    /** @return array<string, string> */
    private function casts(Tabela $tabela, bool $ehUsers): array
    {
        $casts = [];

        foreach ($tabela->colunas as $coluna) {
            $cast = $this->castColuna($coluna, $ehUsers);

            if ($cast !== null) {
                $casts[$coluna->nome] = $cast;
            }
        }

        return $casts;
    }

    private function castColuna(Coluna $coluna, bool $ehUsers): ?string
    {
        if ($ehUsers && in_array($coluna->nome, ['password', 'senha'], true)) {
            return 'hashed';
        }

        if (
            in_array($coluna->tipo, ['boolean', 'bool'], true)
            || ($coluna->tipo === 'tinyint' && $coluna->tamanho === 1)
        ) {
            return 'boolean';
        }

        return match ($coluna->tipo) {
            'decimal', 'numeric' => 'decimal:'.($coluna->escala ?? 2),
            'float', 'double', 'real' => 'float',
            'json', 'jsonb' => 'array',
            'date' => 'date',
            'datetime', 'timestamp' => 'datetime',
            default => null,
        };
    }

    /** @param list<string> $valores */
    private function arrayPropriedade(string $nome, array $valores): string
    {
        $linhas = array_map(
            fn (string $valor) => "        '{$valor}',",
            $valores,
        );

        return "    protected \${$nome} = [\n".implode("\n", $linhas)."\n    ];";
    }

    /** @param array<string, string> $valores */
    private function arrayAssociativo(string $nome, array $valores): string
    {
        $linhas = [];

        foreach ($valores as $chave => $valor) {
            $linhas[] = "        '{$chave}' => '{$valor}',";
        }

        return "    protected \${$nome} = [\n".implode("\n", $linhas)."\n    ];";
    }

    private function metodoRelacionamento(Relacionamento $rel): string
    {
        $retorno = match ($rel->tipo) {
            'belongsTo' => 'BelongsTo',
            'hasOne' => 'HasOne',
            default => 'HasMany',
        };

        return <<<PHP
    public function {$rel->metodo}(): {$retorno}
    {
        return \$this->{$rel->tipo}({$rel->modelo}::class, '{$rel->chaveEstrangeira}', '{$rel->chaveDona}');
    }
PHP;
    }
}
