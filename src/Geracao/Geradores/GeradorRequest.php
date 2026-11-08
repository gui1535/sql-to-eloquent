<?php

namespace SqlToEloquent\Geracao\Geradores;

use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\ContratoGerador;
use SqlToEloquent\Planejamento\OpcoesGeracao;

class GeradorRequest implements ContratoGerador
{
    public function tipo(): TipoArtefato
    {
        return TipoArtefato::Request;
    }

    public function gerar(Schema $schema, OpcoesGeracao $opcoes): array
    {
        return [];
    }
}
