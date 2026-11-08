<?php

namespace SqlToEloquent\Planejamento;

use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Geracao\Orquestrador;
use SqlToEloquent\Geracao\Suporte\DetectorPivot;

class Planejador
{
    public function __construct(
        private readonly Orquestrador $orquestrador,
        private readonly DetectorPivot $detectorPivot = new DetectorPivot,
    ) {
    }

    public function montar(Schema $schema, OpcoesGeracao $opcoes): PlanoGeracao
    {
        if ($opcoes->tabelas !== null) {
            $schema = $schema->filtrarTabelas($opcoes->tabelas);
        }

        $arquivos = $this->orquestrador->gerar($schema, $opcoes);
        $avisos = $this->avisosGeracao($schema, $opcoes);

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

    /** @return list<string> */
    private function avisosGeracao(Schema $schema, OpcoesGeracao $opcoes): array
    {
        $avisos = [];

        $pivots = $this->detectorPivot->nomesPivots($schema);

        if ($pivots !== []) {
            $avisos[] = 'Tabelas pivot detectadas (belongsToMany, sem CRUD): '.implode(', ', $pivots).'.';
        }

        if ($opcoes->tabelaUsers !== null) {
            $avisos[] = 'Ao gravar, a migration create_users_table e a UserFactory podem ser ajustadas automaticamente.';
        }

        if ($opcoes->authApi()) {
            if ($opcoes->campoSenha !== null && $opcoes->campoSenha !== 'password') {
                $avisos[] = "Auth usará o campo '{$opcoes->campoSenha}' como senha (getAuthPasswordName).";
            }

            if ($opcoes->campoEmail !== null && $opcoes->campoEmail !== 'email') {
                $avisos[] = "Auth usará o campo '{$opcoes->campoEmail}' como e-mail.";
            }
        }

        return $avisos;
    }

    private function ehArquivoUsersEsperado(ArquivoPlanejado $arquivo, OpcoesGeracao $opcoes): bool
    {
        return $opcoes->tabelaUsers !== null
            && $arquivo->tabela === $opcoes->tabelaUsers;
    }
}
