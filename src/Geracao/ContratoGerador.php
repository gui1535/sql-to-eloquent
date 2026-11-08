<?php

namespace SqlToEloquent\Geracao;

use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Planejamento\ArquivoPlanejado;
use SqlToEloquent\Planejamento\OpcoesGeracao;

interface ContratoGerador
{
    public function tipo(): TipoArtefato;

    /**
     * @return list<ArquivoPlanejado>
     */
    public function gerar(Schema $schema, OpcoesGeracao $opcoes): array;
}
