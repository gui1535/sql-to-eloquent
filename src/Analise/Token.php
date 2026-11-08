<?php

namespace SqlToEloquent\Analise;

class Token
{
    public function __construct(
        public readonly string $tipo,
        public readonly string $valor,
        public readonly int $posicao,
    ) {
    }

    public function eh(string $tipo, ?string $valor = null): bool
    {
        if ($this->tipo !== $tipo) {
            return false;
        }

        if ($valor === null) {
            return true;
        }

        return strcasecmp($this->valor, $valor) === 0;
    }

    public function texto(): string
    {
        return $this->valor;
    }
}
