<?php

namespace SqlToEloquent\Geracao\Geradores;

use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\ContratoGerador;
use SqlToEloquent\Geracao\Suporte\ConstrutorSeeder;
use SqlToEloquent\Geracao\Suporte\OrdenadorTabelas;
use SqlToEloquent\Planejamento\ArquivoPlanejado;
use SqlToEloquent\Planejamento\OpcoesGeracao;

class GeradorSeeder implements ContratoGerador
{
    public function __construct(
        private readonly ConstrutorSeeder $construtor = new ConstrutorSeeder,
        private readonly OrdenadorTabelas $ordenador = new OrdenadorTabelas,
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

        $tabelasOrdenadas = $this->ordenador->ordenar(
            array_values(array_filter(
                $schema->tabelas,
                fn ($tabela) => isset($porTabela[$tabela->nome]),
            )),
        );

        $arquivos = [];
        $classes = [];

        foreach ($tabelasOrdenadas as $tabela) {
            $nome = $tabela->nome;
            $classe = $this->construtor->nomeClasse($nome, $opcoes->tabelaUsers);
            $classes[] = $classe;

            $arquivos[] = new ArquivoPlanejado(
                tipo: TipoArtefato::Seeder,
                caminho: $diretorio.'/'.$this->construtor->nomeArquivo($nome, $opcoes->tabelaUsers),
                conteudo: $this->construtor->montar($nome, $porTabela[$nome], $opcoes->tabelaUsers),
                tabela: $nome,
            );
        }

        if ($classes !== []) {
            $arquivos[] = new ArquivoPlanejado(
                tipo: TipoArtefato::Seeder,
                caminho: $diretorio.'/DatabaseSeeder.php',
                conteudo: $this->construtor->montarDatabaseSeeder($classes),
                tabela: null,
            );
        }

        return $arquivos;
    }
}
