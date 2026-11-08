<?php

namespace SqlToEloquent\Planejamento;

use SqlToEloquent\Dominio\TipoArtefato;

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

    /**
     * @return list<ArquivoPlanejado>
     */
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

    public function totalConflitos(): int
    {
        return count($this->conflitos());
    }

    /**
     * @return array<string, list<ArquivoPlanejado>>
     */
    public function porTipo(): array
    {
        $grupos = [];

        foreach (TipoArtefato::cases() as $tipo) {
            $grupos[$tipo->value] = [];
        }

        foreach ($this->arquivos as $arquivo) {
            $grupos[$arquivo->tipo->value][] = $arquivo;
        }

        return array_filter($grupos);
    }
}
