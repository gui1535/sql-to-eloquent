<?php

namespace SqlToEloquent\Analise;

use RuntimeException;
use SqlToEloquent\Dominio\Schema;

class AnalisadorMysql
{
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
        return new Schema(
            tabelas: [],
            avisos: ['Analise SQL ainda nao implementada.'],
            origem: $origem,
        );
    }
}
