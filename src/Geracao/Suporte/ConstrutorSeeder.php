<?php

namespace SqlToEloquent\Geracao\Suporte;

use SqlToEloquent\Dominio\Insercao;

class ConstrutorSeeder
{
    public function __construct(
        private readonly NomeadorModel $nomeador = new NomeadorModel,
    ) {
    }

    public function nomeClasse(string $tabela): string
    {
        return $this->nomeador->classe($tabela).'Seeder';
    }

    public function nomeArquivo(string $tabela): string
    {
        return $this->nomeClasse($tabela).'.php';
    }

    /**
     * @param  list<Insercao>  $insercoes
     */
    public function montar(string $tabela, array $insercoes): string
    {
        $classe = $this->nomeClasse($tabela);
        $linhas = $this->linhasInsert($tabela, $insercoes);
        $corpo = implode("\n", array_map(
            fn (string $linha) => '            '.$linha,
            $linhas,
        ));

        return <<<PHP
<?php

namespace Database\\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class {$classe} extends Seeder
{
    public function run(): void
    {
        DB::table('{$tabela}')->insert([
{$corpo}
        ]);
    }
}

PHP;
    }

    /**
     * @param  list<Insercao>  $insercoes
     * @return list<string>
     */
    private function linhasInsert(string $tabela, array $insercoes): array
    {
        $linhas = [];

        foreach ($insercoes as $insercao) {
            if ($insercao->tabela !== $tabela) {
                continue;
            }

            foreach ($insercao->linhas as $linha) {
                if ($insercao->colunas === []) {
                    $linhas[] = '// linha sem colunas nomeadas foi ignorada';
                    continue;
                }

                $pares = [];
                foreach ($insercao->colunas as $indice => $coluna) {
                    $valor = $linha[$indice] ?? null;
                    $pares[] = "'{$coluna}' => ".$this->exportar($valor);
                }

                $linhas[] = '['.implode(', ', $pares).'],';
            }
        }

        return $linhas;
    }

    private function exportar(mixed $valor): string
    {
        return var_export($valor, true);
    }
}
