<?php

namespace SqlToEloquent\Analise;

use RuntimeException;
use SqlToEloquent\Dominio\AlteracaoTabela;
use SqlToEloquent\Dominio\Indice;

class InterpretadorAlterTable
{
    public function __construct(
        private readonly InterpretadorCreateTable $createTable = new InterpretadorCreateTable,
    ) {
    }

    /** @param list<Token> $tokens */
    public function interpretar(array $tokens): AlteracaoTabela
    {
        $leitor = new LeitorTokens($tokens);
        $avisos = [];

        $leitor->exigir(TiposToken::IDENTIFICADOR, 'ALTER');
        $leitor->exigir(TiposToken::IDENTIFICADOR, 'TABLE');

        $tabela = $this->createTable->interpretarNome($leitor);

        $colunasAdicionadas = [];
        $colunasRemovidas = [];
        $colunasModificadas = [];
        $indicesAdicionados = [];
        $fksAdicionadas = [];
        $chavePrimaria = null;

        do {
            $resultado = $this->lerAcao($leitor, $avisos);

            foreach ($resultado['colunas_adicionadas'] as $coluna) {
                $colunasAdicionadas[] = $coluna;
            }
            foreach ($resultado['colunas_removidas'] as $coluna) {
                $colunasRemovidas[] = $coluna;
            }
            foreach ($resultado['colunas_modificadas'] as $coluna) {
                $colunasModificadas[] = $coluna;
            }
            foreach ($resultado['indices'] as $indice) {
                $indicesAdicionados[] = $indice;
            }
            foreach ($resultado['fks'] as $fk) {
                $fksAdicionadas[] = $fk;
            }
            if ($resultado['chave_primaria'] !== null) {
                $chavePrimaria = $resultado['chave_primaria'];
            }
        } while ($leitor->consumirSe(TiposToken::SIMBOLO, ','));

        return new AlteracaoTabela(
            tabela: $tabela,
            colunasAdicionadas: $colunasAdicionadas,
            colunasRemovidas: $colunasRemovidas,
            colunasModificadas: $colunasModificadas,
            indicesAdicionados: $indicesAdicionados,
            fksAdicionadas: $fksAdicionadas,
            chavePrimaria: $chavePrimaria,
            avisos: $avisos,
        );
    }

    /**
     * @return array{
     *   colunas_adicionadas: list<\SqlToEloquent\Dominio\Coluna>,
     *   colunas_removidas: list<string>,
     *   colunas_modificadas: list<\SqlToEloquent\Dominio\Coluna>,
     *   indices: list<Indice>,
     *   fks: list<\SqlToEloquent\Dominio\ChaveEstrangeira>,
     *   chave_primaria: list<string>|null
     * }
     */
    private function lerAcao(LeitorTokens $leitor, array &$avisos): array
    {
        $vazio = [
            'colunas_adicionadas' => [],
            'colunas_removidas' => [],
            'colunas_modificadas' => [],
            'indices' => [],
            'fks' => [],
            'chave_primaria' => null,
        ];

        $token = $leitor->atual();

        if ($token === null) {
            throw new RuntimeException('ALTER TABLE incompleto.');
        }

        if ($token->eh(TiposToken::IDENTIFICADOR, 'ADD')) {
            $leitor->consumir();

            if ($leitor->consumirSe(TiposToken::IDENTIFICADOR, 'COLUMN')) {
                $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'IF');
                $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'NOT');
                $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'EXISTS');
                $coluna = $this->createTable->interpretarColuna($leitor, $avisos);
                $vazio['colunas_adicionadas'][] = $coluna['coluna'];

                if ($coluna['pk_inline']) {
                    $vazio['chave_primaria'] = [$coluna['coluna']->nome];
                }

                if ($coluna['unique_inline']) {
                    $vazio['indices'][] = new Indice(
                        nome: $coluna['coluna']->nome.'_unique',
                        colunas: [$coluna['coluna']->nome],
                        unico: true,
                        tipo: 'unique',
                    );
                }

                $this->pularPosicaoColuna($leitor);

                return $vazio;
            }

            if ($leitor->atual()?->eh(TiposToken::IDENTIFICADOR, 'CONSTRAINT')) {
                $leitor->consumir();
                $nome = $this->createTable->interpretarNome($leitor);

                if ($leitor->atual()?->eh(TiposToken::IDENTIFICADOR, 'FOREIGN')) {
                    $vazio['fks'][] = $this->createTable->interpretarForeignKey($leitor, $nome);

                    return $vazio;
                }

                if ($leitor->atual()?->eh(TiposToken::IDENTIFICADOR, 'UNIQUE')) {
                    $leitor->consumir();
                    $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'KEY')
                        ?? $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'INDEX');
                    $colunas = $this->createTable->interpretarListaNomes($leitor);
                    $vazio['indices'][] = new Indice($nome, $colunas, true, 'unique');

                    return $vazio;
                }

                if ($leitor->atual()?->eh(TiposToken::IDENTIFICADOR, 'PRIMARY')) {
                    $leitor->consumir();
                    $leitor->exigir(TiposToken::IDENTIFICADOR, 'KEY');
                    $vazio['chave_primaria'] = $this->createTable->interpretarListaNomes($leitor);

                    return $vazio;
                }

                $avisos[] = "ALTER TABLE ADD CONSTRAINT '{$nome}' nao suportado.";
                $this->pularAteProximaVirgula($leitor);

                return $vazio;
            }

            if ($leitor->atual()?->eh(TiposToken::IDENTIFICADOR, 'PRIMARY')) {
                $leitor->consumir();
                $leitor->exigir(TiposToken::IDENTIFICADOR, 'KEY');
                $vazio['chave_primaria'] = $this->createTable->interpretarListaNomes($leitor);

                return $vazio;
            }

            if ($leitor->atual()?->eh(TiposToken::IDENTIFICADOR, 'UNIQUE')) {
                $leitor->consumir();
                $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'KEY')
                    ?? $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'INDEX');
                $nome = null;
                if ($leitor->atual()?->tipo === TiposToken::IDENTIFICADOR
                    && ! $leitor->atual()->eh(TiposToken::SIMBOLO, '(')
                ) {
                    $nome = $this->createTable->interpretarNome($leitor);
                }
                $colunas = $this->createTable->interpretarListaNomes($leitor);
                $vazio['indices'][] = new Indice(
                    $nome ?? implode('_', $colunas).'_unique',
                    $colunas,
                    true,
                    'unique',
                );

                return $vazio;
            }

            if ($leitor->atual()?->eh(TiposToken::IDENTIFICADOR, 'FOREIGN')) {
                $vazio['fks'][] = $this->createTable->interpretarForeignKey($leitor, null);

                return $vazio;
            }

            if ($leitor->atual()?->eh(TiposToken::IDENTIFICADOR, 'INDEX')
                || $leitor->atual()?->eh(TiposToken::IDENTIFICADOR, 'KEY')
                || $leitor->atual()?->eh(TiposToken::IDENTIFICADOR, 'FULLTEXT')
                || $leitor->atual()?->eh(TiposToken::IDENTIFICADOR, 'SPATIAL')
            ) {
                $tipo = 'index';
                $unico = false;

                if ($leitor->consumirSe(TiposToken::IDENTIFICADOR, 'FULLTEXT')) {
                    $tipo = 'fulltext';
                    $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'INDEX')
                        ?? $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'KEY');
                } elseif ($leitor->consumirSe(TiposToken::IDENTIFICADOR, 'SPATIAL')) {
                    $tipo = 'spatial';
                    $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'INDEX')
                        ?? $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'KEY');
                } else {
                    $leitor->consumir();
                }

                $nome = null;
                if ($leitor->atual()?->tipo === TiposToken::IDENTIFICADOR
                    && ! $leitor->atual()->eh(TiposToken::SIMBOLO, '(')
                ) {
                    $nome = $this->createTable->interpretarNome($leitor);
                }

                $colunas = $this->createTable->interpretarListaNomes($leitor);
                $vazio['indices'][] = new Indice(
                    $nome ?? implode('_', $colunas).'_'.$tipo,
                    $colunas,
                    $unico,
                    $tipo,
                );

                return $vazio;
            }

            // ADD coluna sem a palavra COLUMN
            if ($leitor->atual()?->tipo === TiposToken::IDENTIFICADOR
                || $leitor->atual()?->tipo === TiposToken::STRING
            ) {
                $coluna = $this->createTable->interpretarColuna($leitor, $avisos);
                $vazio['colunas_adicionadas'][] = $coluna['coluna'];
                $this->pularPosicaoColuna($leitor);

                return $vazio;
            }

            $avisos[] = 'ALTER TABLE ADD nao reconhecido e foi ignorado.';
            $this->pularAteProximaVirgula($leitor);

            return $vazio;
        }

        if ($token->eh(TiposToken::IDENTIFICADOR, 'DROP')) {
            $leitor->consumir();

            if ($leitor->consumirSe(TiposToken::IDENTIFICADOR, 'COLUMN')
                || $leitor->atual()?->tipo === TiposToken::IDENTIFICADOR
                || $leitor->atual()?->tipo === TiposToken::STRING
            ) {
                if ($leitor->atual()?->eh(TiposToken::IDENTIFICADOR, 'PRIMARY')
                    || $leitor->atual()?->eh(TiposToken::IDENTIFICADOR, 'INDEX')
                    || $leitor->atual()?->eh(TiposToken::IDENTIFICADOR, 'KEY')
                    || $leitor->atual()?->eh(TiposToken::IDENTIFICADOR, 'FOREIGN')
                    || $leitor->atual()?->eh(TiposToken::IDENTIFICADOR, 'CONSTRAINT')
                ) {
                    $avisos[] = 'ALTER TABLE DROP de indice/constraint tem suporte limitado e foi ignorado.';
                    $this->pularAteProximaVirgula($leitor);

                    return $vazio;
                }

                $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'IF');
                $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'EXISTS');
                $vazio['colunas_removidas'][] = $this->createTable->interpretarNome($leitor);

                return $vazio;
            }

            $avisos[] = 'ALTER TABLE DROP nao reconhecido e foi ignorado.';
            $this->pularAteProximaVirgula($leitor);

            return $vazio;
        }

        if ($token->eh(TiposToken::IDENTIFICADOR, 'MODIFY')
            || $token->eh(TiposToken::IDENTIFICADOR, 'CHANGE')
        ) {
            $acao = strtolower($leitor->consumir()->valor);
            $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'COLUMN');

            if ($acao === 'change') {
                $this->createTable->interpretarNome($leitor);
            }

            $coluna = $this->createTable->interpretarColuna($leitor, $avisos);
            $vazio['colunas_modificadas'][] = $coluna['coluna'];
            $this->pularPosicaoColuna($leitor);

            return $vazio;
        }

        if ($token->eh(TiposToken::IDENTIFICADOR, 'RENAME')
            || $token->eh(TiposToken::IDENTIFICADOR, 'CONVERT')
            || $token->eh(TiposToken::IDENTIFICADOR, 'ENGINE')
            || $token->eh(TiposToken::IDENTIFICADOR, 'DEFAULT')
            || $token->eh(TiposToken::IDENTIFICADOR, 'CHARACTER')
            || $token->eh(TiposToken::IDENTIFICADOR, 'COLLATE')
            || $token->eh(TiposToken::IDENTIFICADOR, 'COMMENT')
        ) {
            $avisos[] = "ALTER TABLE {$token->valor} ignorado.";
            $this->pularAteProximaVirgula($leitor);

            return $vazio;
        }

        $avisos[] = "Acao ALTER TABLE '{$token->valor}' nao suportada.";
        $this->pularAteProximaVirgula($leitor);

        return $vazio;
    }

    private function pularPosicaoColuna(LeitorTokens $leitor): void
    {
        if ($leitor->consumirSe(TiposToken::IDENTIFICADOR, 'FIRST')) {
            return;
        }

        if ($leitor->consumirSe(TiposToken::IDENTIFICADOR, 'AFTER')) {
            $this->createTable->interpretarNome($leitor);
        }
    }

    private function pularAteProximaVirgula(LeitorTokens $leitor): void
    {
        $nivel = 0;

        while (! $leitor->fim()) {
            $token = $leitor->atual();

            if ($token === null) {
                return;
            }

            if ($token->eh(TiposToken::SIMBOLO, '(')) {
                $nivel++;
                $leitor->consumir();
                continue;
            }

            if ($token->eh(TiposToken::SIMBOLO, ')')) {
                if ($nivel === 0) {
                    return;
                }
                $nivel--;
                $leitor->consumir();
                continue;
            }

            if ($nivel === 0 && $token->eh(TiposToken::SIMBOLO, ',')) {
                return;
            }

            $leitor->consumir();
        }
    }
}
