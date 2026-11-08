<?php

namespace SqlToEloquent\Analise;

class TokenizadorSql
{
    /** @return list<Token> */
    public function tokenizar(string $sql): array
    {
        $tokens = [];
        $tamanho = strlen($sql);
        $i = 0;

        while ($i < $tamanho) {
            $char = $sql[$i];

            if (ctype_space($char)) {
                $i++;
                continue;
            }

            if ($char === '-' && ($i + 1) < $tamanho && $sql[$i + 1] === '-') {
                $i = $this->avancarAteFimLinha($sql, $i + 2);
                continue;
            }

            if ($char === '#') {
                $i = $this->avancarAteFimLinha($sql, $i + 1);
                continue;
            }

            if ($char === '/' && ($i + 1) < $tamanho && $sql[$i + 1] === '*') {
                $i = $this->avancarComentarioBloco($sql, $i + 2);
                continue;
            }

            if ($char === '`') {
                [$valor, $i] = $this->lerAspas($sql, $i, '`');
                $tokens[] = new Token(TiposToken::IDENTIFICADOR, $valor, $i);
                continue;
            }

            if ($char === "'" || $char === '"') {
                [$valor, $i] = $this->lerAspas($sql, $i, $char);
                $tokens[] = new Token(TiposToken::STRING, $valor, $i);
                continue;
            }

            if (ctype_digit($char) || ($char === '.' && ($i + 1) < $tamanho && ctype_digit($sql[$i + 1]))) {
                [$valor, $i] = $this->lerNumero($sql, $i);
                $tokens[] = new Token(TiposToken::NUMERO, $valor, $i);
                continue;
            }

            if ($this->ehInicioIdentificador($char)) {
                [$valor, $i] = $this->lerIdentificador($sql, $i);
                $tokens[] = new Token(TiposToken::IDENTIFICADOR, $valor, $i);
                continue;
            }

            $tokens[] = new Token(TiposToken::SIMBOLO, $char, $i);
            $i++;
        }

        return $tokens;
    }

    private function avancarAteFimLinha(string $sql, int $i): int
    {
        $tamanho = strlen($sql);

        while ($i < $tamanho && $sql[$i] !== "\n") {
            $i++;
        }

        return $i;
    }

    private function avancarComentarioBloco(string $sql, int $i): int
    {
        $tamanho = strlen($sql);

        while ($i < $tamanho) {
            if ($sql[$i] === '*' && ($i + 1) < $tamanho && $sql[$i + 1] === '/') {
                return $i + 2;
            }
            $i++;
        }

        return $i;
    }

    /** @return array{0: string, 1: int} */
    private function lerAspas(string $sql, int $inicio, string $aspas): array
    {
        $tamanho = strlen($sql);
        $i = $inicio + 1;
        $valor = '';

        while ($i < $tamanho) {
            $char = $sql[$i];

            if ($char === '\\' && ($i + 1) < $tamanho) {
                $valor .= $sql[$i + 1];
                $i += 2;
                continue;
            }

            if ($char === $aspas) {
                if (($i + 1) < $tamanho && $sql[$i + 1] === $aspas) {
                    $valor .= $aspas;
                    $i += 2;
                    continue;
                }

                return [$valor, $i + 1];
            }

            $valor .= $char;
            $i++;
        }

        return [$valor, $i];
    }

    /** @return array{0: string, 1: int} */
    private function lerNumero(string $sql, int $inicio): array
    {
        $tamanho = strlen($sql);
        $i = $inicio;
        $valor = '';

        while ($i < $tamanho && (ctype_digit($sql[$i]) || $sql[$i] === '.')) {
            $valor .= $sql[$i];
            $i++;
        }

        return [$valor, $i];
    }

    /** @return array{0: string, 1: int} */
    private function lerIdentificador(string $sql, int $inicio): array
    {
        $tamanho = strlen($sql);
        $i = $inicio;
        $valor = '';

        while ($i < $tamanho && $this->ehCorpoIdentificador($sql[$i])) {
            $valor .= $sql[$i];
            $i++;
        }

        return [$valor, $i];
    }

    private function ehInicioIdentificador(string $char): bool
    {
        return ctype_alpha($char) || $char === '_';
    }

    private function ehCorpoIdentificador(string $char): bool
    {
        return ctype_alnum($char) || $char === '_';
    }
}
