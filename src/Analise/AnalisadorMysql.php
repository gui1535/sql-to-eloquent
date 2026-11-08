<?php

namespace SqlToEloquent\Analise;

use RuntimeException;
use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Dominio\Tabela;

class AnalisadorMysql
{
    public function __construct(
        private readonly TokenizadorSql $tokenizador = new TokenizadorSql,
        private readonly InterpretadorCreateTable $interpretador = new InterpretadorCreateTable,
    ) {
    }

    public function analisarArquivo(string $caminho): Schema
    {
        if (! is_file($caminho)) {
            throw new RuntimeException("Arquivo SQL nao encontrado: {$caminho}");
        }

        $conteudo = file_get_contents($caminho);

        if ($conteudo === false) {
            throw new RuntimeException("Nao foi possivel ler o arquivo: {$caminho}");
        }

        return $this->analisar($conteudo, $caminho);
    }

    public function analisar(string $sql, ?string $origem = null): Schema
    {
        $tokens = $this->tokenizador->tokenizar($sql);
        $statements = $this->separarStatements($tokens);

        $tabelas = [];
        $avisos = [];

        foreach ($statements as $statement) {
            if ($statement === []) {
                continue;
            }

            $resultado = $this->processarStatement($statement);
            $avisos = array_merge($avisos, $resultado['avisos']);

            if ($resultado['tabela'] !== null) {
                $tabelas[] = $resultado['tabela'];
            }
        }

        return new Schema(
            tabelas: $tabelas,
            avisos: $avisos,
            origem: $origem,
        );
    }

    /**
     * @param  list<Token>  $tokens
     * @return list<list<Token>>
     */
    private function separarStatements(array $tokens): array
    {
        $statements = [];
        $atual = [];

        foreach ($tokens as $token) {
            if ($token->eh(TiposToken::SIMBOLO, ';')) {
                if ($atual !== []) {
                    $statements[] = $atual;
                    $atual = [];
                }
                continue;
            }

            $atual[] = $token;
        }

        if ($atual !== []) {
            $statements[] = $atual;
        }

        return $statements;
    }

    /**
     * @param  list<Token>  $tokens
     * @return array{tabela: ?Tabela, avisos: list<string>}
     */
    private function processarStatement(array $tokens): array
    {
        $primeiro = $tokens[0] ?? null;

        if ($primeiro === null) {
            return ['tabela' => null, 'avisos' => []];
        }

        if ($this->ehCreateTable($tokens)) {
            try {
                return $this->interpretador->interpretar($tokens);
            } catch (RuntimeException $erro) {
                $nome = $this->tentarExtrairNomeTabela($tokens) ?? 'desconhecida';

                return [
                    'tabela' => null,
                    'avisos' => ["Falha ao interpretar CREATE TABLE '{$nome}': {$erro->getMessage()}"],
                ];
            }
        }

        if ($this->deveIgnorar($tokens)) {
            return ['tabela' => null, 'avisos' => []];
        }

        $resumo = $this->resumoStatement($tokens);

        return [
            'tabela' => null,
            'avisos' => ["Statement nao suportado e ignorado: {$resumo}"],
        ];
    }

    /** @param list<Token> $tokens */
    private function ehCreateTable(array $tokens): bool
    {
        $leitor = new LeitorTokens($tokens);

        if (! $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'CREATE')) {
            return false;
        }

        $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'TEMPORARY');

        return $leitor->atual()?->eh(TiposToken::IDENTIFICADOR, 'TABLE') === true;
    }

    /** @param list<Token> $tokens */
    private function deveIgnorar(array $tokens): bool
    {
        $primeiro = strtoupper($tokens[0]->valor);
        $segundo = strtoupper($tokens[1]->valor ?? '');

        $comandosIgnorados = [
            'SET', 'USE', 'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'LOCK', 'UNLOCK',
            'START', 'COMMIT', 'ROLLBACK', 'SAVEPOINT', 'TRUNCATE',
        ];

        if (in_array($primeiro, $comandosIgnorados, true)) {
            return true;
        }

        if ($primeiro === 'DROP' && in_array($segundo, ['TABLE', 'DATABASE', 'SCHEMA'], true)) {
            return true;
        }

        if ($primeiro === 'CREATE' && in_array($segundo, ['DATABASE', 'SCHEMA'], true)) {
            return true;
        }

        return false;
    }

    /** @param list<Token> $tokens */
    private function tentarExtrairNomeTabela(array $tokens): ?string
    {
        $leitor = new LeitorTokens($tokens);
        $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'CREATE');
        $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'TEMPORARY');
        $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'TABLE');
        $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'IF');
        $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'NOT');
        $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'EXISTS');

        $token = $leitor->atual();

        if ($token === null) {
            return null;
        }

        $nome = $token->valor;

        if ($leitor->olhar(1)?->eh(TiposToken::SIMBOLO, '.')) {
            return $leitor->olhar(2)?->valor ?? $nome;
        }

        return $nome;
    }

    /** @param list<Token> $tokens */
    private function resumoStatement(array $tokens): string
    {
        $pedacos = [];

        foreach (array_slice($tokens, 0, 6) as $token) {
            $pedacos[] = $token->valor;
        }

        $resumo = implode(' ', $pedacos);

        if (count($tokens) > 6) {
            $resumo .= ' ...';
        }

        return $resumo;
    }
}
