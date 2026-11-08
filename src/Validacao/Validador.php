<?php

namespace SqlToEloquent\Validacao;

use SqlToEloquent\Planejamento\ArquivoPlanejado;
use SqlToEloquent\Planejamento\PlanoGeracao;

class Validador
{
    /**
     * @return list<string>
     */
    public function validar(PlanoGeracao $plano): array
    {
        $erros = [];

        foreach ($plano->arquivos as $arquivo) {
            if ($arquivo->conflito) {
                continue;
            }

            foreach ($this->validarArquivo($arquivo) as $erro) {
                $erros[] = $erro;
            }
        }

        return $erros;
    }

    /**
     * @return list<string>
     */
    private function validarArquivo(ArquivoPlanejado $arquivo): array
    {
        $erros = [];

        if ($arquivo->conteudo === '') {
            $erros[] = "Arquivo vazio: {$arquivo->caminho}";
        }

        if (str_ends_with($arquivo->caminho, '.php') && ! $this->sintaxePhpValida($arquivo->conteudo)) {
            $erros[] = "Sintaxe PHP invalida: {$arquivo->caminho}";
        }

        return $erros;
    }

    private function sintaxePhpValida(string $conteudo): bool
    {
        $temporario = tempnam(sys_get_temp_dir(), 'sql_to_eloquent_');

        if ($temporario === false) {
            return true;
        }

        $arquivo = $temporario.'.php';
        rename($temporario, $arquivo);
        file_put_contents($arquivo, $conteudo);

        exec('php -l '.escapeshellarg($arquivo).' 2>&1', $saida, $codigo);
        unlink($arquivo);

        return $codigo === 0;
    }
}
