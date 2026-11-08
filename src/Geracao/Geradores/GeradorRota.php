<?php

namespace SqlToEloquent\Geracao\Geradores;

use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\ContratoGerador;
use SqlToEloquent\Geracao\Suporte\ConstrutorRota;
use SqlToEloquent\Geracao\Suporte\DetectorPivot;
use SqlToEloquent\Planejamento\ArquivoPlanejado;
use SqlToEloquent\Planejamento\OpcoesGeracao;

class GeradorRota implements ContratoGerador
{
    public function __construct(
        private readonly ConstrutorRota $construtor = new ConstrutorRota,
        private readonly DetectorPivot $detectorPivot = new DetectorPivot,
    ) {
    }

    public function tipo(): TipoArtefato
    {
        return TipoArtefato::Rota;
    }

    public function gerar(Schema $schema, OpcoesGeracao $opcoes): array
    {
        $tabelas = [];

        if ($opcoes->inclui(TipoArtefato::Controller)) {
            foreach ($schema->tabelas as $tabela) {
                if ($this->detectorPivot->ehPivot($tabela)) {
                    continue;
                }

                if (! $opcoes->incluiTabelaCrud($tabela->nome)) {
                    continue;
                }

                $tabelas[] = $tabela;
            }
        }

        if ($tabelas === [] && ! $opcoes->authApi()) {
            return [];
        }

        $diretorio = rtrim($opcoes->caminhos['rotas'] ?? 'routes', '/');

        return [
            new ArquivoPlanejado(
                tipo: TipoArtefato::Rota,
                caminho: $diretorio.'/sql-to-eloquent.php',
                conteudo: $this->construtor->montar(
                    $tabelas,
                    $opcoes->namespaceControllers,
                    $opcoes->tabelaUsers,
                    $opcoes->authApi(),
                ),
                tabela: null,
            ),
        ];
    }
}
