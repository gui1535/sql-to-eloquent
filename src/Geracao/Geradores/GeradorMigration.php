<?php

namespace SqlToEloquent\Geracao\Geradores;

use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\ContratoGerador;
use SqlToEloquent\Geracao\Suporte\ConstrutorMigration;
use SqlToEloquent\Geracao\Suporte\OrdenadorTabelas;
use SqlToEloquent\Planejamento\ArquivoPlanejado;
use SqlToEloquent\Planejamento\OpcoesGeracao;

class GeradorMigration implements ContratoGerador
{
    public function __construct(
        private readonly ConstrutorMigration $construtor = new ConstrutorMigration,
        private readonly OrdenadorTabelas $ordenador = new OrdenadorTabelas,
    ) {
    }

    public function tipo(): TipoArtefato
    {
        return TipoArtefato::Migration;
    }

    public function gerar(Schema $schema, OpcoesGeracao $opcoes): array
    {
        $diretorio = rtrim($opcoes->caminhos['migrations'] ?? 'database/migrations', '/');
        $tabelas = $this->ordenador->ordenar($schema->tabelas);
        $arquivos = [];

        foreach ($tabelas as $indice => $tabela) {
            $nomeArquivo = $this->construtor->nomeArquivo($indice, $tabela->nome);

            $arquivos[] = new ArquivoPlanejado(
                tipo: TipoArtefato::Migration,
                caminho: $diretorio.'/'.$nomeArquivo,
                conteudo: $this->construtor->montar(
                    $tabela,
                    $opcoes->incluiSoftDelete($tabela->nome),
                ),
                tabela: $tabela->nome,
            );
        }

        return $arquivos;
    }
}
