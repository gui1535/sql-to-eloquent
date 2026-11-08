<?php

namespace SqlToEloquent\Geracao\Geradores;

use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\ContratoGerador;
use SqlToEloquent\Geracao\Suporte\ConstrutorSeeder;
use SqlToEloquent\Planejamento\ArquivoPlanejado;
use SqlToEloquent\Planejamento\OpcoesGeracao;

class GeradorSeeder implements ContratoGerador
{
    public function __construct(
        private readonly ConstrutorSeeder $construtor = new ConstrutorSeeder,
    ) {
    }

    public function tipo(): TipoArtefato
    {
        return TipoArtefato::Seeder;
    }

    public function gerar(Schema $schema, OpcoesGeracao $opcoes): array
    {
        if (! $schema->temDados()) {
            return [];
        }

        $diretorio = rtrim($opcoes->caminhos['seeders'] ?? 'database/seeders', '/');
        $porTabela = [];

        foreach ($schema->insercoes as $insercao) {
            $porTabela[$insercao->tabela][] = $insercao;
        }

        $arquivos = [];

        foreach ($porTabela as $tabela => $insercoes) {
            $arquivos[] = new ArquivoPlanejado(
                tipo: TipoArtefato::Seeder,
                caminho: $diretorio.'/'.$this->construtor->nomeArquivo($tabela),
                conteudo: $this->construtor->montar($tabela, $insercoes),
                tabela: $tabela,
            );
        }

        return $arquivos;
    }
}
