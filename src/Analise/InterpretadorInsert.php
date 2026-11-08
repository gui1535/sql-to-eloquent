<?php

namespace SqlToEloquent\Analise;

use RuntimeException;
use SqlToEloquent\Dominio\Insercao;

class InterpretadorInsert
{
    /** @param list<Token> $tokens */
    public function interpretar(array $tokens): Insercao
    {
        $leitor = new LeitorTokens($tokens);
        $leitor->exigir(TiposToken::IDENTIFICADOR, 'INSERT');
        $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'IGNORE');
        $leitor->exigir(TiposToken::IDENTIFICADOR, 'INTO');

        $tabela = $this->lerNome($leitor);
        $colunas = [];

        if ($leitor->atual()?->eh(TiposToken::SIMBOLO, '(')) {
            $colunas = $this->lerListaNomes($leitor);
        }

        $leitor->exigir(TiposToken::IDENTIFICADOR, 'VALUES');
        $linhas = [];

        do {
            $linhas[] = $this->lerLinha($leitor);
        } while ($leitor->consumirSe(TiposToken::SIMBOLO, ','));

        return new Insercao(
            tabela: $tabela,
            colunas: $colunas,
            linhas: $linhas,
        );
    }

    private function lerNome(LeitorTokens $leitor): string
    {
        $token = $leitor->consumir();

        if (! in_array($token->tipo, [TiposToken::IDENTIFICADOR, TiposToken::STRING], true)) {
            throw new RuntimeException("Nome de tabela invalido no INSERT: {$token->valor}");
        }

        $nome = $token->valor;

        if ($leitor->consumirSe(TiposToken::SIMBOLO, '.')) {
            $nome = $leitor->consumir()->valor;
        }

        return $nome;
    }

    /** @return list<string> */
    private function lerListaNomes(LeitorTokens $leitor): array
    {
        $leitor->exigir(TiposToken::SIMBOLO, '(');
        $nomes = [];

        do {
            $nomes[] = $this->lerNome($leitor);
        } while ($leitor->consumirSe(TiposToken::SIMBOLO, ','));

        $leitor->exigir(TiposToken::SIMBOLO, ')');

        return $nomes;
    }

    /** @return list<mixed> */
    private function lerLinha(LeitorTokens $leitor): array
    {
        $leitor->exigir(TiposToken::SIMBOLO, '(');
        $valores = [];

        do {
            $valores[] = $this->lerValor($leitor);
        } while ($leitor->consumirSe(TiposToken::SIMBOLO, ','));

        $leitor->exigir(TiposToken::SIMBOLO, ')');

        return $valores;
    }

    private function lerValor(LeitorTokens $leitor): mixed
    {
        $token = $leitor->atual();

        if ($token === null) {
            throw new RuntimeException('Valor ausente no INSERT.');
        }

        if ($token->eh(TiposToken::IDENTIFICADOR, 'NULL')) {
            $leitor->consumir();

            return null;
        }

        if ($token->tipo === TiposToken::STRING) {
            return $leitor->consumir()->valor;
        }

        if ($token->tipo === TiposToken::NUMERO) {
            $valor = $leitor->consumir()->valor;

            return str_contains($valor, '.') ? $valor : (int) $valor;
        }

        if ($token->eh(TiposToken::SIMBOLO, '-')) {
            $leitor->consumir();
            $numero = $leitor->exigir(TiposToken::NUMERO)->valor;

            return str_contains($numero, '.') ? '-'.$numero : (int) ('-'.$numero);
        }

        if ($token->tipo === TiposToken::IDENTIFICADOR) {
            return $leitor->consumir()->valor;
        }

        throw new RuntimeException("Valor nao suportado no INSERT: {$token->valor}");
    }
}
