<?php

namespace SqlToEloquent\Geracao\Suporte;

use SqlToEloquent\Dominio\Tabela;

class OrdenadorTabelas
{
    /**
     * @param  list<Tabela>  $tabelas
     * @return list<Tabela>
     */
    public function ordenar(array $tabelas): array
    {
        $porNome = [];
        foreach ($tabelas as $tabela) {
            $porNome[$tabela->nome] = $tabela;
        }

        $visitadas = [];
        $emPilha = [];
        $ordem = [];

        $visitar = function (string $nome) use (&$visitar, &$visitadas, &$emPilha, &$ordem, $porNome): void {
            if (isset($visitadas[$nome]) || ! isset($porNome[$nome])) {
                return;
            }

            if (isset($emPilha[$nome])) {
                return;
            }

            $emPilha[$nome] = true;
            $tabela = $porNome[$nome];

            foreach ($tabela->chavesEstrangeiras as $fk) {
                $visitar($fk->tabelaReferenciada);
            }

            unset($emPilha[$nome]);
            $visitadas[$nome] = true;
            $ordem[] = $tabela;
        };

        foreach ($tabelas as $tabela) {
            $visitar($tabela->nome);
        }

        return $ordem;
    }
}
