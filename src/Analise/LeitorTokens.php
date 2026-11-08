<?php

namespace SqlToEloquent\Analise;

use RuntimeException;

class LeitorTokens
{
    private int $indice = 0;

    /** @param list<Token> $tokens */
    public function __construct(
        private readonly array $tokens,
    ) {
    }

    public function fim(): bool
    {
        return $this->indice >= count($this->tokens);
    }

    public function atual(): ?Token
    {
        return $this->tokens[$this->indice] ?? null;
    }

    public function olhar(int $offset = 0): ?Token
    {
        return $this->tokens[$this->indice + $offset] ?? null;
    }

    public function consumir(): Token
    {
        $token = $this->atual();

        if ($token === null) {
            throw new RuntimeException('Fim inesperado dos tokens SQL.');
        }

        $this->indice++;

        return $token;
    }

    public function consumirSe(string $tipo, ?string $valor = null): ?Token
    {
        $token = $this->atual();

        if ($token === null || ! $token->eh($tipo, $valor)) {
            return null;
        }

        return $this->consumir();
    }

    public function exigir(string $tipo, ?string $valor = null): Token
    {
        $token = $this->consumir();

        if (! $token->eh($tipo, $valor)) {
            $esperado = $valor ?? $tipo;
            throw new RuntimeException("Token inesperado '{$token->valor}', esperado '{$esperado}'.");
        }

        return $token;
    }

    /** @return list<Token> */
    public function restante(): array
    {
        return array_slice($this->tokens, $this->indice);
    }
}
