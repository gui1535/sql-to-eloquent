<?php

namespace SqlToEloquent\Dominio;

class Relacionamento
{
    public function __construct(
        public readonly string $tabela,
        public readonly string $tipo,
        public readonly string $metodo,
        public readonly string $modelo,
        public readonly string $chaveEstrangeira,
        public readonly string $chaveDona,
    ) {
    }
}
