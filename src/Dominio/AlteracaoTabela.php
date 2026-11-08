<?php

namespace SqlToEloquent\Dominio;

class AlteracaoTabela
{
    /**
     * @param  list<Coluna>  $colunasAdicionadas
     * @param  list<string>  $colunasRemovidas
     * @param  list<Coluna>  $colunasModificadas
     * @param  list<Indice>  $indicesAdicionados
     * @param  list<ChaveEstrangeira>  $fksAdicionadas
     * @param  list<string>|null  $chavePrimaria
     */
    public function __construct(
        public readonly string $tabela,
        public readonly array $colunasAdicionadas = [],
        public readonly array $colunasRemovidas = [],
        public readonly array $colunasModificadas = [],
        public readonly array $indicesAdicionados = [],
        public readonly array $fksAdicionadas = [],
        public readonly ?array $chavePrimaria = null,
        public readonly array $avisos = [],
    ) {
    }
}
