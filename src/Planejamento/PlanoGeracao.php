<?php

namespace SqlToEloquent\Planejamento;

class PlanoGeracao
{
    /**
     * @param  list<ArquivoPlanejado>  $arquivos
     * @param  list<string>  $avisos
     */
    public function __construct(
        public readonly array $arquivos = [],
        public readonly array $avisos = [],
    ) {
    }

    public function conflitos(): array
    {
        return array_values(array_filter(
            $this->arquivos,
            fn (ArquivoPlanejado $arquivo) => $arquivo->conflito,
        ));
    }

    public function total(): int
    {
        return count($this->arquivos);
    }
}
