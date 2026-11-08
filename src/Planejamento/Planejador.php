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
        $arquivos = $this->orquestrador->gerar($schema, $opcoes);
        $avisos = $schema->avisos;

        foreach ($arquivos as $indice => $arquivo) {
            if (is_file($arquivo->caminho) && ! $opcoes->sobrescrever) {
                $arquivos[$indice] = new ArquivoPlanejado(
                    tipo: $arquivo->tipo,
                    caminho: $arquivo->caminho,
                    conteudo: $arquivo->conteudo,
                    tabela: $arquivo->tabela,
                    conflito: true,
                );
            }
        }

        return new PlanoGeracao(
            arquivos: $arquivos,
            avisos: $avisos,
        );
    }
}
