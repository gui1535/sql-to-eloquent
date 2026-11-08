<?php

namespace SqlToEloquent\Dominio;

class ChaveEstrangeira
{
    public function __construct(
        public readonly string $nome,
        public readonly array $colunas,
        public readonly string $tabelaReferenciada,
        public readonly array $colunasReferenciadas,
        public readonly ?string $aoAtualizar = null,
        public readonly ?string $aoDeletar = null,
    ) {
    }
}
