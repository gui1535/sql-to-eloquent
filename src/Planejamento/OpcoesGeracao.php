<?php

namespace SqlToEloquent\Planejamento;

use SqlToEloquent\Dominio\TipoArtefato;

class OpcoesGeracao
{
    /**
     * @param  list<TipoArtefato>  $artefatos
     * @param  array<string, string>  $caminhos
     * @param  list<string>|null  $tabelas
     */
    public function __construct(
        public readonly array $artefatos,
        public readonly array $caminhos,
        public readonly bool $sobrescrever = false,
        public readonly string $namespaceModels = 'App\\Models',
        public readonly string $namespaceControllers = 'App\\Http\\Controllers',
        public readonly ?array $tabelas = null,
    ) {
    }

    public function inclui(TipoArtefato $tipo): bool
    {
        return in_array($tipo, $this->artefatos, true);
    }
}
