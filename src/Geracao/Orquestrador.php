<?php

namespace SqlToEloquent\Geracao;

use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Geracao\Geradores\GeradorController;
use SqlToEloquent\Geracao\Geradores\GeradorFactory;
use SqlToEloquent\Geracao\Geradores\GeradorMigration;
use SqlToEloquent\Geracao\Geradores\GeradorModel;
use SqlToEloquent\Geracao\Geradores\GeradorRequest;
use SqlToEloquent\Geracao\Geradores\GeradorResource;
use SqlToEloquent\Geracao\Geradores\GeradorRotas;
use SqlToEloquent\Geracao\Geradores\GeradorSeeder;
use SqlToEloquent\Geracao\Geradores\GeradorTeste;
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
            new GeradorFactory,
            new GeradorSeeder,
            new GeradorController,
            new GeradorRequest,
            new GeradorResource,
            new GeradorRotas,
            new GeradorTeste,
        ];
    }

    public function gerar(Schema $schema, OpcoesGeracao $opcoes): array
    {
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
