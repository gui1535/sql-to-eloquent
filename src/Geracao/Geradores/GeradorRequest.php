<?php

namespace SqlToEloquent\Geracao\Geradores;

use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\ContratoGerador;
use SqlToEloquent\Geracao\Suporte\ConstrutorRequest;
use SqlToEloquent\Planejamento\ArquivoPlanejado;
use SqlToEloquent\Planejamento\OpcoesGeracao;

class GeradorRequest implements ContratoGerador
{
    public function __construct(
        private readonly ConstrutorRequest $construtor = new ConstrutorRequest,
    ) {
    }

    public function tipo(): TipoArtefato
    {
        return TipoArtefato::Request;
    }

    public function gerar(Schema $schema, OpcoesGeracao $opcoes): array
    {
        $diretorio = rtrim($opcoes->caminhos['requests'] ?? 'app/Http/Requests', '/');
        $arquivos = [];

        foreach ($schema->tabelas as $tabela) {
            if (! $opcoes->incluiTabelaCrud($tabela->nome)) {
                continue;
            }

            $arquivos[] = new ArquivoPlanejado(
                tipo: TipoArtefato::Request,
                caminho: $diretorio.'/'.$this->construtor->nomeArquivoStore($tabela->nome, $opcoes->tabelaUsers),
                conteudo: $this->construtor->montarStore(
                    $tabela,
                    $opcoes->namespaceRequests,
                    $opcoes->tabelaUsers,
                ),
                tabela: $tabela->nome,
            );

            $arquivos[] = new ArquivoPlanejado(
                tipo: TipoArtefato::Request,
                caminho: $diretorio.'/'.$this->construtor->nomeArquivoUpdate($tabela->nome, $opcoes->tabelaUsers),
                conteudo: $this->construtor->montarUpdate(
                    $tabela,
                    $opcoes->namespaceRequests,
                    $opcoes->tabelaUsers,
                ),
                tabela: $tabela->nome,
            );
        }

        return $arquivos;
    }
}
