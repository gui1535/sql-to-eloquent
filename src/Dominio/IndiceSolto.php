<?php

namespace SqlToEloquent\Dominio;

class IndiceSolto
{
    public function __construct(
        public readonly string $tabela,
        public readonly Indice $indice,
    ) {
    }
}
