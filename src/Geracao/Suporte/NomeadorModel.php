<?php

namespace SqlToEloquent\Geracao\Suporte;

use Illuminate\Support\Str;

class NomeadorModel
{
    public function classe(string $tabela): string
    {
        return Str::studly($this->singular($tabela));
    }

    public function metodoBelongsTo(string $colunaFk, string $tabelaReferenciada): string
    {
        if (str_ends_with($colunaFk, '_id')) {
            return Str::camel(substr($colunaFk, 0, -3));
        }

        return Str::camel($this->singular($tabelaReferenciada));
    }

    public function metodoHasMany(string $tabelaRelacionada): string
    {
        return Str::camel($tabelaRelacionada);
    }

    public function metodoHasOne(string $tabelaRelacionada): string
    {
        return Str::camel($this->singular($tabelaRelacionada));
    }

    public function singular(string $nome): string
    {
        $partes = explode('_', $nome);

        return implode('_', array_map(
            fn (string $parte) => $this->singularPalavra($parte),
            $partes,
        ));
    }

    private function singularPalavra(string $palavra): string
    {
        $palavra = strtolower($palavra);

        $regras = [
            '/oes$/' => 'ao',
            '/aes$/' => 'ao',
            '/ais$/' => 'al',
            '/eis$/' => 'el',
            '/ois$/' => 'ol',
            '/ens$/' => 'em',
            '/ns$/' => 'm',
            '/is$/' => 'il',
            '/res$/' => 'r',
            '/ses$/' => 's',
            '/zes$/' => 'z',
            '/s$/' => '',
        ];

        foreach ($regras as $padrao => $substituicao) {
            $resultado = preg_replace($padrao, $substituicao, $palavra, 1, $quantidade);

            if ($quantidade > 0) {
                return $resultado;
            }
        }

        return $palavra;
    }
}
