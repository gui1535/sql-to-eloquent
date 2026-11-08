<?php

namespace SqlToEloquent\Geracao\Suporte;

use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Dominio\Tabela;

class DetectorPivot
{
    private const COLUNAS_META = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
        'criado_em',
        'atualizado_em',
    ];

    public function ehPivot(Tabela $tabela): bool
    {
        $fks = $this->fksSimples($tabela);

        if (count($fks) !== 2) {
            return false;
        }

        if ($fks[0]['tabela'] === $fks[1]['tabela']) {
            return false;
        }

        $colunasFk = [$fks[0]['coluna'], $fks[1]['coluna']];

        if ($this->mesmasColunas($tabela->chavePrimaria, $colunasFk)) {
            return true;
        }

        foreach ($tabela->indices as $indice) {
            if ($indice->unico && $this->mesmasColunas($indice->colunas, $colunasFk)) {
                return true;
            }
        }

        return count($this->colunasExtras($tabela, $colunasFk)) === 0;
    }

    public function ehPivotPura(Tabela $tabela): bool
    {
        if (! $this->ehPivot($tabela)) {
            return false;
        }

        $fks = $this->fksSimples($tabela);
        $colunasFk = [$fks[0]['coluna'], $fks[1]['coluna']];

        return count($this->colunasExtras($tabela, $colunasFk)) === 0;
    }

    /**
     * @return list<string>
     */
    public function nomesPivots(Schema $schema): array
    {
        $nomes = [];

        foreach ($schema->tabelas as $tabela) {
            if ($this->ehPivot($tabela)) {
                $nomes[] = $tabela->nome;
            }
        }

        return $nomes;
    }

    /**
     * @return list<string>
     */
    public function nomesPivotsPuras(Schema $schema): array
    {
        $nomes = [];

        foreach ($schema->tabelas as $tabela) {
            if ($this->ehPivotPura($tabela)) {
                $nomes[] = $tabela->nome;
            }
        }

        return $nomes;
    }

    /**
     * @return list<array{coluna: string, tabela: string, referenciada: string}>
     */
    public function fksSimples(Tabela $tabela): array
    {
        $fks = [];

        foreach ($tabela->chavesEstrangeiras as $fk) {
            if (count($fk->colunas) !== 1 || count($fk->colunasReferenciadas) !== 1) {
                continue;
            }

            $fks[] = [
                'coluna' => $fk->colunas[0],
                'tabela' => $fk->tabelaReferenciada,
                'referenciada' => $fk->colunasReferenciadas[0],
            ];
        }

        return $fks;
    }

    /**
     * @param  list<string>  $colunasFk
     * @return list<string>
     */
    public function colunasExtras(Tabela $tabela, array $colunasFk): array
    {
        $extras = [];

        foreach ($tabela->colunas as $coluna) {
            if (in_array($coluna->nome, $colunasFk, true)) {
                continue;
            }

            if (in_array($coluna->nome, self::COLUNAS_META, true)) {
                continue;
            }

            $extras[] = $coluna->nome;
        }

        return $extras;
    }

    public function temTimestamps(Tabela $tabela): bool
    {
        return $tabela->coluna('created_at') !== null
            && $tabela->coluna('updated_at') !== null;
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private function mesmasColunas(array $a, array $b): bool
    {
        $esquerda = $a;
        $direita = $b;
        sort($esquerda);
        sort($direita);

        return $esquerda === $direita;
    }
}
