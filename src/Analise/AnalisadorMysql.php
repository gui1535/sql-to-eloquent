<?php

namespace SqlToEloquent\Analise;

use RuntimeException;
use SqlToEloquent\Dominio\AlteracaoTabela;
use SqlToEloquent\Dominio\IndiceSolto;
use SqlToEloquent\Dominio\Insercao;
use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Dominio\Tabela;

class AnalisadorMysql
{
    public function __construct(
        private readonly TokenizadorSql $tokenizador = new TokenizadorSql,
        private readonly InterpretadorCreateTable $interpretador = new InterpretadorCreateTable,
        private readonly InterpretadorInsert $interpretadorInsert = new InterpretadorInsert,
        private readonly InterpretadorAlterTable $interpretadorAlter = new InterpretadorAlterTable,
        private readonly InterpretadorCreateIndex $interpretadorIndex = new InterpretadorCreateIndex,
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

        /** @var array<string, Tabela> $tabelas */
        $tabelas = [];
        $insercoes = [];
        $avisos = [];

        foreach ($statements as $statement) {
            if ($statement === []) {
                continue;
            }

            $resultado = $this->processarStatement($statement);
            $avisos = array_merge($avisos, $resultado['avisos']);

            if ($resultado['tabela'] !== null) {
                $tabelas[$resultado['tabela']->nome] = $resultado['tabela'];
            }

            if ($resultado['insercao'] !== null) {
                $insercoes[] = $resultado['insercao'];
            }

            if ($resultado['alteracao'] !== null) {
                $avisos = array_merge($avisos, $resultado['alteracao']->avisos);
                $tabelas = $this->aplicarAlteracao($tabelas, $resultado['alteracao'], $avisos);
            }

            if ($resultado['indice'] !== null) {
                $tabelas = $this->aplicarIndice($tabelas, $resultado['indice'], $avisos);
            }
        }

        return new Schema(
            tabelas: array_values($tabelas),
            insercoes: $insercoes,
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
     * @return array{
     *   tabela: ?Tabela,
     *   insercao: ?Insercao,
     *   alteracao: ?AlteracaoTabela,
     *   indice: ?IndiceSolto,
     *   avisos: list<string>
     * }
     */
    private function processarStatement(array $tokens): array
    {
        $vazio = [
            'tabela' => null,
            'insercao' => null,
            'alteracao' => null,
            'indice' => null,
            'avisos' => [],
        ];

        $primeiro = $tokens[0] ?? null;

        if ($primeiro === null) {
            return $vazio;
        }

        if ($this->ehCreateTable($tokens)) {
            try {
                $resultado = $this->interpretador->interpretar($tokens);

                return [
                    'tabela' => $resultado['tabela'],
                    'insercao' => null,
                    'alteracao' => null,
                    'indice' => null,
                    'avisos' => $resultado['avisos'],
                ];
            } catch (RuntimeException $erro) {
                $nome = $this->tentarExtrairNomeTabela($tokens) ?? 'desconhecida';

                return [
                    ...$vazio,
                    'avisos' => ["Falha ao interpretar CREATE TABLE '{$nome}': {$erro->getMessage()}"],
                ];
            }
        }

        if ($this->ehCreateIndex($tokens)) {
            try {
                return [
                    ...$vazio,
                    'indice' => $this->interpretadorIndex->interpretar($tokens),
                ];
            } catch (RuntimeException $erro) {
                return [
                    ...$vazio,
                    'avisos' => ["Falha ao interpretar CREATE INDEX: {$erro->getMessage()}"],
                ];
            }
        }

        if ($this->ehAlterTable($tokens)) {
            try {
                return [
                    ...$vazio,
                    'alteracao' => $this->interpretadorAlter->interpretar($tokens),
                ];
            } catch (RuntimeException $erro) {
                return [
                    ...$vazio,
                    'avisos' => ["Falha ao interpretar ALTER TABLE: {$erro->getMessage()}"],
                ];
            }
        }

        if ($this->ehInsert($tokens)) {
            try {
                return [
                    ...$vazio,
                    'insercao' => $this->interpretadorInsert->interpretar($tokens),
                ];
            } catch (RuntimeException $erro) {
                return [
                    ...$vazio,
                    'avisos' => ["Falha ao interpretar INSERT: {$erro->getMessage()}"],
                ];
            }
        }

        if ($this->ehView($tokens)) {
            return [
                ...$vazio,
                'avisos' => ['CREATE VIEW ainda não é suportado e foi ignorado: '.$this->resumoStatement($tokens)],
            ];
        }

        if ($this->deveIgnorar($tokens)) {
            return $vazio;
        }

        return [
            ...$vazio,
            'avisos' => ['Statement ainda não suportado e foi ignorado: '.$this->resumoStatement($tokens)],
        ];
    }

    /**
     * @param  array<string, Tabela>  $tabelas
     * @param  list<string>  $avisos
     * @return array<string, Tabela>
     */
    private function aplicarAlteracao(array $tabelas, AlteracaoTabela $alteracao, array &$avisos): array
    {
        if (! isset($tabelas[$alteracao->tabela])) {
            $avisos[] = "ALTER TABLE '{$alteracao->tabela}' ignorado: tabela nao encontrada no schema.";

            return $tabelas;
        }

        $tabela = $tabelas[$alteracao->tabela];

        foreach ($alteracao->colunasRemovidas as $nome) {
            $tabela = $tabela->semColuna($nome);
        }

        foreach ($alteracao->colunasAdicionadas as $coluna) {
            $tabela = $tabela->comColuna($coluna);
        }

        foreach ($alteracao->colunasModificadas as $coluna) {
            $tabela = $tabela->comColuna($coluna);
        }

        foreach ($alteracao->indicesAdicionados as $indice) {
            $tabela = $tabela->comIndice($indice);
        }

        foreach ($alteracao->fksAdicionadas as $fk) {
            $tabela = $tabela->comChaveEstrangeira($fk);
        }

        if ($alteracao->chavePrimaria !== null) {
            $tabela = $tabela->comChavePrimaria($alteracao->chavePrimaria);
        }

        $tabelas[$alteracao->tabela] = $tabela;

        return $tabelas;
    }

    /**
     * @param  array<string, Tabela>  $tabelas
     * @param  list<string>  $avisos
     * @return array<string, Tabela>
     */
    private function aplicarIndice(array $tabelas, IndiceSolto $indiceSolto, array &$avisos): array
    {
        if (! isset($tabelas[$indiceSolto->tabela])) {
            $avisos[] = "CREATE INDEX em '{$indiceSolto->tabela}' ignorado: tabela nao encontrada no schema.";

            return $tabelas;
        }

        $tabelas[$indiceSolto->tabela] = $tabelas[$indiceSolto->tabela]->comIndice($indiceSolto->indice);

        return $tabelas;
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
    private function ehCreateIndex(array $tokens): bool
    {
        $leitor = new LeitorTokens($tokens);

        if (! $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'CREATE')) {
            return false;
        }

        $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'UNIQUE')
            ?? $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'FULLTEXT')
            ?? $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'SPATIAL');

        return $leitor->atual()?->eh(TiposToken::IDENTIFICADOR, 'INDEX') === true
            || $leitor->atual()?->eh(TiposToken::IDENTIFICADOR, 'KEY') === true;
    }

    /** @param list<Token> $tokens */
    private function ehAlterTable(array $tokens): bool
    {
        $leitor = new LeitorTokens($tokens);

        return $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'ALTER') !== null
            && $leitor->atual()?->eh(TiposToken::IDENTIFICADOR, 'TABLE') === true;
    }

    /** @param list<Token> $tokens */
    private function ehInsert(array $tokens): bool
    {
        return ($tokens[0] ?? null)?->eh(TiposToken::IDENTIFICADOR, 'INSERT') === true;
    }

    /** @param list<Token> $tokens */
    private function ehView(array $tokens): bool
    {
        foreach (array_slice($tokens, 0, 8) as $token) {
            if ($token->eh(TiposToken::IDENTIFICADOR, 'VIEW')) {
                return true;
            }
        }

        return false;
    }

    /** @param list<Token> $tokens */
    private function deveIgnorar(array $tokens): bool
    {
        $primeiro = strtoupper($tokens[0]->valor);
        $segundo = strtoupper($tokens[1]->valor ?? '');

        $comandosIgnorados = [
            'SET', 'USE', 'UPDATE', 'DELETE', 'REPLACE', 'LOCK', 'UNLOCK',
            'START', 'COMMIT', 'ROLLBACK', 'SAVEPOINT', 'TRUNCATE',
        ];

        if (in_array($primeiro, $comandosIgnorados, true)) {
            return true;
        }

        if ($primeiro === 'DROP' && in_array($segundo, ['TABLE', 'DATABASE', 'SCHEMA', 'VIEW'], true)) {
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
