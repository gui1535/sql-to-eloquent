<?php

namespace SqlToEloquent\Analise;

use RuntimeException;
use SqlToEloquent\Dominio\ChaveEstrangeira;
use SqlToEloquent\Dominio\Coluna;
use SqlToEloquent\Dominio\Indice;
use SqlToEloquent\Dominio\Tabela;

class InterpretadorCreateTable
{
    /** @param list<Token> $tokens */
    public function interpretar(array $tokens): array
    {
        $leitor = new LeitorTokens($tokens);
        $avisos = [];

        $leitor->exigir(TiposToken::IDENTIFICADOR, 'CREATE');

        if ($leitor->consumirSe(TiposToken::IDENTIFICADOR, 'TEMPORARY')) {
            $avisos[] = 'CREATE TEMPORARY TABLE detectado; tratado como tabela normal.';
        }

        $leitor->exigir(TiposToken::IDENTIFICADOR, 'TABLE');

        if ($leitor->consumirSe(TiposToken::IDENTIFICADOR, 'IF')) {
            $leitor->exigir(TiposToken::IDENTIFICADOR, 'NOT');
            $leitor->exigir(TiposToken::IDENTIFICADOR, 'EXISTS');
        }

        $nome = $this->lerNome($leitor);
        $leitor->exigir(TiposToken::SIMBOLO, '(');

        $colunas = [];
        $chavePrimaria = [];
        $indices = [];
        $chavesEstrangeiras = [];

        while (! $leitor->fim() && ! $leitor->atual()?->eh(TiposToken::SIMBOLO, ')')) {
            $resultado = $this->lerDefinicao($leitor, $avisos);

            if ($resultado['coluna'] !== null) {
                $colunas[] = $resultado['coluna'];

                if ($resultado['pk_inline']) {
                    $chavePrimaria = [$resultado['coluna']->nome];
                }

                if ($resultado['unique_inline']) {
                    $indices[] = new Indice(
                        nome: $resultado['coluna']->nome.'_unique',
                        colunas: [$resultado['coluna']->nome],
                        unico: true,
                        tipo: 'unique',
                    );
                }
            }

            if ($resultado['chave_primaria'] !== []) {
                $chavePrimaria = $resultado['chave_primaria'];
            }

            if ($resultado['indice'] !== null) {
                $indices[] = $resultado['indice'];
            }

            if ($resultado['fk'] !== null) {
                $chavesEstrangeiras[] = $resultado['fk'];
            }

            $leitor->consumirSe(TiposToken::SIMBOLO, ',');
        }

        $leitor->exigir(TiposToken::SIMBOLO, ')');

        $opcoes = $this->lerOpcoesTabela($leitor);

        return [
            'tabela' => new Tabela(
                nome: $nome,
                colunas: $colunas,
                chavePrimaria: $chavePrimaria,
                indices: $indices,
                chavesEstrangeiras: $chavesEstrangeiras,
                mecanismo: $opcoes['mecanismo'],
                charset: $opcoes['charset'],
                collation: $opcoes['collation'],
                comentario: $opcoes['comentario'],
            ),
            'avisos' => $avisos,
        ];
    }

    private function lerDefinicao(LeitorTokens $leitor, array &$avisos): array
    {
        $vazio = [
            'coluna' => null,
            'pk_inline' => false,
            'unique_inline' => false,
            'chave_primaria' => [],
            'indice' => null,
            'fk' => null,
        ];

        $atual = $leitor->atual();

        if ($atual === null) {
            throw new RuntimeException('Definicao de tabela incompleta.');
        }

        if ($atual->eh(TiposToken::IDENTIFICADOR, 'PRIMARY')) {
            $leitor->consumir();
            $leitor->exigir(TiposToken::IDENTIFICADOR, 'KEY');
            $vazio['chave_primaria'] = $this->lerListaNomes($leitor);

            return $vazio;
        }

        if ($atual->eh(TiposToken::IDENTIFICADOR, 'UNIQUE')) {
            $leitor->consumir();
            $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'KEY')
                ?? $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'INDEX');

            $nome = null;
            if ($leitor->atual()?->tipo === TiposToken::IDENTIFICADOR
                && ! $leitor->atual()->eh(TiposToken::SIMBOLO, '(')
                && ! $this->ehPalavraRestricao($leitor->atual()->valor)
            ) {
                $nome = $this->lerNome($leitor);
            }

            $colunas = $this->lerListaNomes($leitor);
            $vazio['indice'] = new Indice(
                nome: $nome ?? implode('_', $colunas).'_unique',
                colunas: $colunas,
                unico: true,
                tipo: 'unique',
            );

            return $vazio;
        }

        if ($atual->eh(TiposToken::IDENTIFICADOR, 'KEY')
            || $atual->eh(TiposToken::IDENTIFICADOR, 'INDEX')
        ) {
            $leitor->consumir();
            $nome = null;

            if ($leitor->atual()?->tipo === TiposToken::IDENTIFICADOR
                && ! $leitor->atual()->eh(TiposToken::SIMBOLO, '(')
            ) {
                $nome = $this->lerNome($leitor);
            }

            $colunas = $this->lerListaNomes($leitor);
            $vazio['indice'] = new Indice(
                nome: $nome ?? implode('_', $colunas).'_index',
                colunas: $colunas,
                unico: false,
                tipo: 'index',
            );

            return $vazio;
        }

        if ($atual->eh(TiposToken::IDENTIFICADOR, 'FULLTEXT')
            || $atual->eh(TiposToken::IDENTIFICADOR, 'SPATIAL')
        ) {
            $tipo = strtolower($leitor->consumir()->valor);
            $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'KEY')
                ?? $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'INDEX');

            $nome = null;
            if ($leitor->atual()?->tipo === TiposToken::IDENTIFICADOR
                && ! $leitor->atual()->eh(TiposToken::SIMBOLO, '(')
            ) {
                $nome = $this->lerNome($leitor);
            }

            $colunas = $this->lerListaNomes($leitor);
            $vazio['indice'] = new Indice(
                nome: $nome ?? implode('_', $colunas).'_'.$tipo,
                colunas: $colunas,
                unico: false,
                tipo: $tipo,
            );
            $avisos[] = "Indice {$tipo} detectado na tabela; suporte limitado na geracao.";

            return $vazio;
        }

        if ($atual->eh(TiposToken::IDENTIFICADOR, 'CONSTRAINT')) {
            $leitor->consumir();
            $nomeConstraint = $this->lerNome($leitor);

            if ($leitor->atual()?->eh(TiposToken::IDENTIFICADOR, 'PRIMARY')) {
                $leitor->consumir();
                $leitor->exigir(TiposToken::IDENTIFICADOR, 'KEY');
                $vazio['chave_primaria'] = $this->lerListaNomes($leitor);

                return $vazio;
            }

            if ($leitor->atual()?->eh(TiposToken::IDENTIFICADOR, 'UNIQUE')) {
                $leitor->consumir();
                $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'KEY')
                    ?? $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'INDEX');
                $colunas = $this->lerListaNomes($leitor);
                $vazio['indice'] = new Indice(
                    nome: $nomeConstraint,
                    colunas: $colunas,
                    unico: true,
                    tipo: 'unique',
                );

                return $vazio;
            }

            if ($leitor->atual()?->eh(TiposToken::IDENTIFICADOR, 'FOREIGN')) {
                $vazio['fk'] = $this->lerForeignKey($leitor, $nomeConstraint);

                return $vazio;
            }

            $avisos[] = "Constraint '{$nomeConstraint}' nao suportada e foi ignorada.";
            $this->pularAteProximaVirgulaOuFecha($leitor);

            return $vazio;
        }

        if ($atual->eh(TiposToken::IDENTIFICADOR, 'FOREIGN')) {
            $vazio['fk'] = $this->lerForeignKey($leitor, null);

            return $vazio;
        }

        if ($atual->eh(TiposToken::IDENTIFICADOR, 'CHECK')) {
            $avisos[] = 'Constraint CHECK ignorada.';
            $this->pularAteProximaVirgulaOuFecha($leitor);

            return $vazio;
        }

        return $this->lerColuna($leitor, $avisos);
    }

    private function lerColuna(LeitorTokens $leitor, array &$avisos): array
    {
        $nome = $this->lerNome($leitor);
        $tipoToken = $leitor->exigir(TiposToken::IDENTIFICADOR);
        $tipo = strtolower($tipoToken->valor);

        $tamanho = null;
        $precisao = null;
        $escala = null;
        $valoresEnum = [];

        if (in_array($tipo, ['enum', 'set'], true)) {
            $valoresEnum = $this->lerListaStrings($leitor);
        } elseif ($leitor->consumirSe(TiposToken::SIMBOLO, '(')) {
            $primeiro = $leitor->consumir();
            $tamanho = (int) $primeiro->valor;

            if ($leitor->consumirSe(TiposToken::SIMBOLO, ',')) {
                $precisao = $tamanho;
                $escala = (int) $leitor->consumir()->valor;
                $tamanho = null;
            }

            $leitor->exigir(TiposToken::SIMBOLO, ')');
        }

        $unsigned = false;
        $nulo = true;
        $padrao = null;
        $autoIncremento = false;
        $comentario = null;
        $pkInline = false;
        $uniqueInline = false;

        while (! $leitor->fim()) {
            $token = $leitor->atual();

            if ($token === null
                || $token->eh(TiposToken::SIMBOLO, ',')
                || $token->eh(TiposToken::SIMBOLO, ')')
            ) {
                break;
            }

            if ($token->eh(TiposToken::IDENTIFICADOR, 'UNSIGNED')) {
                $leitor->consumir();
                $unsigned = true;
                continue;
            }

            if ($token->eh(TiposToken::IDENTIFICADOR, 'ZEROFILL')) {
                $leitor->consumir();
                continue;
            }

            if ($token->eh(TiposToken::IDENTIFICADOR, 'CHARACTER')) {
                $leitor->consumir();
                $leitor->exigir(TiposToken::IDENTIFICADOR, 'SET');
                $leitor->consumir();
                continue;
            }

            if ($token->eh(TiposToken::IDENTIFICADOR, 'COLLATE')) {
                $leitor->consumir();
                $leitor->consumir();
                continue;
            }

            if ($token->eh(TiposToken::IDENTIFICADOR, 'NOT')) {
                $leitor->consumir();
                $leitor->exigir(TiposToken::IDENTIFICADOR, 'NULL');
                $nulo = false;
                continue;
            }

            if ($token->eh(TiposToken::IDENTIFICADOR, 'NULL')) {
                $leitor->consumir();
                $nulo = true;
                continue;
            }

            if ($token->eh(TiposToken::IDENTIFICADOR, 'DEFAULT')) {
                $leitor->consumir();
                $padrao = $this->lerValorPadrao($leitor);
                continue;
            }

            if ($token->eh(TiposToken::IDENTIFICADOR, 'AUTO_INCREMENT')) {
                $leitor->consumir();
                $autoIncremento = true;
                continue;
            }

            if ($token->eh(TiposToken::IDENTIFICADOR, 'PRIMARY')) {
                $leitor->consumir();
                $leitor->exigir(TiposToken::IDENTIFICADOR, 'KEY');
                $pkInline = true;
                $nulo = false;
                continue;
            }

            if ($token->eh(TiposToken::IDENTIFICADOR, 'UNIQUE')) {
                $leitor->consumir();
                $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'KEY');
                $uniqueInline = true;
                continue;
            }

            if ($token->eh(TiposToken::IDENTIFICADOR, 'COMMENT')) {
                $leitor->consumir();
                $comentario = $leitor->exigir(TiposToken::STRING)->valor;
                continue;
            }

            if ($token->eh(TiposToken::IDENTIFICADOR, 'ON')) {
                $leitor->consumir();
                $leitor->exigir(TiposToken::IDENTIFICADOR, 'UPDATE');
                $this->lerValorPadrao($leitor);
                continue;
            }

            if ($token->eh(TiposToken::IDENTIFICADOR, 'REFERENCES')) {
                $avisos[] = "REFERENCES inline na coluna '{$nome}' ignorado; use FOREIGN KEY.";
                $this->pularAteProximaVirgulaOuFecha($leitor);
                break;
            }

            if ($token->eh(TiposToken::IDENTIFICADOR, 'GENERATED')
                || $token->eh(TiposToken::IDENTIFICADOR, 'AS')
                || $token->eh(TiposToken::IDENTIFICADOR, 'VIRTUAL')
                || $token->eh(TiposToken::IDENTIFICADOR, 'STORED')
            ) {
                $avisos[] = "Coluna gerada '{$nome}' tem suporte limitado.";
                $leitor->consumir();
                continue;
            }

            if ($token->eh(TiposToken::IDENTIFICADOR, 'VISIBLE')
                || $token->eh(TiposToken::IDENTIFICADOR, 'INVISIBLE')
                || $token->eh(TiposToken::IDENTIFICADOR, 'COLUMN_FORMAT')
                || $token->eh(TiposToken::IDENTIFICADOR, 'STORAGE')
            ) {
                $leitor->consumir();
                if ($leitor->atual()?->tipo === TiposToken::IDENTIFICADOR) {
                    $leitor->consumir();
                }
                continue;
            }

            $avisos[] = "Atributo '{$token->valor}' na coluna '{$nome}' foi ignorado.";
            $leitor->consumir();
        }

        return [
            'coluna' => new Coluna(
                nome: $nome,
                tipo: $tipo,
                nulo: $nulo,
                padrao: $padrao,
                autoIncremento: $autoIncremento,
                tamanho: $tamanho,
                precisao: $precisao,
                escala: $escala,
                unsigned: $unsigned,
                comentario: $comentario,
                valoresEnum: $valoresEnum,
            ),
            'pk_inline' => $pkInline,
            'unique_inline' => $uniqueInline,
            'chave_primaria' => [],
            'indice' => null,
            'fk' => null,
        ];
    }

    private function lerForeignKey(LeitorTokens $leitor, ?string $nome): ChaveEstrangeira
    {
        $leitor->exigir(TiposToken::IDENTIFICADOR, 'FOREIGN');
        $leitor->exigir(TiposToken::IDENTIFICADOR, 'KEY');

        if ($leitor->atual()?->tipo === TiposToken::IDENTIFICADOR
            && ! $leitor->atual()->eh(TiposToken::SIMBOLO, '(')
        ) {
            $nome = $this->lerNome($leitor);
        }

        $colunas = $this->lerListaNomes($leitor);
        $leitor->exigir(TiposToken::IDENTIFICADOR, 'REFERENCES');
        $tabelaReferenciada = $this->lerNome($leitor);
        $colunasReferenciadas = $this->lerListaNomes($leitor);

        $aoAtualizar = null;
        $aoDeletar = null;

        while ($leitor->atual()?->eh(TiposToken::IDENTIFICADOR, 'ON')) {
            $leitor->consumir();
            $acao = strtolower($leitor->exigir(TiposToken::IDENTIFICADOR)->valor);
            $regra = $this->lerRegraReferencial($leitor);

            if ($acao === 'delete') {
                $aoDeletar = $regra;
            }

            if ($acao === 'update') {
                $aoAtualizar = $regra;
            }
        }

        return new ChaveEstrangeira(
            nome: $nome ?? implode('_', $colunas).'_foreign',
            colunas: $colunas,
            tabelaReferenciada: $tabelaReferenciada,
            colunasReferenciadas: $colunasReferenciadas,
            aoAtualizar: $aoAtualizar,
            aoDeletar: $aoDeletar,
        );
    }

    private function lerRegraReferencial(LeitorTokens $leitor): string
    {
        $primeiro = strtolower($leitor->exigir(TiposToken::IDENTIFICADOR)->valor);

        if (in_array($primeiro, ['cascade', 'restrict'], true)) {
            return $primeiro;
        }

        if ($primeiro === 'set') {
            $segundo = strtolower($leitor->exigir(TiposToken::IDENTIFICADOR)->valor);

            return "set {$segundo}";
        }

        if ($primeiro === 'no') {
            $leitor->exigir(TiposToken::IDENTIFICADOR, 'ACTION');

            return 'no action';
        }

        return $primeiro;
    }

    private function lerOpcoesTabela(LeitorTokens $leitor): array
    {
        $opcoes = [
            'mecanismo' => null,
            'charset' => null,
            'collation' => null,
            'comentario' => null,
        ];

        while (! $leitor->fim() && ! $leitor->atual()?->eh(TiposToken::SIMBOLO, ';')) {
            $token = $leitor->atual();

            if ($token === null) {
                break;
            }

            if ($token->eh(TiposToken::IDENTIFICADOR, 'ENGINE')) {
                $leitor->consumir();
                $leitor->consumirSe(TiposToken::SIMBOLO, '=');
                $opcoes['mecanismo'] = $leitor->consumir()->valor;
                continue;
            }

            if ($token->eh(TiposToken::IDENTIFICADOR, 'DEFAULT')) {
                $leitor->consumir();
                continue;
            }

            if ($token->eh(TiposToken::IDENTIFICADOR, 'CHARSET')
                || ($token->eh(TiposToken::IDENTIFICADOR, 'CHARACTER') && $leitor->olhar(1)?->eh(TiposToken::IDENTIFICADOR, 'SET'))
            ) {
                if ($token->eh(TiposToken::IDENTIFICADOR, 'CHARACTER')) {
                    $leitor->consumir();
                    $leitor->exigir(TiposToken::IDENTIFICADOR, 'SET');
                } else {
                    $leitor->consumir();
                }
                $leitor->consumirSe(TiposToken::SIMBOLO, '=');
                $opcoes['charset'] = $leitor->consumir()->valor;
                continue;
            }

            if ($token->eh(TiposToken::IDENTIFICADOR, 'COLLATE')) {
                $leitor->consumir();
                $leitor->consumirSe(TiposToken::SIMBOLO, '=');
                $opcoes['collation'] = $leitor->consumir()->valor;
                continue;
            }

            if ($token->eh(TiposToken::IDENTIFICADOR, 'COMMENT')) {
                $leitor->consumir();
                $leitor->consumirSe(TiposToken::SIMBOLO, '=');
                $opcoes['comentario'] = $leitor->exigir(TiposToken::STRING)->valor;
                continue;
            }

            if ($token->eh(TiposToken::IDENTIFICADOR, 'AUTO_INCREMENT')
                || $token->eh(TiposToken::IDENTIFICADOR, 'ROW_FORMAT')
                || $token->eh(TiposToken::IDENTIFICADOR, 'KEY_BLOCK_SIZE')
                || $token->eh(TiposToken::IDENTIFICADOR, 'STATS_PERSISTENT')
            ) {
                $leitor->consumir();
                $leitor->consumirSe(TiposToken::SIMBOLO, '=');
                if (! $leitor->fim() && ! $leitor->atual()?->eh(TiposToken::SIMBOLO, ';')) {
                    $leitor->consumir();
                }
                continue;
            }

            $leitor->consumir();
        }

        return $opcoes;
    }

    private function lerNome(LeitorTokens $leitor): string
    {
        $token = $leitor->consumir();

        if (! in_array($token->tipo, [TiposToken::IDENTIFICADOR, TiposToken::STRING], true)) {
            throw new RuntimeException("Nome invalido: {$token->valor}");
        }

        $nome = $token->valor;

        if ($leitor->consumirSe(TiposToken::SIMBOLO, '.')) {
            $nome = $leitor->consumir()->valor;
        }

        return $nome;
    }

    /** @return list<string> */
    private function lerListaNomes(LeitorTokens $leitor): array
    {
        $leitor->exigir(TiposToken::SIMBOLO, '(');
        $nomes = [];

        do {
            $nomes[] = $this->lerNome($leitor);
            if ($leitor->atual()?->eh(TiposToken::SIMBOLO, '(')) {
                $this->pularParenteses($leitor);
            }
            $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'ASC')
                ?? $leitor->consumirSe(TiposToken::IDENTIFICADOR, 'DESC');
        } while ($leitor->consumirSe(TiposToken::SIMBOLO, ','));

        $leitor->exigir(TiposToken::SIMBOLO, ')');

        return $nomes;
    }

    /** @return list<string> */
    private function lerListaStrings(LeitorTokens $leitor): array
    {
        $leitor->exigir(TiposToken::SIMBOLO, '(');
        $valores = [];

        do {
            $valores[] = $leitor->exigir(TiposToken::STRING)->valor;
        } while ($leitor->consumirSe(TiposToken::SIMBOLO, ','));

        $leitor->exigir(TiposToken::SIMBOLO, ')');

        return $valores;
    }

    private function lerValorPadrao(LeitorTokens $leitor): mixed
    {
        $token = $leitor->atual();

        if ($token === null) {
            return null;
        }

        if ($token->eh(TiposToken::IDENTIFICADOR, 'NULL')) {
            $leitor->consumir();

            return null;
        }

        if ($token->tipo === TiposToken::STRING || $token->tipo === TiposToken::NUMERO) {
            return $leitor->consumir()->valor;
        }

        if ($token->eh(TiposToken::IDENTIFICADOR, 'CURRENT_TIMESTAMP')
            || $token->eh(TiposToken::IDENTIFICADOR, 'NOW')
        ) {
            $valor = $leitor->consumir()->valor;
            if ($leitor->atual()?->eh(TiposToken::SIMBOLO, '(')) {
                $this->pularParenteses($leitor);
            }

            return strtoupper($valor) === 'NOW' ? 'CURRENT_TIMESTAMP' : strtoupper($valor);
        }

        if ($token->eh(TiposToken::SIMBOLO, '(')) {
            return $this->capturarParenteses($leitor);
        }

        if ($token->tipo === TiposToken::IDENTIFICADOR) {
            return $leitor->consumir()->valor;
        }

        return $leitor->consumir()->valor;
    }

    private function pularParenteses(LeitorTokens $leitor): void
    {
        $this->capturarParenteses($leitor);
    }

    private function capturarParenteses(LeitorTokens $leitor): string
    {
        $leitor->exigir(TiposToken::SIMBOLO, '(');
        $conteudo = '(';
        $nivel = 1;

        while (! $leitor->fim() && $nivel > 0) {
            $token = $leitor->consumir();

            if ($token->eh(TiposToken::SIMBOLO, '(')) {
                $nivel++;
            }

            if ($token->eh(TiposToken::SIMBOLO, ')')) {
                $nivel--;
            }

            $conteudo .= $token->valor;
        }

        return $conteudo;
    }

    private function pularAteProximaVirgulaOuFecha(LeitorTokens $leitor): void
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

    private function ehPalavraRestricao(string $valor): bool
    {
        return in_array(strtoupper($valor), [
            'PRIMARY', 'UNIQUE', 'KEY', 'INDEX', 'FOREIGN', 'CONSTRAINT', 'CHECK',
            'FULLTEXT', 'SPATIAL',
        ], true);
    }
}
