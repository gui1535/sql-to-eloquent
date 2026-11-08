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
        ?string $campoEmail = null,
        ?string $campoSenha = null,
        bool $authApi = false,
    ): string {
        $classe = $this->nomeador->classe($tabela->nome, $tabelaUsers);
        $ehUsers = $this->nomeador->ehTabelaUsers($tabela->nome, $tabelaUsers);
        $usaSanctum = $ehUsers && $authApi;
        $corpo = $this->corpo(
            $tabela,
            $relacionamentos,
            $ehUsers,
            $softDelete,
            $campoEmail,
            $campoSenha,
        );
        $imports = $this->montarImports($relacionamentos, $softDelete, $usaSanctum);
        $blocoImports = $imports === '' ? '' : $imports."\n";
        $extends = $ehUsers ? 'Authenticatable' : 'Model';
        $traits = $this->traits($ehUsers, $softDelete, $usaSanctum);

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

    private function traits(bool $ehUsers, bool $softDelete, bool $usaSanctum): string
    {
        $usados = [];

        if ($ehUsers) {
            $usados[] = 'Notifiable';
        }

        if ($usaSanctum) {
            $usados[] = 'HasApiTokens';
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
    private function montarImports(array $relacionamentos, bool $softDelete, bool $usaSanctum): string
    {
        $linhas = [];

        if ($usaSanctum) {
            $linhas[] = 'use Laravel\\Sanctum\\HasApiTokens;';
        }

        if ($softDelete) {
            $linhas[] = 'use Illuminate\\Database\\Eloquent\\SoftDeletes;';
        }

        $tipos = [];

        foreach ($relacionamentos as $rel) {
            $tipos[$rel->tipo] = match ($rel->tipo) {
                'belongsTo' => 'use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;',
                'hasOne' => 'use Illuminate\\Database\\Eloquent\\Relations\\HasOne;',
                'belongsToMany' => 'use Illuminate\\Database\\Eloquent\\Relations\\BelongsToMany;',
                default => 'use Illuminate\\Database\\Eloquent\\Relations\\HasMany;',
            };
        }

        ksort($tipos);

        return implode("\n", [...$linhas, ...array_values($tipos)]);
    }

    /**
     * @param  list<Relacionamento>  $relacionamentos
     */
    private function corpo(
        Tabela $tabela,
        array $relacionamentos,
        bool $ehUsers,
        bool $softDelete,
        ?string $campoEmail,
        ?string $campoSenha,
    ): string {
        $blocos = [];

        $blocos[] = "    protected \$table = '{$tabela->nome}';";

        $pk = $tabela->chavePrimaria;
        if (count($pk) === 1 && $pk[0] !== 'id') {
            $blocos[] = "    protected \$primaryKey = '{$pk[0]}';";
        }

        if (count($pk) > 1) {
            $blocos[] = '    public $incrementing = false;';
            $blocos[] = '    protected $primaryKey = null;';
        } elseif (count($pk) === 1) {
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

        $hidden = $this->hidden($tabela, $campoSenha);
        if ($hidden !== []) {
            $blocos[] = $this->arrayPropriedade('hidden', $hidden);
        }

        $casts = $this->casts($tabela, $ehUsers, $campoSenha);
        if ($casts !== []) {
            $blocos[] = $this->arrayAssociativo('casts', $casts);
        }

        if ($ehUsers) {
            foreach ($this->metodosAuth($campoEmail, $campoSenha) as $metodo) {
                $blocos[] = $metodo;
            }
        }

        foreach ($relacionamentos as $rel) {
            $blocos[] = $this->metodoRelacionamento($rel);
        }

        return implode("\n\n", $blocos);
    }

    /** @return list<string> */
    private function metodosAuth(?string $campoEmail, ?string $campoSenha): array
    {
        $metodos = [];
        $senha = $campoSenha ?: 'password';
        $email = $campoEmail ?: 'email';

        if ($senha !== 'password') {
            $metodos[] = <<<PHP
    public function getAuthPasswordName(): string
    {
        return '{$senha}';
    }
PHP;
        }

        if ($email !== 'email') {
            $metodos[] = <<<PHP
    public function getEmailForPasswordReset(): string
    {
        return (string) \$this->{$email};
    }

    public function routeNotificationForMail(): string
    {
        return (string) \$this->{$email};
    }
PHP;
        }

        return $metodos;
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
    private function hidden(Tabela $tabela, ?string $campoSenha): array
    {
        $hidden = [];
        $candidatos = array_filter(['password', 'senha', 'remember_token', $campoSenha]);

        foreach (array_unique($candidatos) as $campo) {
            if ($tabela->coluna($campo) !== null) {
                $hidden[] = $campo;
            }
        }

        return array_values(array_unique($hidden));
    }

    /** @return array<string, string> */
    private function casts(Tabela $tabela, bool $ehUsers, ?string $campoSenha): array
    {
        $casts = [];

        foreach ($tabela->colunas as $coluna) {
            $cast = $this->castColuna($coluna, $ehUsers, $campoSenha);

            if ($cast !== null) {
                $casts[$coluna->nome] = $cast;
            }
        }

        return $casts;
    }

    private function castColuna(Coluna $coluna, bool $ehUsers, ?string $campoSenha): ?string
    {
        $senhas = array_filter(['password', 'senha', $campoSenha]);

        if ($ehUsers && in_array($coluna->nome, $senhas, true)) {
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
        if ($rel->tipo === 'belongsToMany') {
            return $this->metodoBelongsToMany($rel);
        }

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

    private function metodoBelongsToMany(Relacionamento $rel): string
    {
        $cadeia = "\$this->belongsToMany({$rel->modelo}::class, '{$rel->tabelaPivot}', '{$rel->chaveEstrangeira}', '{$rel->chaveRelacionada}')";

        if ($rel->withPivot !== []) {
            $cols = implode("', '", $rel->withPivot);
            $cadeia .= "\n            ->withPivot('{$cols}')";
        }

        if ($rel->withTimestamps) {
            $cadeia .= "\n            ->withTimestamps()";
        }

        return <<<PHP
    public function {$rel->metodo}(): BelongsToMany
    {
        return {$cadeia};
    }
PHP;
    }
}
