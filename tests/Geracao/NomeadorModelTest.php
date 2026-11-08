<?php

namespace SqlToEloquent\Tests\Geracao;

use PHPUnit\Framework\TestCase;
use SqlToEloquent\Geracao\Suporte\NomeadorModel;

class NomeadorModelTest extends TestCase
{
    public function test_singulariza_portugues_comum(): void
    {
        $nomeador = new NomeadorModel;

        $this->assertSame('cupom', $nomeador->singular('cupons'));
        $this->assertSame('fornecedor', $nomeador->singular('fornecedores'));
        $this->assertSame('usuario', $nomeador->singular('usuarios'));
        $this->assertSame('pedido', $nomeador->singular('pedidos'));
        $this->assertSame('cupom', $nomeador->variavel('cupons'));
        $this->assertSame('fornecedor', $nomeador->variavel('fornecedores'));
    }
}
