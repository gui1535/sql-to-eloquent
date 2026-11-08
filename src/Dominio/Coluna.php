<?php

namespace SqlToEloquent\Dominio;

class Coluna
{
    public function __construct(
        public readonly string $nome,
        public readonly string $tipo,
        public readonly bool $nulo = true,
        public readonly mixed $padrao = null,
        public readonly bool $autoIncremento = false,
        public readonly ?int $tamanho = null,
        public readonly ?int $precisao = null,
        public readonly ?int $escala = null,
        public readonly bool $unsigned = false,
        public readonly ?string $comentario = null,
        public readonly array $valoresEnum = [],
    ) {
    }
}
