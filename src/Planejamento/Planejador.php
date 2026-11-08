<?php

namespace SqlToEloquent\Planejamento;

use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Geracao\Orquestrador;

class Planejador
{
    public function __construct(
        private readonly Orquestrador $orquestrador,
    ) {
    }

    public function montar(Schema $schema, OpcoesGeracao $opcoes): PlanoGeracao
    {
        if ($opcoes->tabelas !== null) {
            $schema = $schema->filtrarTabelas($opcoes->tabelas);
        }

        $arquivos = $this->orquestrador->gerar($schema, $opcoes);
        $avisos = $schema->avisos;

        foreach ($arquivos as $indice => $arquivo) {
            if (! is_file($arquivo->caminho) || $opcoes->sobrescrever) {
                continue;
            }

            if ($this->ehArquivoUsersEsperado($arquivo, $opcoes)) {
                continue;
            }

            $arquivos[$indice] = new ArquivoPlanejado(
                tipo: $arquivo->tipo,
                caminho: $arquivo->caminho,
                conteudo: $arquivo->conteudo,
                tabela: $arquivo->tabela,
                conflito: true,
            );
        }

        return new PlanoGeracao(
            arquivos: $arquivos,
            avisos: $avisos,
        );
    }

    private function ehArquivoUsersEsperado(ArquivoPlanejado $arquivo, OpcoesGeracao $opcoes): bool
    {
        return $opcoes->tabelaUsers !== null
            && $arquivo->tabela === $opcoes->tabelaUsers;
    }
}
