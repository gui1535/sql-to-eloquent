<?php

namespace SqlToEloquent\Dominio;

class Relacionamento
{
    /**
     * @param  list<string>  $withPivot
     */
    public function __construct(
        public readonly string $tabela,
        public readonly string $tipo,
        public readonly string $metodo,
        public readonly string $modelo,
        public readonly string $chaveEstrangeira,
        public readonly string $chaveDona,
        public readonly ?string $tabelaPivot = null,
        public readonly ?string $chaveRelacionada = null,
        public readonly array $withPivot = [],
        public readonly bool $withTimestamps = false,
    ) {
    }
}
