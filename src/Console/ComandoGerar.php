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
use function Laravel\Prompts\select;

class ComandoGerar extends Command
{
    protected $signature = 'sql:gerar
        {arquivo : Caminho do arquivo SQL}
        {--artefatos= : migrations,models,controllers,seeders}
        {--tabelas= : Filtra tabelas (separadas por vírgula)}
        {--tabelas-crud= : Tabelas com controller CRUD e Form Requests}
        {--soft-deletes : Ativa SoftDeletes nas tabelas incluídas}
        {--sem-soft-delete= : Tabelas sem SoftDeletes (com --soft-deletes)}
        {--tabelas-soft-delete= : Tabelas com SoftDeletes (lista explícita)}
        {--tabela-users= : Tabela de autenticação (ex: users ou usuarios)}
        {--dir-migrations= : Diretório de saída das migrations}
        {--dir-models= : Diretório de saída dos models}
        {--dir-controllers= : Diretório de saída dos controllers}
        {--dir-requests= : Diretório de saída dos Form Requests}
        {--dir-seeders= : Diretório de saída dos seeders}
        {--namespace-models= : Namespace dos models}
        {--namespace-controllers= : Namespace dos controllers}
        {--namespace-requests= : Namespace dos Form Requests}
        {--sobrescrever : Sobrescreve arquivos existentes}
        {--dry-run : Mostra o plano sem gravar arquivos}
        {--sem-interacao : Não faz perguntas}';

    protected $description = 'Gera migrations, models, Form Requests e controllers CRUD a partir de um schema SQL';

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
            $this->components->warn('Nenhum arquivo para gerar com as opções atuais.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->components->info('Dry-run: nenhum arquivo foi gravado.');

            return self::SUCCESS;
        }

        if ($plano->totalConflitos() > 0 && ! $opcoes->sobrescrever) {
            if ($this->modoInterativo()) {
                if (! confirm('Existem conflitos. Sobrescrever arquivos existentes?', false)) {
                    $this->components->warn('Operação cancelada.');

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
            $this->components->warn('Operação cancelada.');

            return self::SUCCESS;
        }

        $erros = $validador->validar($plano);

        if ($erros !== []) {
            $this->components->error('Validação falhou:');
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
        $tabelas = $this->resolverTabelas($schema);
        $artefatos = $this->resolverArtefatos($schema, $config);
        $tabelasCrud = $this->resolverTabelasCrud($schema, $tabelas, $artefatos);
        $tabelasSoftDelete = $this->resolverTabelasSoftDelete($schema, $tabelas, $artefatos, $config);

        return new OpcoesGeracao(
            artefatos: $artefatos,
            caminhos: $this->resolverCaminhos($config),
            sobrescrever: (bool) $this->option('sobrescrever') || ($config['sobrescrever'] ?? false),
            namespaceModels: (string) ($this->option('namespace-models')
                ?: ($config['namespace_models'] ?? 'App\\Models')),
            namespaceControllers: (string) ($this->option('namespace-controllers')
                ?: ($config['namespace_controllers'] ?? 'App\\Http\\Controllers')),
            namespaceRequests: (string) ($this->option('namespace-requests')
                ?: ($config['namespace_requests'] ?? 'App\\Http\\Requests')),
            tabelas: $tabelas,
            tabelaUsers: $this->resolverTabelaUsers($schema, $tabelas, $config),
            tabelasCrud: $tabelasCrud,
            tabelasSoftDelete: $tabelasSoftDelete,
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
            return $this->comRequestsDoCrud($this->parseArtefatos($opcao));
        }

        if ($this->modoInterativo()) {
            return $this->perguntarArtefatos($schema, $config);
        }

        return $this->comRequestsDoCrud($this->artefatosDaConfig($schema, $config));
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
            if ($tipo === TipoArtefato::Request) {
                continue;
            }

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
            label: 'O que deseja gerar?',
            options: $opcoes,
            default: $padrao,
            required: true,
            hint: 'Espaço marca, Enter confirma',
        );

        return $this->comRequestsDoCrud(array_values(array_filter(
            array_map(
                fn (string $valor) => TipoArtefato::tryFrom($valor),
                $escolhidos,
            ),
        )));
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<TipoArtefato>
     */
    private function artefatosDaConfig(Schema $schema, array $config): array
    {
        $selecionados = [];

        foreach (TipoArtefato::cases() as $tipo) {
            if ($tipo === TipoArtefato::Request) {
                continue;
            }

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

    /**
     * @param  list<TipoArtefato>  $artefatos
     * @return list<TipoArtefato>
     */
    private function comRequestsDoCrud(array $artefatos): array
    {
        if (
            in_array(TipoArtefato::Controller, $artefatos, true)
            && ! in_array(TipoArtefato::Request, $artefatos, true)
        ) {
            $artefatos[] = TipoArtefato::Request;
        }

        return array_values($artefatos);
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
            hint: 'Define o escopo de migrations, models e seeders',
        );

        if (count($escolhidas) === count($nomes)) {
            return null;
        }

        return array_values($escolhidas);
    }

    /**
     * @param  list<string>|null  $tabelas
     * @param  list<TipoArtefato>  $artefatos
     * @return list<string>|null
     */
    private function resolverTabelasCrud(Schema $schema, ?array $tabelas, array $artefatos): ?array
    {
        $temCrud = in_array(TipoArtefato::Controller, $artefatos, true)
            || in_array(TipoArtefato::Request, $artefatos, true);

        if (! $temCrud) {
            return null;
        }

        $candidatas = $tabelas ?? $schema->nomesTabelas();
        $opcao = $this->option('tabelas-crud');

        if (is_string($opcao) && $opcao !== '') {
            $escolhidas = array_values(array_filter(array_map('trim', explode(',', $opcao))));

            return array_values(array_intersect($escolhidas, $candidatas));
        }

        if ($candidatas === []) {
            return [];
        }

        if (! $this->modoInterativo()) {
            return null;
        }

        if (count($candidatas) === 1) {
            return $candidatas;
        }

        $escolhidas = multiselect(
            label: 'Quais tabelas devem ter Controllers CRUD?',
            options: array_combine($candidatas, $candidatas),
            default: $candidatas,
            required: true,
            hint: 'Cada tabela marcada gera Controller + Store/Update Form Request',
        );

        if (count($escolhidas) === count($candidatas)) {
            return null;
        }

        return array_values($escolhidas);
    }

    /**
     * @param  list<string>|null  $tabelas
     * @param  array<string, mixed>  $config
     */
    private function resolverTabelaUsers(Schema $schema, ?array $tabelas, array $config): ?string
    {
        $opcao = $this->option('tabela-users');

        if (is_string($opcao) && $opcao !== '') {
            return $opcao === 'nenhuma' ? null : $opcao;
        }

        if (array_key_exists('tabela_users', $config) && is_string($config['tabela_users']) && $config['tabela_users'] !== '') {
            return $config['tabela_users'];
        }

        $candidatas = $tabelas ?? $schema->nomesTabelas();
        $sugestao = $this->sugerirTabelaUsers($candidatas);

        if (! $this->modoInterativo()) {
            return $sugestao;
        }

        $opcoes = ['nenhuma' => 'Nenhuma (não gerar model User de autenticação)'];

        foreach ($candidatas as $nome) {
            $opcoes[$nome] = $nome;
        }

        $padrao = ($sugestao !== null && isset($opcoes[$sugestao])) ? $sugestao : 'nenhuma';

        $escolhida = select(
            label: 'Qual tabela é a de usuários/autenticação do Laravel?',
            options: $opcoes,
            default: $padrao,
            hint: 'Essa tabela vira App\\Models\\User (Authenticatable) e sobrescreve o User padrão',
        );

        return $escolhida === 'nenhuma' ? null : $escolhida;
    }

    /**
     * @param  list<string>|null  $tabelas
     * @param  list<TipoArtefato>  $artefatos
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function resolverTabelasSoftDelete(
        Schema $schema,
        ?array $tabelas,
        array $artefatos,
        array $config,
    ): array {
        $relevante = in_array(TipoArtefato::Migration, $artefatos, true)
            || in_array(TipoArtefato::Model, $artefatos, true);

        if (! $relevante) {
            return [];
        }

        $candidatas = $tabelas ?? $schema->nomesTabelas();

        $opcaoLista = $this->option('tabelas-soft-delete');

        if (is_string($opcaoLista) && $opcaoLista !== '') {
            $escolhidas = array_values(array_filter(array_map('trim', explode(',', $opcaoLista))));

            return array_values(array_intersect($escolhidas, $candidatas));
        }

        $padrao = (bool) $this->option('soft-deletes') || ($config['soft_deletes'] ?? false) === true;
        $ativar = $this->modoInterativo()
            ? confirm('Usar SoftDeletes?', $padrao)
            : $padrao;

        if (! $ativar) {
            return [];
        }

        $excluidas = [];
        $opcaoExcluir = $this->option('sem-soft-delete');

        if (is_string($opcaoExcluir) && $opcaoExcluir !== '') {
            $excluidas = array_values(array_filter(array_map('trim', explode(',', $opcaoExcluir))));
        } elseif ($this->modoInterativo() && count($candidatas) > 1) {
            $excluidas = multiselect(
                label: 'Quais tabelas NÃO devem ter SoftDeletes?',
                options: array_combine($candidatas, $candidatas),
                default: [],
                required: false,
                hint: 'Deixe vazio para aplicar em todas. Espaço marca, Enter confirma',
            );
        }

        return array_values(array_diff($candidatas, $excluidas));
    }

    /** @param  list<string>  $candidatas */
    private function sugerirTabelaUsers(array $candidatas): ?string
    {
        foreach (['users', 'usuarios', 'user'] as $candidato) {
            if (in_array($candidato, $candidatas, true)) {
                return $candidato;
            }
        }

        return null;
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
            'requests' => 'dir-requests',
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
            namespaceRequests: $opcoes->namespaceRequests,
            tabelas: $opcoes->tabelas,
            tabelaUsers: $opcoes->tabelaUsers,
            tabelasCrud: $opcoes->tabelasCrud,
            tabelasSoftDelete: $opcoes->tabelasSoftDelete,
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
        $this->components->info("Plano de geração ({$plano->total()} arquivo(s))");

        $artefatos = array_map(
            fn (TipoArtefato $tipo) => $tipo->rotulo(),
            $opcoes->artefatos,
        );
        $this->line('  Artefatos: <fg=cyan>'.implode(', ', $artefatos).'</>');

        if ($opcoes->tabelas !== null) {
            $this->line('  Tabelas: <fg=cyan>'.implode(', ', $opcoes->tabelas).'</>');
        }

        if ($opcoes->inclui(TipoArtefato::Controller)) {
            $crud = $opcoes->tabelasCrud === null
                ? 'todas as tabelas incluídas'
                : ($opcoes->tabelasCrud === []
                    ? 'nenhuma'
                    : implode(', ', $opcoes->tabelasCrud));
            $this->line("  CRUD: <fg=cyan>{$crud}</>");
        }

        if ($opcoes->tabelasSoftDelete !== []) {
            $this->line('  SoftDeletes: <fg=cyan>'.implode(', ', $opcoes->tabelasSoftDelete).'</>');
        }

        if ($opcoes->tabelaUsers !== null) {
            $this->line("  Tabela users: <fg=cyan>{$opcoes->tabelaUsers}</> → <fg=cyan>User</>");
        }

        $this->newLine();

        foreach ($plano->porTipo() as $tipo => $arquivos) {
            $rotulo = TipoArtefato::from($tipo)->rotulo();
            $this->line("  <fg=yellow;options=bold>{$rotulo}</> <fg=gray>(".count($arquivos).')</>');

            foreach ($arquivos as $arquivo) {
                if ($arquivo->conflito) {
                    $this->line("    <fg=red>conflito</>   {$arquivo->caminho}");
                } elseif (is_file($arquivo->caminho)) {
                    $this->line("    <fg=cyan>substituir</> {$arquivo->caminho}");
                } else {
                    $this->line("    <fg=green>novo</>       {$arquivo->caminho}");
                }
            }

            $this->newLine();
        }

        if ($plano->totalConflitos() > 0) {
            $this->components->warn("{$plano->totalConflitos()} arquivo(s) já existem e serão ignorados sem --sobrescrever.");
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
        $this->components->info('Geração concluída');
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
