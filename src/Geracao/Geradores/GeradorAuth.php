<?php

namespace SqlToEloquent\Geracao\Geradores;

use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\ContratoGerador;
use SqlToEloquent\Geracao\Suporte\ConstrutorAuth;
use SqlToEloquent\Planejamento\ArquivoPlanejado;
use SqlToEloquent\Planejamento\OpcoesGeracao;

class GeradorAuth implements ContratoGerador
{
    public function __construct(
        private readonly ConstrutorAuth $construtor = new ConstrutorAuth,
    ) {
    }

    public function tipo(): TipoArtefato
    {
        return TipoArtefato::Auth;
    }

    public function gerar(Schema $schema, OpcoesGeracao $opcoes): array
    {
        if (! $opcoes->authApi()) {
            return [];
        }

        $tabela = null;
        foreach ($schema->tabelas as $atual) {
            if ($atual->nome === $opcoes->tabelaUsers) {
                $tabela = $atual;
                break;
            }
        }

        if ($tabela === null) {
            return [];
        }

        $campoEmail = $opcoes->campoEmail ?: 'email';
        $campoSenha = $opcoes->campoSenha ?: 'password';
        $dirControllers = rtrim($opcoes->caminhos['controllers'] ?? 'app/Http/Controllers', '/');
        $dirRequests = rtrim($opcoes->caminhos['requests'] ?? 'app/Http/Requests', '/');

        return [
            new ArquivoPlanejado(
                tipo: TipoArtefato::Auth,
                caminho: $dirControllers.'/AuthController.php',
                conteudo: $this->construtor->montarController(
                    $tabela,
                    $opcoes->namespaceControllers,
                    $opcoes->namespaceModels,
                    $opcoes->namespaceRequests,
                    $campoEmail,
                    $campoSenha,
                    $opcoes->tabelaUsers,
                ),
                tabela: $tabela->nome,
            ),
            new ArquivoPlanejado(
                tipo: TipoArtefato::Auth,
                caminho: $dirRequests.'/LoginRequest.php',
                conteudo: $this->construtor->montarLoginRequest(
                    $opcoes->namespaceRequests,
                    $campoEmail,
                    $campoSenha,
                ),
                tabela: $tabela->nome,
            ),
            new ArquivoPlanejado(
                tipo: TipoArtefato::Auth,
                caminho: $dirRequests.'/RegisterRequest.php',
                conteudo: $this->construtor->montarRegisterRequest(
                    $tabela,
                    $opcoes->namespaceRequests,
                    $campoEmail,
                    $campoSenha,
                ),
                tabela: $tabela->nome,
            ),
        ];
    }
}
