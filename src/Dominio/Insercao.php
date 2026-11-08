<?php

namespace SqlToEloquent\Dominio;

class Insercao
{
    /**
     * @param  list<string>  $colunas
     * @param  list<list<mixed>>  $linhas
     */
    public function __construct(
        public readonly string $tabela,
        public readonly array $colunas,
        public readonly array $linhas,
    ) {
    }
}
