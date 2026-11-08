<?php

namespace SqlToEloquent\Dominio;

class Schema
{
    /**
     * @param  list<Tabela>  $tabelas
     * @param  list<Insercao>  $insercoes
     * @param  list<string>  $avisos
     */
    public function __construct(
        public readonly array $tabelas = [],
        public readonly array $insercoes = [],
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

    public function temDados(): bool
    {
        return $this->insercoes !== [];
    }

    /**
     * @param  list<string>  $nomes
     */
    public function filtrarTabelas(array $nomes): self
    {
        if ($nomes === []) {
            return $this;
        }

        $permitidas = array_fill_keys($nomes, true);

        return new self(
            tabelas: array_values(array_filter(
                $this->tabelas,
                fn (Tabela $tabela) => isset($permitidas[$tabela->nome]),
            )),
            insercoes: array_values(array_filter(
                $this->insercoes,
                fn (Insercao $insercao) => isset($permitidas[$insercao->tabela]),
            )),
            avisos: $this->avisos,
            origem: $this->origem,
        );
    }

    public function totalColunas(): int
    {
        $total = 0;

        foreach ($this->tabelas as $tabela) {
            $total += count($tabela->colunas);
        }

        return $total;
    }

    public function totalForeignKeys(): int
    {
        $total = 0;

        foreach ($this->tabelas as $tabela) {
            $total += count($tabela->chavesEstrangeiras);
        }

        return $total;
    }

    public function totalLinhasInsert(): int
    {
        $total = 0;

        foreach ($this->insercoes as $insercao) {
            $total += count($insercao->linhas);
        }

        return $total;
    }
}
