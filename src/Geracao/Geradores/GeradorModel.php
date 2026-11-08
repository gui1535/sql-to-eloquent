<?php

namespace SqlToEloquent\Geracao\Geradores;

use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\ContratoGerador;
use SqlToEloquent\Geracao\Suporte\ConstrutorModel;
use SqlToEloquent\Geracao\Suporte\DetectorRelacionamentos;
use SqlToEloquent\Planejamento\ArquivoPlanejado;
use SqlToEloquent\Planejamento\OpcoesGeracao;

class GeradorModel implements ContratoGerador
{
    public function __construct(
        private readonly ConstrutorModel $construtor = new ConstrutorModel,
        private readonly DetectorRelacionamentos $detector = new DetectorRelacionamentos,
    ) {
    }

    public function tipo(): TipoArtefato
    {
        return TipoArtefato::Model;
    }

    public function gerar(Schema $schema, OpcoesGeracao $opcoes): array
    {
        $diretorio = rtrim($opcoes->caminhos['models'] ?? 'app/Models', '/');
        $relacionamentos = $this->detector->detectar($schema);
        $arquivos = [];

        foreach ($schema->tabelas as $tabela) {
            $rels = $relacionamentos[$tabela->nome] ?? [];

            $arquivos[] = new ArquivoPlanejado(
                tipo: TipoArtefato::Model,
                caminho: $diretorio.'/'.$this->construtor->nomeArquivo($tabela->nome),
                conteudo: $this->construtor->montar(
                    $tabela,
                    $opcoes->namespaceModels,
                    $rels,
                ),
                tabela: $tabela->nome,
            );
        }

        return $arquivos;
    }
}
