<?php

namespace SqlToEloquent\Escrita;

use RuntimeException;
use SqlToEloquent\Planejamento\ArquivoPlanejado;
use SqlToEloquent\Planejamento\PlanoGeracao;

class EscritorArquivos
{
    /**
     * @return array{criados: list<string>, ignorados: list<string>}
     */
    public function gravar(PlanoGeracao $plano, bool $sobrescrever = false): array
    {
        $criados = [];
        $ignorados = [];

        foreach ($plano->arquivos as $arquivo) {
            if ($arquivo->conflito && ! $sobrescrever) {
                $ignorados[] = $arquivo->caminho;
                continue;
            }

            $this->gravarArquivo($arquivo);
            $criados[] = $arquivo->caminho;
        }

        return [
            'criados' => $criados,
            'ignorados' => $ignorados,
        ];
    }

    private function gravarArquivo(ArquivoPlanejado $arquivo): void
    {
        $diretorio = dirname($arquivo->caminho);

        if (! is_dir($diretorio) && ! mkdir($diretorio, 0755, true) && ! is_dir($diretorio)) {
            throw new RuntimeException("Nao foi possivel criar o diretorio: {$diretorio}");
        }

        if (file_put_contents($arquivo->caminho, $arquivo->conteudo) === false) {
            throw new RuntimeException("Nao foi possivel gravar o arquivo: {$arquivo->caminho}");
        }
    }
}
