<?php

namespace SqlToEloquent\Dominio;

class Indice
{
    public function __construct(
        public readonly string $nome,
        public readonly array $colunas,
        public readonly bool $unico = false,
        public readonly string $tipo = 'index',
    ) {
    }
}
