<?php

namespace SqlToEloquent\Geracao\Suporte;

use SqlToEloquent\Dominio\Relacionamento;
use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Dominio\Tabela;

class DetectorRelacionamentos
{
    public function __construct(
        private readonly NomeadorModel $nomeador = new NomeadorModel,
        private readonly DetectorPivot $detectorPivot = new DetectorPivot,
    ) {
    }

    /**
     * @return array<string, list<Relacionamento>>
     */
    public function detectar(Schema $schema, ?string $tabelaUsers = null): array
    {
        $porTabela = [];

        foreach ($schema->tabelas as $tabela) {
            $porTabela[$tabela->nome] = [];
        }

        $pivots = [];

        foreach ($schema->tabelas as $tabela) {
            if ($this->detectorPivot->ehPivot($tabela)) {
                $pivots[$tabela->nome] = $tabela;
                $this->registrarBelongsToMany($porTabela, $tabela, $tabelaUsers);
            }
        }

        foreach ($schema->tabelas as $tabela) {
            if (isset($pivots[$tabela->nome])) {
                if (! $this->detectorPivot->ehPivotPura($tabela)) {
                    $this->registrarBelongsToDaPivot($porTabela, $tabela, $tabelaUsers);
                }

                continue;
            }

            foreach ($tabela->chavesEstrangeiras as $fk) {
                if (count($fk->colunas) !== 1 || count($fk->colunasReferenciadas) !== 1) {
                    continue;
                }

                $colunaFk = $fk->colunas[0];
                $colunaRef = $fk->colunasReferenciadas[0];
                $modeloDono = $this->nomeador->classe($fk->tabelaReferenciada, $tabelaUsers);
                $modeloFilho = $this->nomeador->classe($tabela->nome, $tabelaUsers);

                $porTabela[$tabela->nome][] = new Relacionamento(
                    tabela: $tabela->nome,
                    tipo: 'belongsTo',
                    metodo: $this->nomeador->metodoBelongsTo($colunaFk, $fk->tabelaReferenciada),
                    modelo: $modeloDono,
                    chaveEstrangeira: $colunaFk,
                    chaveDona: $colunaRef,
                );

                if (! isset($porTabela[$fk->tabelaReferenciada])) {
                    $porTabela[$fk->tabelaReferenciada] = [];
                }

                $unico = $this->fkEhUnica($tabela, $colunaFk);

                $porTabela[$fk->tabelaReferenciada][] = new Relacionamento(
                    tabela: $fk->tabelaReferenciada,
                    tipo: $unico ? 'hasOne' : 'hasMany',
                    metodo: $unico
                        ? $this->nomeador->metodoHasOne($tabela->nome)
                        : $this->nomeador->metodoHasMany($tabela->nome),
                    modelo: $modeloFilho,
                    chaveEstrangeira: $colunaFk,
                    chaveDona: $colunaRef,
                );
            }
        }

        foreach ($porTabela as $nome => $relacionamentos) {
            $porTabela[$nome] = $this->resolverConflitos($relacionamentos);
        }

        return $porTabela;
    }

    /**
     * @param  array<string, list<Relacionamento>>  $porTabela
     */
    private function registrarBelongsToMany(array &$porTabela, Tabela $pivot, ?string $tabelaUsers): void
    {
        $fks = $this->detectorPivot->fksSimples($pivot);

        if (count($fks) !== 2) {
            return;
        }

        [$esquerda, $direita] = $fks;
        $extras = $this->detectorPivot->colunasExtras($pivot, [$esquerda['coluna'], $direita['coluna']]);
        $withTimestamps = $this->detectorPivot->temTimestamps($pivot);

        $porTabela[$esquerda['tabela']][] = new Relacionamento(
            tabela: $esquerda['tabela'],
            tipo: 'belongsToMany',
            metodo: $this->nomeador->metodoBelongsToMany($direita['tabela']),
            modelo: $this->nomeador->classe($direita['tabela'], $tabelaUsers),
            chaveEstrangeira: $esquerda['coluna'],
            chaveDona: $esquerda['referenciada'],
            tabelaPivot: $pivot->nome,
            chaveRelacionada: $direita['coluna'],
            withPivot: $extras,
            withTimestamps: $withTimestamps,
        );

        $porTabela[$direita['tabela']][] = new Relacionamento(
            tabela: $direita['tabela'],
            tipo: 'belongsToMany',
            metodo: $this->nomeador->metodoBelongsToMany($esquerda['tabela']),
            modelo: $this->nomeador->classe($esquerda['tabela'], $tabelaUsers),
            chaveEstrangeira: $direita['coluna'],
            chaveDona: $direita['referenciada'],
            tabelaPivot: $pivot->nome,
            chaveRelacionada: $esquerda['coluna'],
            withPivot: $extras,
            withTimestamps: $withTimestamps,
        );
    }

    /**
     * @param  array<string, list<Relacionamento>>  $porTabela
     */
    private function registrarBelongsToDaPivot(array &$porTabela, Tabela $pivot, ?string $tabelaUsers): void
    {
        foreach ($this->detectorPivot->fksSimples($pivot) as $fk) {
            $porTabela[$pivot->nome][] = new Relacionamento(
                tabela: $pivot->nome,
                tipo: 'belongsTo',
                metodo: $this->nomeador->metodoBelongsTo($fk['coluna'], $fk['tabela']),
                modelo: $this->nomeador->classe($fk['tabela'], $tabelaUsers),
                chaveEstrangeira: $fk['coluna'],
                chaveDona: $fk['referenciada'],
            );
        }
    }

    private function fkEhUnica(Tabela $tabela, string $coluna): bool
    {
        if ($tabela->chavePrimaria === [$coluna]) {
            return true;
        }

        foreach ($tabela->indices as $indice) {
            if ($indice->unico && $indice->colunas === [$coluna]) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<Relacionamento>  $relacionamentos
     * @return list<Relacionamento>
     */
    private function resolverConflitos(array $relacionamentos): array
    {
        $usados = [];
        $resolvidos = [];

        foreach ($relacionamentos as $rel) {
            $metodo = $rel->metodo;
            $base = $metodo;
            $sufixo = 2;

            while (isset($usados[$metodo])) {
                $metodo = $base.$sufixo;
                $sufixo++;
            }

            $usados[$metodo] = true;

            $resolvidos[] = $metodo === $rel->metodo
                ? $rel
                : new Relacionamento(
                    tabela: $rel->tabela,
                    tipo: $rel->tipo,
                    metodo: $metodo,
                    modelo: $rel->modelo,
                    chaveEstrangeira: $rel->chaveEstrangeira,
                    chaveDona: $rel->chaveDona,
                    tabelaPivot: $rel->tabelaPivot,
                    chaveRelacionada: $rel->chaveRelacionada,
                    withPivot: $rel->withPivot,
                    withTimestamps: $rel->withTimestamps,
                );
        }

        return $resolvidos;
    }
}
