<?php

namespace SqlToEloquent\Geracao;

use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\Geradores\GeradorController;
use SqlToEloquent\Geracao\Geradores\GeradorMigration;
use SqlToEloquent\Geracao\Geradores\GeradorModel;
use SqlToEloquent\Geracao\Geradores\GeradorRequest;
use SqlToEloquent\Geracao\Geradores\GeradorSeeder;
use SqlToEloquent\Planejamento\OpcoesGeracao;

class Orquestrador
{
    /** @var list<ContratoGerador> */
    private array $geradores;

    public function __construct()
    {
        $this->geradores = [
            new GeradorMigration,
            new GeradorModel,
            new GeradorRequest,
            new GeradorController,
            new GeradorSeeder,
        ];
    }

    public function gerar(Schema $schema, OpcoesGeracao $opcoes): array
    {
        $artefatos = $opcoes->artefatos;

        if ($opcoes->inclui(TipoArtefato::Controller) && ! $opcoes->inclui(TipoArtefato::Request)) {
            $artefatos[] = TipoArtefato::Request;
            $opcoes = new OpcoesGeracao(
                artefatos: $artefatos,
                caminhos: $opcoes->caminhos,
                sobrescrever: $opcoes->sobrescrever,
                namespaceModels: $opcoes->namespaceModels,
                namespaceControllers: $opcoes->namespaceControllers,
                namespaceRequests: $opcoes->namespaceRequests,
                tabelas: $opcoes->tabelas,
                tabelaUsers: $opcoes->tabelaUsers,
                tabelasCrud: $opcoes->tabelasCrud,
                tabelasSoftDelete: $opcoes->tabelasSoftDelete,
            );
        }

        $arquivos = [];

        foreach ($this->geradores as $gerador) {
            if (! $opcoes->inclui($gerador->tipo())) {
                continue;
            }

            foreach ($gerador->gerar($schema, $opcoes) as $arquivo) {
                $arquivos[] = $arquivo;
            }
        }

        return $arquivos;
    }
}
