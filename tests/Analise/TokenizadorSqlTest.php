<?php

namespace SqlToEloquent\Tests\Analise;

use PHPUnit\Framework\TestCase;
use SqlToEloquent\Analise\TiposToken;
use SqlToEloquent\Analise\TokenizadorSql;

class TokenizadorSqlTest extends TestCase
{
    public function test_ignora_comentarios_e_preserva_strings(): void
    {
        $tokens = (new TokenizadorSql)->tokenizar(<<<'SQL'
            -- comentario
            SELECT `nome`, 'a;b' FROM t # fim
            /* bloco */
        SQL);

        $valores = array_map(fn ($token) => $token->valor, $tokens);

        $this->assertSame(
            ['SELECT', 'nome', ',', 'a;b', 'FROM', 't'],
            $valores
        );
        $this->assertSame(TiposToken::STRING, $tokens[3]->tipo);
    }
}
