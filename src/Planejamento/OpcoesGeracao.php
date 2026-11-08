<?php

namespace SqlToEloquent\Planejamento;

use SqlToEloquent\Dominio\TipoArtefato;

class OpcoesGeracao
{
    /**
     * @param  list<TipoArtefato>  $artefatos
     * @param  array<string, string>  $caminhos
     * @param  list<string>|null  $tabelas
     * @param  list<string>|null  $tabelasCrud
     * @param  list<string>  $tabelasSoftDelete
     */
    public function __construct(
        public readonly array $artefatos,
        public readonly array $caminhos,
        public readonly bool $sobrescrever = false,
        public readonly string $namespaceModels = 'App\\Models',
        public readonly string $namespaceControllers = 'App\\Http\\Controllers',
        public readonly string $namespaceRequests = 'App\\Http\\Requests',
        public readonly ?array $tabelas = null,
        public readonly ?string $tabelaUsers = null,
        public readonly ?array $tabelasCrud = null,
        public readonly array $tabelasSoftDelete = [],
    ) {
    }

    public function inclui(TipoArtefato $tipo): bool
    {
        return in_array($tipo, $this->artefatos, true);
    }

    public function incluiTabelaCrud(string $tabela): bool
    {
        if ($this->tabelasCrud === null) {
            return true;
        }

        return in_array($tabela, $this->tabelasCrud, true);
    }

    public function incluiSoftDelete(string $tabela): bool
    {
        return in_array($tabela, $this->tabelasSoftDelete, true);
    }
}
