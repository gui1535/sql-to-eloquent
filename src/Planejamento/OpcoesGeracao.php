<?php

namespace SqlToEloquent\Planejamento;

use SqlToEloquent\Dominio\TipoArtefato;

class OpcoesGeracao
{
    /**
     * @param  list<TipoArtefato>  $artefatos
     * @param  array<string, string>  $caminhos
     */
    public function __construct(
        public readonly array $artefatos,
        public readonly array $caminhos,
        public readonly bool $sobrescrever = false,
        public readonly string $namespaceModels = 'App\\Models',
        public readonly string $namespaceControllers = 'App\\Http\\Controllers',
    ) {
    }

    public function inclui(TipoArtefato $tipo): bool
    {
        return in_array($tipo, $this->artefatos, true);
    }
}
