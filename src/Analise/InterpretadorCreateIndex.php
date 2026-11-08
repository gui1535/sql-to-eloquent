<?php

namespace SqlToEloquent\Analise;

use RuntimeException;
use SqlToEloquent\Dominio\Indice;
use SqlToEloquent\Dominio\IndiceSolto;

class InterpretadorCreateIndex
{
    public function __construct(
        private readonly InterpretadorCreateTable $createTable = new InterpretadorCreateTable,
    ) {
    }

    /** @param list<Token> $tokens */
    public function interpretar(array $tokens): IndiceSolto
    {
        $leitor = new LeitorTokens($tokens);
        $leitor->exigir(TiposToken::IDENTIFICADOR, 'CREATE');

        $unico = false;
        $tipo = 'index';

        if ($leitor->consumirSe(TiposToken::IDENTIFICADOR, 'UNIQUE')) {
            $unico = true;
            $tipo = 'unique';
        } elseif ($leitor->consumirSe(TiposToken::IDENTIFICADOR, 'FULLTEXT')) {
            $tipo = 'fulltext';
        } elseif ($leitor->consumirSe(TiposToken::IDENTIFICADOR, 'SPATIAL')) {
            $tipo = 'spatial';
        }

        if (! $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'INDEX')
            && ! $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'KEY')
        ) {
            throw new RuntimeException('CREATE INDEX esperado.');
        }

        $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'IF');
        $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'NOT');
        $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'EXISTS');

        $nome = null;
        if ($leitor->atual()?->tipo === TiposToken::IDENTIFICADOR
            && ! $leitor->atual()->eh(TiposToken::IDENTIFICADOR, 'ON')
        ) {
            $nome = $this->createTable->interpretarNome($leitor);
        }

        $leitor->exigir(TiposToken::IDENTIFICADOR, 'ON');
        $tabela = $this->createTable->interpretarNome($leitor);
        $colunas = $this->createTable->interpretarListaNomes($leitor);

        return new IndiceSolto(
            tabela: $tabela,
            indice: new Indice(
                nome: $nome ?? implode('_', $colunas).'_'.$tipo,
                colunas: $colunas,
                unico: $unico,
                tipo: $tipo,
            ),
        );
    }
}
