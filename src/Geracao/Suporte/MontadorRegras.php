<?php

namespace SqlToEloquent\Geracao\Suporte;

use SqlToEloquent\Dominio\ChaveEstrangeira;
use SqlToEloquent\Dominio\Coluna;
use SqlToEloquent\Dominio\Tabela;

class MontadorRegras
{
    /**
     * @return array<string, list<string>>
     */
    public function regras(Tabela $tabela, bool $parcial = false): array
    {
        $excluir = ['id', 'created_at', 'updated_at', 'criado_em', 'atualizado_em', 'remember_token', 'deleted_at'];
        $regras = [];

        foreach ($tabela->colunas as $coluna) {
            if (in_array($coluna->nome, $excluir, true)) {
                continue;
            }

            if ($tabela->chavePrimaria === [$coluna->nome] && $coluna->autoIncremento) {
                continue;
            }

            $lista = $this->regrasColuna($coluna, $tabela);

            if ($parcial) {
                $lista = $this->tornarParcial($lista);
            }

            $regras[$coluna->nome] = $lista;
        }

        return $regras;
    }

    /**
     * @return list<string>
     */
    public function unicos(Tabela $tabela): array
    {
        $campos = [];

        foreach ($tabela->indices as $indice) {
            if ($indice->unico && count($indice->colunas) === 1) {
                $campos[] = $indice->colunas[0];
            }
        }

        return array_values(array_unique($campos));
    }

    /**
     * @param  list<string>  $regras
     * @return list<string>
     */
    private function tornarParcial(array $regras): array
    {
        $filtradas = array_values(array_filter(
            $regras,
            fn (string $regra) => $regra !== 'required',
        ));

        array_unshift($filtradas, 'sometimes');

        return array_values(array_unique($filtradas));
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

        $fk = $this->fkDaColuna($tabela, $coluna->nome);

        if ($fk !== null) {
            $regras[] = 'exists:'.$fk->tabelaReferenciada.','.$fk->colunasReferenciadas[0];
        }

        foreach ($tabela->indices as $indice) {
            if ($indice->unico && $indice->colunas === [$coluna->nome]) {
                $regras[] = 'unique:'.$tabela->nome.','.$coluna->nome;
            }
        }

        return array_values(array_unique($regras));
    }

    private function fkDaColuna(Tabela $tabela, string $coluna): ?ChaveEstrangeira
    {
        foreach ($tabela->chavesEstrangeiras as $fk) {
            if ($fk->colunas === [$coluna] && count($fk->colunasReferenciadas) === 1) {
                return $fk;
            }
        }

        return null;
    }
}
