<?php

namespace SqlToEloquent\Geracao\Geradores;

use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\ContratoGerador;
use SqlToEloquent\Geracao\Suporte\ConstrutorController;
use SqlToEloquent\Planejamento\ArquivoPlanejado;
use SqlToEloquent\Planejamento\OpcoesGeracao;

class GeradorController implements ContratoGerador
{
    public function __construct(
        private readonly ConstrutorController $construtor = new ConstrutorController,
    ) {
    }

    public function tipo(): TipoArtefato
    {
        return TipoArtefato::Controller;
    }

    public function gerar(Schema $schema, OpcoesGeracao $opcoes): array
    {
        $diretorio = rtrim($opcoes->caminhos['controllers'] ?? 'app/Http/Controllers', '/');
        $arquivos = [];

        foreach ($schema->tabelas as $tabela) {
            $arquivos[] = new ArquivoPlanejado(
                tipo: TipoArtefato::Controller,
                caminho: $diretorio.'/'.$this->construtor->nomeArquivo($tabela->nome),
                conteudo: $this->construtor->montar(
                    $tabela,
                    $opcoes->namespaceControllers,
                    $opcoes->namespaceModels,
                ),
                tabela: $tabela->nome,
            );
        }

        return $arquivos;
    }
}
