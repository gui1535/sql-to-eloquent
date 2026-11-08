<?php

namespace SqlToEloquent\Dominio;

class Tabela
{
    /**
     * @param  list<Coluna>  $colunas
     * @param  list<string>  $chavePrimaria
     * @param  list<Indice>  $indices
     * @param  list<ChaveEstrangeira>  $chavesEstrangeiras
     */
    public function __construct(
        public readonly string $nome,
        public readonly array $colunas = [],
        public readonly array $chavePrimaria = [],
        public readonly array $indices = [],
        public readonly array $chavesEstrangeiras = [],
        public readonly ?string $mecanismo = null,
        public readonly ?string $charset = null,
        public readonly ?string $collation = null,
        public readonly ?string $comentario = null,
    ) {
    }

    public function coluna(string $nome): ?Coluna
    {
        foreach ($this->colunas as $coluna) {
            if ($coluna->nome === $nome) {
                return $coluna;
            }
        }

        return null;
    }
}
