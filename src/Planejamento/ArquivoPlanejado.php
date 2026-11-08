<?php

namespace SqlToEloquent\Planejamento;

use SqlToEloquent\Dominio\TipoArtefato;

class ArquivoPlanejado
{
    public function __construct(
        public readonly TipoArtefato $tipo,
        public readonly string $caminho,
        public readonly string $conteudo,
        public readonly ?string $tabela = null,
        public readonly bool $conflito = false,
    ) {
    }
}
