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

    public function nomeArquivo(string $tabela): string
    {
        return $this->nomeador->classe($tabela).'.php';
    }

    /**
     * @param  list<Relacionamento>  $relacionamentos
     */
    public function montar(Tabela $tabela, string $namespace, array $relacionamentos = []): string
    {
        $classe = $this->nomeador->classe($tabela->nome);
        $corpo = $this->corpo($tabela, $relacionamentos);
        $imports = $this->montarImports($relacionamentos);
        $blocoImports = $imports === '' ? '' : $imports."\n";

        return <<<PHP
<?php

namespace {$namespace};

use Illuminate\Database\Eloquent\Model;
{$blocoImports}
class {$classe} extends Model
{
{$corpo}
}

PHP;
    }

    /**
     * @param  list<Relacionamento>  $relacionamentos
     */
    private function montarImports(array $relacionamentos): string
    {
        $tipos = [];

        foreach ($relacionamentos as $rel) {
            $tipos[$rel->tipo] = match ($rel->tipo) {
                'belongsTo' => 'use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;',
                'hasOne' => 'use Illuminate\\Database\\Eloquent\\Relations\\HasOne;',
                default => 'use Illuminate\\Database\\Eloquent\\Relations\\HasMany;',
            };
        }

        ksort($tipos);

        return implode("\n", array_values($tipos));
    }

    /**
     * @param  list<Relacionamento>  $relacionamentos
     */
    private function corpo(Tabela $tabela, array $relacionamentos): string
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

        $fillable = $this->fillable($tabela);
        if ($fillable !== []) {
            $blocos[] = $this->arrayPropriedade('fillable', $fillable);
        }

        $hidden = $this->hidden($tabela);
        if ($hidden !== []) {
            $blocos[] = $this->arrayPropriedade('hidden', $hidden);
        }

        $casts = $this->casts($tabela);
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
    private function fillable(Tabela $tabela): array
    {
        $excluir = ['id', 'created_at', 'updated_at', 'criado_em', 'atualizado_em', 'remember_token'];
        $campos = [];

        foreach ($tabela->colunas as $coluna) {
            if (in_array($coluna->nome, $excluir, true)) {
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
    private function casts(Tabela $tabela): array
    {
        $casts = [];

        foreach ($tabela->colunas as $coluna) {
            $cast = $this->castColuna($coluna);

            if ($cast !== null) {
                $casts[$coluna->nome] = $cast;
            }
        }

        return $casts;
    }

    private function castColuna(Coluna $coluna): ?string
    {
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
