<?php

namespace SqlToEloquent\Geracao\Suporte;

use Illuminate\Support\Str;
use SqlToEloquent\Dominio\ChaveEstrangeira;
use SqlToEloquent\Dominio\Coluna;
use SqlToEloquent\Dominio\Indice;
use SqlToEloquent\Dominio\Tabela;

class ConstrutorMigration
{
    public function nomeClasse(string $tabela): string
    {
        return 'Create'.Str::studly($tabela).'Table';
    }

    public function nomeArquivo(int $indice, string $tabela): string
    {
        $sequencia = str_pad((string) ($indice + 1), 6, '0', STR_PAD_LEFT);

        return "2020_01_01_{$sequencia}_create_{$tabela}_table.php";
    }

    public function montar(Tabela $tabela, bool $softDelete = false): string
    {
        $linhas = $this->linhasBlueprint($tabela, $softDelete);
        $corpo = implode("\n", array_map(
            fn (string $linha) => '            '.$linha,
            $linhas,
        ));

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$tabela->nome}', function (Blueprint \$table) {
{$corpo}
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$tabela->nome}');
    }
};

PHP;
    }

    /** @return list<string> */
    private function linhasBlueprint(Tabela $tabela, bool $softDelete): array
    {
        $linhas = [];
        $ignoradas = [];
        $pk = $tabela->chavePrimaria;
        $timestampsPendentes = $this->temTimestamps($tabela);
        $softDeleteAdicionado = false;

        if ($this->usarId($tabela)) {
            $linhas[] = '$table->id();';
            $ignoradas[] = 'id';
        } elseif ($this->usarIncrements($tabela)) {
            $coluna = $tabela->coluna($pk[0]);
            $linhas[] = $this->linhaIncrements($coluna);
            $ignoradas[] = $coluna->nome;
        }

        foreach ($tabela->colunas as $coluna) {
            if (in_array($coluna->nome, $ignoradas, true)) {
                continue;
            }

            if ($timestampsPendentes && in_array($coluna->nome, ['created_at', 'updated_at'], true)) {
                $linhas[] = '$table->timestamps();';
                $ignoradas[] = 'created_at';
                $ignoradas[] = 'updated_at';
                $timestampsPendentes = false;
                continue;
            }

            if ($coluna->nome === 'deleted_at') {
                $linhas[] = '$table->softDeletes();';
                $softDeleteAdicionado = true;
                continue;
            }

            if ($coluna->nome === 'remember_token') {
                $linhas[] = '$table->rememberToken();';
                continue;
            }

            $linhas[] = $this->linhaColuna($coluna);
        }

        if ($softDelete && ! $softDeleteAdicionado) {
            $linhas[] = '$table->softDeletes();';
        }

        if (
            count($pk) > 1
            || (
                count($pk) === 1
                && ! in_array($pk[0], $ignoradas, true)
            )
        ) {
            if (count($pk) === 1) {
                $linhas[] = "\$table->primary('{$pk[0]}');";
            } else {
                $linhas[] = '$table->primary(['.$this->listaStrings($pk).']);';
            }
        }

        foreach ($tabela->indices as $indice) {
            if ($this->indiceCobertoPorFk($indice, $tabela->chavesEstrangeiras)) {
                continue;
            }

            $linhas[] = $this->linhaIndice($indice);
        }

        foreach ($tabela->chavesEstrangeiras as $fk) {
            $linhas[] = $this->linhaForeign($fk);
        }

        return $linhas;
    }

    private function usarId(Tabela $tabela): bool
    {
        if ($tabela->chavePrimaria !== ['id']) {
            return false;
        }

        $id = $tabela->coluna('id');

        return $id !== null
            && $id->tipo === 'bigint'
            && $id->unsigned
            && $id->autoIncremento;
    }

    private function usarIncrements(Tabela $tabela): bool
    {
        if (count($tabela->chavePrimaria) !== 1) {
            return false;
        }

        $coluna = $tabela->coluna($tabela->chavePrimaria[0]);

        return $coluna !== null && $coluna->autoIncremento;
    }

    private function linhaIncrements(Coluna $coluna): string
    {
        if ($coluna->tipo === 'bigint' && ! $coluna->unsigned) {
            return "\$table->bigInteger('{$coluna->nome}', true);";
        }

        $metodo = match ($coluna->tipo) {
            'bigint' => 'bigIncrements',
            'mediumint' => 'mediumIncrements',
            'smallint' => 'smallIncrements',
            'tinyint' => 'tinyIncrements',
            default => 'increments',
        };

        return "\$table->{$metodo}('{$coluna->nome}');";
    }

    private function temTimestamps(Tabela $tabela): bool
    {
        $criado = $tabela->coluna('created_at');
        $atualizado = $tabela->coluna('updated_at');

        if ($criado === null || $atualizado === null) {
            return false;
        }

        $tipos = ['timestamp', 'datetime'];

        return in_array($criado->tipo, $tipos, true)
            && in_array($atualizado->tipo, $tipos, true);
    }

    private function linhaColuna(Coluna $coluna): string
    {
        $partes = [$this->chamadaTipo($coluna)];

        if ($coluna->unsigned && ! $this->ehBoolean($coluna)) {
            $partes[] = 'unsigned()';
        }

        if ($coluna->nulo) {
            $partes[] = 'nullable()';
        }

        if ($coluna->padrao !== null) {
            $partes[] = $this->chamadaPadrao($coluna);
        }

        if ($coluna->comentario !== null) {
            $partes[] = 'comment('.$this->exportar($coluna->comentario).')';
        }

        return implode('->', $partes).';';
    }

    private function chamadaTipo(Coluna $coluna): string
    {
        $nome = $coluna->nome;

        if ($this->ehBoolean($coluna)) {
            return "\$table->boolean('{$nome}')";
        }

        return match ($coluna->tipo) {
            'bigint' => "\$table->bigInteger('{$nome}')",
            'int', 'integer' => "\$table->integer('{$nome}')",
            'mediumint' => "\$table->mediumInteger('{$nome}')",
            'smallint' => "\$table->smallInteger('{$nome}')",
            'tinyint' => "\$table->tinyInteger('{$nome}')",
            'varchar' => "\$table->string('{$nome}'".$this->argTamanho($coluna, 255).')',
            'char' => "\$table->char('{$nome}'".$this->argTamanho($coluna, 255).')',
            'text' => "\$table->text('{$nome}')",
            'mediumtext' => "\$table->mediumText('{$nome}')",
            'longtext' => "\$table->longText('{$nome}')",
            'tinytext' => "\$table->text('{$nome}')",
            'decimal', 'numeric' => sprintf(
                "\$table->decimal('%s', %d, %d)",
                $nome,
                $coluna->precisao ?? 8,
                $coluna->escala ?? 2,
            ),
            'float' => "\$table->float('{$nome}')",
            'double', 'real' => "\$table->double('{$nome}')",
            'date' => "\$table->date('{$nome}')",
            'datetime' => "\$table->dateTime('{$nome}')",
            'timestamp' => "\$table->timestamp('{$nome}')",
            'time' => "\$table->time('{$nome}')",
            'year' => "\$table->year('{$nome}')",
            'json', 'jsonb' => "\$table->json('{$nome}')",
            'enum' => "\$table->enum('{$nome}', [".$this->listaStrings($coluna->valoresEnum).'])',
            'set' => "\$table->string('{$nome}'".$this->argTamanho($coluna, 255).')',
            'blob', 'binary', 'mediumblob', 'longblob' => "\$table->binary('{$nome}')",
            'uuid' => "\$table->uuid('{$nome}')",
            default => "\$table->string('{$nome}')",
        };
    }

    private function argTamanho(Coluna $coluna, int $padrao): string
    {
        $tamanho = $coluna->tamanho ?? $padrao;

        if ($coluna->tipo === 'varchar' && $tamanho === $padrao) {
            return '';
        }

        return ', '.$tamanho;
    }

    private function ehBoolean(Coluna $coluna): bool
    {
        return in_array($coluna->tipo, ['boolean', 'bool'], true)
            || ($coluna->tipo === 'tinyint' && $coluna->tamanho === 1);
    }

    private function chamadaPadrao(Coluna $coluna): string
    {
        $padrao = $coluna->padrao;

        if (is_string($padrao) && strtoupper($padrao) === 'CURRENT_TIMESTAMP') {
            return 'useCurrent()';
        }

        if ($this->ehBoolean($coluna)) {
            $valor = in_array((string) $padrao, ['1', 'true', 'TRUE'], true);

            return 'default('.($valor ? 'true' : 'false').')';
        }

        if (is_numeric($padrao) && ! in_array($coluna->tipo, ['varchar', 'char', 'text', 'enum', 'set'], true)) {
            return 'default('.$padrao.')';
        }

        return 'default('.$this->exportar((string) $padrao).')';
    }

    private function linhaIndice(Indice $indice): string
    {
        $cols = count($indice->colunas) === 1
            ? "'{$indice->colunas[0]}'"
            : '['.$this->listaStrings($indice->colunas).']';

        if ($indice->unico || $indice->tipo === 'unique') {
            return "\$table->unique({$cols}, '{$indice->nome}');";
        }

        if ($indice->tipo === 'fulltext') {
            return "\$table->fullText({$cols}, '{$indice->nome}');";
        }

        return "\$table->index({$cols}, '{$indice->nome}');";
    }

    private function linhaForeign(ChaveEstrangeira $fk): string
    {
        $cols = count($fk->colunas) === 1
            ? "'{$fk->colunas[0]}'"
            : '['.$this->listaStrings($fk->colunas).']';

        $refs = count($fk->colunasReferenciadas) === 1
            ? "'{$fk->colunasReferenciadas[0]}'"
            : '['.$this->listaStrings($fk->colunasReferenciadas).']';

        $linha = "\$table->foreign({$cols}, '{$fk->nome}')->references({$refs})->on('{$fk->tabelaReferenciada}')";

        if ($fk->aoDeletar !== null) {
            $linha .= '->onDelete('.$this->exportar($fk->aoDeletar).')';
        }

        if ($fk->aoAtualizar !== null) {
            $linha .= '->onUpdate('.$this->exportar($fk->aoAtualizar).')';
        }

        return $linha.';';
    }

    /**
     * @param  list<ChaveEstrangeira>  $fks
     */
    private function indiceCobertoPorFk(Indice $indice, array $fks): bool
    {
        foreach ($fks as $fk) {
            if ($indice->colunas === $fk->colunas) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $valores */
    private function listaStrings(array $valores): string
    {
        return implode(', ', array_map(
            fn (string $valor) => $this->exportar($valor),
            $valores,
        ));
    }

    private function exportar(string $valor): string
    {
        return var_export($valor, true);
    }
}
