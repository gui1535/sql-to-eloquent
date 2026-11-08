<?php

namespace SqlToEloquent\Geracao\Suporte;

use SqlToEloquent\Dominio\Insercao;

class ConstrutorSeeder
{
    public function __construct(
        private readonly NomeadorModel $nomeador = new NomeadorModel,
    ) {
    }

    public function nomeClasse(string $tabela, ?string $tabelaUsers = null): string
    {
        return $this->nomeador->classe($tabela, $tabelaUsers).'Seeder';
    }

    public function nomeArquivo(string $tabela, ?string $tabelaUsers = null): string
    {
        return $this->nomeClasse($tabela, $tabelaUsers).'.php';
    }

    /**
     * @param  list<Insercao>  $insercoes
     */
    public function montar(string $tabela, array $insercoes, ?string $tabelaUsers = null): string
    {
        $classe = $this->nomeClasse($tabela, $tabelaUsers);
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
     * @param  list<string>  $classes
     */
    public function montarDatabaseSeeder(array $classes): string
    {
        $linhas = array_map(
            fn (string $classe) => "            {$classe}::class,",
            $classes,
        );
        $corpo = implode("\n", $linhas);

        return <<<PHP
<?php

namespace Database\\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        \$this->call([
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
