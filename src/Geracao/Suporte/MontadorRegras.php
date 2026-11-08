<?php

namespace SqlToEloquent\Geracao\Suporte;

use SqlToEloquent\Dominio\Coluna;
use SqlToEloquent\Dominio\Tabela;

class MontadorRegras
{
    /**
     * @return array<string, list<string>>
     */
    public function regras(Tabela $tabela): array
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
}
