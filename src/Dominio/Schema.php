<?php

namespace SqlToEloquent\Dominio;

class Schema
{
    /**
     * @param  list<Tabela>  $tabelas
     * @param  list<string>  $avisos
     */
    public function __construct(
        public readonly array $tabelas = [],
        public readonly array $avisos = [],
        public readonly ?string $origem = null,
    ) {
    }

    public function tabela(string $nome): ?Tabela
    {
        foreach ($this->tabelas as $tabela) {
            if ($tabela->nome === $nome) {
                return $tabela;
            }
        }

        return null;
    }

    public function nomesTabelas(): array
    {
        return array_map(
            fn (Tabela $tabela) => $tabela->nome,
            $this->tabelas,
        );
    }
}
