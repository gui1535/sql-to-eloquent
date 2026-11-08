<?php

namespace SqlToEloquent\Geracao\Suporte;

use SqlToEloquent\Dominio\Relacionamento;
use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Dominio\Tabela;

class DetectorRelacionamentos
{
    public function __construct(
        private readonly NomeadorModel $nomeador = new NomeadorModel,
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

        foreach ($schema->tabelas as $tabela) {
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
                );
        }

        return $resolvidos;
    }
}
