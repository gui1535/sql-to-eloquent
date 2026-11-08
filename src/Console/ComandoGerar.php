<?php

namespace SqlToEloquent\Console;

use Illuminate\Console\Command;
use SqlToEloquent\Analise\AnalisadorMysql;
use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Escrita\EscritorArquivos;
use SqlToEloquent\Planejamento\OpcoesGeracao;
use SqlToEloquent\Planejamento\Planejador;
use SqlToEloquent\Planejamento\PlanoGeracao;
use SqlToEloquent\Validacao\Validador;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;

class ComandoGerar extends Command
{
    protected $signature = 'sql:gerar
        {arquivo : Caminho do arquivo SQL}
        {--artefatos= : migrations,models,controllers,seeders}
        {--tabelas= : Filtra tabelas (separadas por virgula)}
        {--dir-migrations= : Diretorio de saida das migrations}
        {--dir-models= : Diretorio de saida dos models}
        {--dir-controllers= : Diretorio de saida dos controllers}
        {--dir-seeders= : Diretorio de saida dos seeders}
        {--namespace-models= : Namespace dos models}
        {--namespace-controllers= : Namespace dos controllers}
        {--sobrescrever : Sobrescreve arquivos existentes}
        {--dry-run : Mostra o plano sem gravar arquivos}
        {--sem-interacao : Nao faz perguntas}';

    protected $description = 'Gera migrations, models e controllers CRUD a partir de um schema SQL';

    public function handle(
        AnalisadorMysql $analisador,
        Planejador $planejador,
        Validador $validador,
        EscritorArquivos $escritor,
    ): int {
        $caminho = (string) $this->argument('arquivo');

        $this->components->info('SQL to Eloquent');
        $this->line("  Arquivo: <fg=cyan>{$caminho}</>");

        try {
            $schema = $analisador->analisarArquivo($caminho);
        } catch (\Throwable $erro) {
            $this->components->error($erro->getMessage());

            return self::FAILURE;
        }

        if ($schema->tabelas === []) {
            $this->components->warn('Nenhuma tabela encontrada no SQL.');
            $this->exibirAvisos($schema->avisos);

            return self::FAILURE;
        }

        $this->exibirResumoSchema($schema);

        $opcoes = $this->montarOpcoes($schema);
        $plano = $planejador->montar($schema, $opcoes);

        $this->exibirPrevia($plano, $opcoes);

        if ($plano->total() === 0) {
            $this->components->warn('Nenhum arquivo para gerar com as opcoes atuais.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->components->info('Dry-run: nenhum arquivo foi gravado.');

            return self::SUCCESS;
        }

        if ($plano->totalConflitos() > 0 && ! $opcoes->sobrescrever) {
            if ($this->modoInterativo()) {
                if (! confirm('Existem conflitos. Sobrescrever arquivos existentes?', false)) {
                    $this->components->warn('Operacao cancelada.');

                    return self::SUCCESS;
                }

                $opcoes = $this->comSobrescrita($opcoes);
                $plano = $planejador->montar($schema, $opcoes);
            } else {
                $this->components->error(
                    "Existem {$plano->totalConflitos()} conflito(s). Use --sobrescrever ou remova os arquivos."
                );

                return self::FAILURE;
            }
        }

        if ($this->modoInterativo() && ! confirm('Gravar '.$plano->total().' arquivo(s)?', true)) {
            $this->components->warn('Operacao cancelada.');

            return self::SUCCESS;
        }

        $erros = $validador->validar($plano);

        if ($erros !== []) {
            $this->components->error('Validacao falhou:');
            foreach ($erros as $erro) {
                $this->line("  <fg=red>-</> {$erro}");
            }

            return self::FAILURE;
        }

        $resultado = $escritor->gravar($plano, $opcoes->sobrescrever);
        $this->exibirResumoFinal($resultado, $plano->avisos);

        return self::SUCCESS;
    }

    private function montarOpcoes(Schema $schema): OpcoesGeracao
    {
        $config = config('sql-to-eloquent', []);

        return new OpcoesGeracao(
            artefatos: $this->resolverArtefatos($schema, $config),
            caminhos: $this->resolverCaminhos($config),
            sobrescrever: (bool) $this->option('sobrescrever') || ($config['sobrescrever'] ?? false),
            namespaceModels: (string) ($this->option('namespace-models')
                ?: ($config['namespace_models'] ?? 'App\\Models')),
            namespaceControllers: (string) ($this->option('namespace-controllers')
                ?: ($config['namespace_controllers'] ?? 'App\\Http\\Controllers')),
            tabelas: $this->resolverTabelas($schema),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<TipoArtefato>
     */
    private function resolverArtefatos(Schema $schema, array $config): array
    {
        $opcao = $this->option('artefatos');

        if (is_string($opcao) && $opcao !== '') {
            return $this->parseArtefatos($opcao);
        }

        if ($this->modoInterativo()) {
            return $this->perguntarArtefatos($schema, $config);
        }

        return $this->artefatosDaConfig($schema, $config);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<TipoArtefato>
     */
    private function perguntarArtefatos(Schema $schema, array $config): array
    {
        $opcoes = [];
        $padrao = [];

        foreach (TipoArtefato::cases() as $tipo) {
            if ($tipo === TipoArtefato::Seeder && ! $schema->temDados()) {
                continue;
            }

            $opcoes[$tipo->value] = $tipo->rotulo();

            if (($config['artefatos'][$tipo->value] ?? false) === true) {
                $padrao[] = $tipo->value;
            }
        }

        if ($opcoes === []) {
            return [];
        }

        $escolhidos = multiselect(
            label: 'Quais artefatos deseja gerar?',
            options: $opcoes,
            default: $padrao,
            required: true,
            hint: 'Espaco marca, Enter confirma',
        );

        return array_values(array_filter(
            array_map(
                fn (string $valor) => TipoArtefato::tryFrom($valor),
                $escolhidos,
            ),
        ));
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<TipoArtefato>
     */
    private function artefatosDaConfig(Schema $schema, array $config): array
    {
        $selecionados = [];

        foreach (TipoArtefato::cases() as $tipo) {
            if (($config['artefatos'][$tipo->value] ?? false) !== true) {
                continue;
            }

            if ($tipo === TipoArtefato::Seeder && ! $schema->temDados()) {
                continue;
            }

            $selecionados[] = $tipo;
        }

        return $selecionados;
    }

    /** @return list<TipoArtefato> */
    private function parseArtefatos(string $opcao): array
    {
        $nomes = array_map('trim', explode(',', $opcao));

        return array_values(array_filter(
            array_map(
                fn (string $nome) => TipoArtefato::tryFrom($nome),
                $nomes,
            ),
        ));
    }

    /** @return list<string>|null */
    private function resolverTabelas(Schema $schema): ?array
    {
        $opcao = $this->option('tabelas');

        if (is_string($opcao) && $opcao !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $opcao))));
        }

        if (! $this->modoInterativo() || count($schema->tabelas) <= 1) {
            return null;
        }

        $nomes = $schema->nomesTabelas();
        $escolhidas = multiselect(
            label: 'Quais tabelas deseja incluir?',
            options: array_combine($nomes, $nomes),
            default: $nomes,
            required: true,
            hint: 'Deixe todas marcadas para gerar o schema completo',
        );

        if (count($escolhidas) === count($nomes)) {
            return null;
        }

        return array_values($escolhidas);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    private function resolverCaminhos(array $config): array
    {
        $caminhos = $config['caminhos'] ?? [];

        $mapa = [
            'migrations' => 'dir-migrations',
            'models' => 'dir-models',
            'controllers' => 'dir-controllers',
            'seeders' => 'dir-seeders',
        ];

        foreach ($mapa as $chave => $opcao) {
            $valor = $this->option($opcao);

            if (is_string($valor) && $valor !== '') {
                $caminhos[$chave] = $valor;
            }
        }

        return $caminhos;
    }

    private function comSobrescrita(OpcoesGeracao $opcoes): OpcoesGeracao
    {
        return new OpcoesGeracao(
            artefatos: $opcoes->artefatos,
            caminhos: $opcoes->caminhos,
            sobrescrever: true,
            namespaceModels: $opcoes->namespaceModels,
            namespaceControllers: $opcoes->namespaceControllers,
            tabelas: $opcoes->tabelas,
        );
    }

    private function modoInterativo(): bool
    {
        if ($this->option('sem-interacao') || $this->option('no-interaction')) {
            return false;
        }

        return $this->input->isInteractive();
    }

    private function exibirResumoSchema(Schema $schema): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('Tabelas', (string) count($schema->tabelas));
        $this->components->twoColumnDetail('Colunas', (string) $schema->totalColunas());
        $this->components->twoColumnDetail('Foreign keys', (string) $schema->totalForeignKeys());
        $this->components->twoColumnDetail('Inserts', (string) $schema->totalLinhasInsert());

        $this->newLine();
        $this->line('  <fg=gray>Tabelas encontradas:</>');

        foreach ($schema->tabelas as $tabela) {
            $fks = count($tabela->chavesEstrangeiras);
            $extra = $fks > 0 ? " <fg=gray>({$fks} fk)</>" : '';
            $this->line("  <fg=green>•</> {$tabela->nome} <fg=gray>".count($tabela->colunas).' colunas</>'.$extra);
        }

        $this->exibirAvisos($schema->avisos);
    }

    private function exibirPrevia(PlanoGeracao $plano, OpcoesGeracao $opcoes): void
    {
        $this->newLine();
        $this->components->info("Plano de geracao ({$plano->total()} arquivo(s))");

        $artefatos = array_map(
            fn (TipoArtefato $tipo) => $tipo->rotulo(),
            $opcoes->artefatos,
        );
        $this->line('  Artefatos: <fg=cyan>'.implode(', ', $artefatos).'</>');

        if ($opcoes->tabelas !== null) {
            $this->line('  Tabelas: <fg=cyan>'.implode(', ', $opcoes->tabelas).'</>');
        }

        $this->newLine();

        foreach ($plano->porTipo() as $tipo => $arquivos) {
            $rotulo = TipoArtefato::from($tipo)->rotulo();
            $this->line("  <fg=yellow;options=bold>{$rotulo}</> <fg=gray>(".count($arquivos).')</>');

            foreach ($arquivos as $arquivo) {
                if ($arquivo->conflito) {
                    $this->line("    <fg=red>conflito</> {$arquivo->caminho}");
                } else {
                    $this->line("    <fg=green>novo</>     {$arquivo->caminho}");
                }
            }

            $this->newLine();
        }

        if ($plano->totalConflitos() > 0) {
            $this->components->warn("{$plano->totalConflitos()} arquivo(s) ja existem e serao ignorados sem --sobrescrever.");
        }

        $this->exibirAvisos($plano->avisos);
    }

    /**
     * @param  array{criados: list<string>, ignorados: list<string>}  $resultado
     * @param  list<string>  $avisos
     */
    private function exibirResumoFinal(array $resultado, array $avisos): void
    {
        $this->newLine();
        $this->components->info('Geracao concluida');
        $this->components->twoColumnDetail('Criados', (string) count($resultado['criados']));
        $this->components->twoColumnDetail('Ignorados', (string) count($resultado['ignorados']));

        if ($resultado['criados'] !== []) {
            $this->newLine();
            foreach ($resultado['criados'] as $caminho) {
                $this->line("  <fg=green>+</> {$caminho}");
            }
        }

        if ($resultado['ignorados'] !== []) {
            $this->newLine();
            foreach ($resultado['ignorados'] as $caminho) {
                $this->line("  <fg=yellow>·</> {$caminho}");
            }
        }

        $this->exibirAvisos($avisos);
    }

    /** @param list<string> $avisos */
    private function exibirAvisos(array $avisos): void
    {
        if ($avisos === []) {
            return;
        }

        $this->newLine();
        $this->line('  <fg=yellow;options=bold>Avisos</>');

        foreach ($avisos as $aviso) {
            $this->line("  <fg=yellow>!</> {$aviso}");
        }
    }
}
