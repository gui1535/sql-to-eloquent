<?php

namespace SqlToEloquent\Geracao\Geradores;

use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\ContratoGerador;
use SqlToEloquent\Geracao\Suporte\ConstrutorController;
use SqlToEloquent\Geracao\Suporte\DetectorPivot;
use SqlToEloquent\Geracao\Suporte\DetectorRelacionamentos;
use SqlToEloquent\Planejamento\ArquivoPlanejado;
use SqlToEloquent\Planejamento\OpcoesGeracao;

class GeradorController implements ContratoGerador
{
    public function __construct(
        private readonly ConstrutorController $construtor = new ConstrutorController,
        private readonly DetectorRelacionamentos $detector = new DetectorRelacionamentos,
        private readonly DetectorPivot $detectorPivot = new DetectorPivot,
    ) {
    }

    public function tipo(): TipoArtefato
    {
        return TipoArtefato::Controller;
    }

    public function gerar(Schema $schema, OpcoesGeracao $opcoes): array
    {
        $diretorio = rtrim($opcoes->caminhos['controllers'] ?? 'app/Http/Controllers', '/');
        $relacionamentos = $this->detector->detectar($schema, $opcoes->tabelaUsers);
        $arquivos = [];

        foreach ($schema->tabelas as $tabela) {
            if ($this->detectorPivot->ehPivot($tabela)) {
                continue;
            }

            if (! $opcoes->incluiTabelaCrud($tabela->nome)) {
                continue;
            }

            $arquivos[] = new ArquivoPlanejado(
                tipo: TipoArtefato::Controller,
                caminho: $diretorio.'/'.$this->construtor->nomeArquivo($tabela->nome, $opcoes->tabelaUsers),
                conteudo: $this->construtor->montar(
                    $tabela,
                    $opcoes->namespaceControllers,
                    $opcoes->namespaceModels,
                    $opcoes->namespaceRequests,
                    $opcoes->tabelaUsers,
                    $relacionamentos[$tabela->nome] ?? [],
                ),
                tabela: $tabela->nome,
            );
        }

        return $arquivos;
    }
}
