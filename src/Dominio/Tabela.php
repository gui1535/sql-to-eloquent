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

    public function comColuna(Coluna $coluna): self
    {
        $colunas = array_values(array_filter(
            $this->colunas,
            fn (Coluna $atual) => $atual->nome !== $coluna->nome,
        ));
        $colunas[] = $coluna;

        return $this->copiar(colunas: $colunas);
    }

    public function semColuna(string $nome): self
    {
        return $this->copiar(
            colunas: array_values(array_filter(
                $this->colunas,
                fn (Coluna $coluna) => $coluna->nome !== $nome,
            )),
            chavePrimaria: array_values(array_filter(
                $this->chavePrimaria,
                fn (string $coluna) => $coluna !== $nome,
            )),
        );
    }

    public function comIndice(Indice $indice): self
    {
        $indices = array_values(array_filter(
            $this->indices,
            fn (Indice $atual) => $atual->nome !== $indice->nome,
        ));
        $indices[] = $indice;

        return $this->copiar(indices: $indices);
    }

    public function comChaveEstrangeira(ChaveEstrangeira $fk): self
    {
        $fks = array_values(array_filter(
            $this->chavesEstrangeiras,
            fn (ChaveEstrangeira $atual) => $atual->nome !== $fk->nome,
        ));
        $fks[] = $fk;

        return $this->copiar(chavesEstrangeiras: $fks);
    }

    /**
     * @param  list<string>  $chavePrimaria
     */
    public function comChavePrimaria(array $chavePrimaria): self
    {
        return $this->copiar(chavePrimaria: $chavePrimaria);
    }

    /**
     * @param  list<Coluna>|null  $colunas
     * @param  list<string>|null  $chavePrimaria
     * @param  list<Indice>|null  $indices
     * @param  list<ChaveEstrangeira>|null  $chavesEstrangeiras
     */
    private function copiar(
        ?array $colunas = null,
        ?array $chavePrimaria = null,
        ?array $indices = null,
        ?array $chavesEstrangeiras = null,
    ): self {
        return new self(
            nome: $this->nome,
            colunas: $colunas ?? $this->colunas,
            chavePrimaria: $chavePrimaria ?? $this->chavePrimaria,
            indices: $indices ?? $this->indices,
            chavesEstrangeiras: $chavesEstrangeiras ?? $this->chavesEstrangeiras,
            mecanismo: $this->mecanismo,
            charset: $this->charset,
            collation: $this->collation,
            comentario: $this->comentario,
        );
    }
}
