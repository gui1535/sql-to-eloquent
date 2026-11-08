<?php

namespace SqlToEloquent\Planejamento;

use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Dominio\TipoArtefato;
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
        $avisos = $schema->avisos;

        foreach ($this->avisosGeracao($schema, $opcoes, $arquivos) as $aviso) {
            $avisos[] = $aviso;
        }

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

    /**
     * @param  list<ArquivoPlanejado>  $arquivos
     * @return list<string>
     */
    private function avisosGeracao(Schema $schema, OpcoesGeracao $opcoes, array $arquivos): array
    {
        $avisos = [];

        $pivots = $this->detectorPivot->nomesPivots($schema);

        if ($pivots !== []) {
            $avisos[] = 'Tabelas pivot detectadas (belongsToMany, sem CRUD): '.implode(', ', $pivots).'.';
        }

        if ($opcoes->tabelaUsers !== null) {
            if ($opcoes->tabelaUsers !== 'users') {
                $avisos[] = "Tabela de auth é '{$opcoes->tabelaUsers}': remova ou ajuste a migration padrão create_users_table do Laravel.";
            } else {
                $avisos[] = 'Tabela users selecionada: remova a migration padrão create_users_table para evitar conflito.';
            }

            $avisos[] = 'Ajuste ou remova database/factories/UserFactory.php e o User::factory() do DatabaseSeeder padrão.';

            if ($opcoes->campoSenha !== null && $opcoes->campoSenha !== 'password') {
                $avisos[] = "Auth usará o campo '{$opcoes->campoSenha}' como senha (getAuthPasswordName).";
            }

            if ($opcoes->campoEmail !== null && $opcoes->campoEmail !== 'email') {
                $avisos[] = "Auth usará o campo '{$opcoes->campoEmail}' como e-mail.";
            }
        }

        if ($opcoes->authApi() || $opcoes->inclui(TipoArtefato::Rota)) {
            $avisos[] = 'Se a API Laravel ainda não estiver pronta, o comando pode rodar install:api automaticamente.';
        }

        return $avisos;
    }

    private function ehArquivoUsersEsperado(ArquivoPlanejado $arquivo, OpcoesGeracao $opcoes): bool
    {
        return $opcoes->tabelaUsers !== null
            && $arquivo->tabela === $opcoes->tabelaUsers;
    }
}
