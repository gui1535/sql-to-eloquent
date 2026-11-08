<?php

namespace SqlToEloquent\Geracao\Geradores;

use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\ContratoGerador;
use SqlToEloquent\Planejamento\OpcoesGeracao;

class GeradorSeeder implements ContratoGerador
{
    public function tipo(): TipoArtefato
    {
        return TipoArtefato::Seeder;
    }

    public function gerar(Schema $schema, OpcoesGeracao $opcoes): array
    {
        return [];
    }
}
