<?php

namespace SqlToEloquent\Console;

use Illuminate\Console\Command;
use SqlToEloquent\Analise\AnalisadorMysql;
use SqlToEloquent\Dominio\Schema;
use SqlToEloquent\Dominio\Tabela;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Escrita\EscritorArquivos;
use SqlToEloquent\Geracao\Suporte\DetectorPivot;
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
        {--artefatos= : migrations,models,controllers,seeders,rotas,auth}
        {--tabelas= : Filtra tabelas (separadas por vírgula)}
        {--tabelas-crud= : Tabelas com controller CRUD e Form Requests}
        {--soft-deletes : Ativa SoftDeletes nas tabelas incluídas}
        {--sem-soft-delete= : Tabelas sem SoftDeletes (com --soft-deletes)}
        {--tabelas-soft-delete= : Tabelas com SoftDeletes (lista explícita)}
        {--tabela-users= : Tabela de autenticação (ex: users ou usuarios)}
        {--campo-email= : Coluna de e-mail da tabela de auth}
        {--campo-senha= : Coluna de senha da tabela de auth}
        {--auth : Gera autenticação API (login/register/logout com Sanctum)}
        {--sem-install-api : Não executa php artisan install:api}
        {--dir-migrations= : Diretório de saída das migrations}
        {--dir-models= : Diretório de saída dos models}
        {--dir-controllers= : Diretório de saída dos controllers}
        {--dir-requests= : Diretório de saída dos Form Requests}
        {--dir-seeders= : Diretório de saída dos seeders}
        {--dir-rotas= : Diretório de saída das rotas}
        {--namespace-models= : Namespace dos models}
        {--namespace-controllers= : Namespace dos controllers}
        {--namespace-requests= : Namespace dos Form Requests}
        {--sobrescrever : Sobrescreve arquivos existentes}
        {--dry-run : Mostra o plano sem gravar arquivos}
        {--sem-interacao : Não faz perguntas}';

    protected $description = 'Gera migrations, models, Form Requests, controllers CRUD e rotas a partir de um schema SQL';

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

        if (! $this->garantirApiLaravel($opcoes)) {
            return self::FAILURE;
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
        $this->registrarRotasNoApi($plano);
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
        $tabelaUsers = $this->resolverTabelaUsers($schema, $tabelas, $config);
        [$campoEmail, $campoSenha] = $this->resolverCamposAuth($schema, $tabelaUsers, $config);
        $artefatos = $this->resolverAuthERotas($schema, $artefatos, $tabelaUsers, $config);

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
            tabelaUsers: $tabelaUsers,
            tabelasCrud: $tabelasCrud,
            tabelasSoftDelete: $tabelasSoftDelete,
            campoEmail: $campoEmail,
            campoSenha: $campoSenha,
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
            return $this->normalizarArtefatos($this->parseArtefatos($opcao));
        }

        if ($this->modoInterativo()) {
            return $this->perguntarArtefatos($schema, $config);
        }

        return $this->normalizarArtefatos($this->artefatosDaConfig($schema, $config));
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
            if (in_array($tipo, [TipoArtefato::Request, TipoArtefato::Auth], true)) {
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
            hint: 'Espaço marca, Enter confirma. Controllers CRUD incluem Form Requests. Rotas API são opcionais',
        );

        return $this->normalizarArtefatos(array_values(array_filter(
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
            if (in_array($tipo, [TipoArtefato::Request, TipoArtefato::Auth], true)) {
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
    private function normalizarArtefatos(array $artefatos): array
    {
        if (
            in_array(TipoArtefato::Controller, $artefatos, true)
            && ! in_array(TipoArtefato::Request, $artefatos, true)
        ) {
            $artefatos[] = TipoArtefato::Request;
        }

        if (
            in_array(TipoArtefato::Auth, $artefatos, true)
            && ! in_array(TipoArtefato::Rota, $artefatos, true)
        ) {
            $artefatos[] = TipoArtefato::Rota;
        }

        return array_values($artefatos);
    }

    /**
     * @param  list<TipoArtefato>  $artefatos
     * @param  array<string, mixed>  $config
     * @return list<TipoArtefato>
     */
    private function resolverAuthERotas(
        Schema $schema,
        array $artefatos,
        ?string $tabelaUsers,
        array $config,
    ): array {
        if ($tabelaUsers !== null) {
            $querAuth = (bool) $this->option('auth')
                || ($config['artefatos']['auth'] ?? false) === true
                || in_array(TipoArtefato::Auth, $artefatos, true);

            if ($this->modoInterativo()) {
                $querAuth = confirm(
                    'Gerar autenticação API padrão do Laravel (login/register/logout com Sanctum)?',
                    $querAuth,
                );
            }

            if ($querAuth && ! in_array(TipoArtefato::Auth, $artefatos, true)) {
                $artefatos[] = TipoArtefato::Auth;
            }
        }

        if (
            $this->modoInterativo()
            && in_array(TipoArtefato::Controller, $artefatos, true)
            && ! in_array(TipoArtefato::Rota, $artefatos, true)
            && ! in_array(TipoArtefato::Auth, $artefatos, true)
        ) {
            if (confirm('Gerar rotas API (apiResource)?', true)) {
                $artefatos[] = TipoArtefato::Rota;
            }
        }

        return $this->normalizarArtefatos($artefatos);
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

        $detectorPivot = new DetectorPivot;
        $candidatas = array_values(array_filter(
            $tabelas ?? $schema->nomesTabelas(),
            function (string $nome) use ($schema, $detectorPivot): bool {
                $tabela = $this->tabelaPorNome($schema, $nome);

                return $tabela === null || ! $detectorPivot->ehPivot($tabela);
            },
        ));
        $opcao = $this->option('tabelas-crud');

        if (is_string($opcao) && $opcao !== '') {
            $escolhidas = array_values(array_filter(array_map('trim', explode(',', $opcao))));

            return array_values(array_intersect($escolhidas, $candidatas));
        }

        if ($candidatas === []) {
            return [];
        }

        if (! $this->modoInterativo()) {
            return $candidatas;
        }

        if (count($candidatas) === 1) {
            return $candidatas;
        }

        $escolhidas = multiselect(
            label: 'Quais tabelas devem ter Controllers CRUD?',
            options: array_combine($candidatas, $candidatas),
            default: $candidatas,
            required: true,
            hint: 'Pivots ficam de fora. Cada tabela gera Controller + Requests + rota',
        );

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
     * @param  array<string, mixed>  $config
     * @return array{0: ?string, 1: ?string}
     */
    private function resolverCamposAuth(Schema $schema, ?string $tabelaUsers, array $config): array
    {
        if ($tabelaUsers === null) {
            return [null, null];
        }

        $tabela = $this->tabelaPorNome($schema, $tabelaUsers);

        if ($tabela === null) {
            return [null, null];
        }

        $colunas = array_map(fn ($coluna) => $coluna->nome, $tabela->colunas);
        $opcoes = array_combine($colunas, $colunas) ?: [];

        $emailOpcao = $this->option('campo-email');
        $senhaOpcao = $this->option('campo-senha');

        $emailPadrao = is_string($emailOpcao) && $emailOpcao !== ''
            ? $emailOpcao
            : (is_string($config['campo_email'] ?? null) && $config['campo_email'] !== ''
                ? $config['campo_email']
                : $this->sugerirCampo($colunas, ['email', 'e_mail', 'login', 'username']));

        $senhaPadrao = is_string($senhaOpcao) && $senhaOpcao !== ''
            ? $senhaOpcao
            : (is_string($config['campo_senha'] ?? null) && $config['campo_senha'] !== ''
                ? $config['campo_senha']
                : $this->sugerirCampo($colunas, ['senha', 'password', 'hash', 'password_hash']));

        if (! $this->modoInterativo()) {
            return [$emailPadrao, $senhaPadrao];
        }

        $email = select(
            label: 'Qual coluna é o e-mail de autenticação?',
            options: $opcoes,
            default: ($emailPadrao !== null && isset($opcoes[$emailPadrao])) ? $emailPadrao : $colunas[0],
        );

        $senha = select(
            label: 'Qual coluna é a senha de autenticação?',
            options: $opcoes,
            default: ($senhaPadrao !== null && isset($opcoes[$senhaPadrao])) ? $senhaPadrao : $colunas[0],
            hint: 'Se não for password, o model gera getAuthPasswordName()',
        );

        return [$email, $senha];
    }

    /**
     * @param  list<string>  $colunas
     * @param  list<string>  $candidatos
     */
    private function sugerirCampo(array $colunas, array $candidatos): ?string
    {
        foreach ($candidatos as $candidato) {
            if (in_array($candidato, $colunas, true)) {
                return $candidato;
            }
        }

        foreach ($colunas as $coluna) {
            foreach ($candidatos as $candidato) {
                if (str_contains($coluna, $candidato)) {
                    return $coluna;
                }
            }
        }

        return null;
    }

    private function tabelaPorNome(Schema $schema, string $nome): ?Tabela
    {
        foreach ($schema->tabelas as $tabela) {
            if ($tabela->nome === $nome) {
                return $tabela;
            }
        }

        return null;
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
            'rotas' => 'dir-rotas',
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
            campoEmail: $opcoes->campoEmail,
            campoSenha: $opcoes->campoSenha,
        );
    }

    private function modoInterativo(): bool
    {
        if ($this->option('sem-interacao') || $this->option('no-interaction')) {
            return false;
        }

        return $this->input->isInteractive();
    }

    private function garantirApiLaravel(OpcoesGeracao $opcoes): bool
    {
        if (! $opcoes->authApi() && ! $opcoes->inclui(TipoArtefato::Rota)) {
            return true;
        }

        if ($this->option('sem-install-api')) {
            return true;
        }

        if ($this->apiLaravelPronta($opcoes->authApi())) {
            $this->components->info('API Laravel já está pronta.');

            return true;
        }

        if ($this->modoInterativo()) {
            $rotulo = $opcoes->authApi()
                ? 'Instalar API Laravel agora? (php artisan install:api - Sanctum)'
                : 'Instalar scaffolding de API Laravel agora? (php artisan install:api)';

            if (! confirm($rotulo, true)) {
                $this->components->warn('Pulou install:api. Rode depois: php artisan install:api --without-migration-prompt');

                return true;
            }
        }

        $this->components->info('Executando php artisan install:api...');

        try {
            $codigo = $this->call('install:api', [
                '--without-migration-prompt' => true,
            ]);
        } catch (\Throwable $erro) {
            $this->components->error('Falha ao executar install:api: '.$erro->getMessage());

            return false;
        }

        if ($codigo !== self::SUCCESS) {
            $this->components->error('install:api retornou código '.$codigo.'.');

            return false;
        }

        $this->components->info('API Laravel instalada.');

        return true;
    }

    private function apiLaravelPronta(bool $precisaSanctum): bool
    {
        $temApi = is_file(base_path('routes/api.php'));
        $temSanctum = class_exists(\Laravel\Sanctum\Sanctum::class)
            || is_dir(base_path('vendor/laravel/sanctum'));

        if ($precisaSanctum) {
            return $temApi && $temSanctum;
        }

        return $temApi;
    }

    private function registrarRotasNoApi(PlanoGeracao $plano): void
    {
        $arquivoRotas = null;

        foreach ($plano->arquivos as $arquivo) {
            if ($arquivo->tipo === TipoArtefato::Rota) {
                $arquivoRotas = $arquivo->caminho;
                break;
            }
        }

        if ($arquivoRotas === null) {
            return;
        }

        $api = base_path('routes/api.php');

        if (! is_file($api)) {
            $this->components->warn('routes/api.php não encontrado. Inclua manualmente: require base_path(\'routes/sql-to-eloquent.php\');');

            return;
        }

        $conteudo = (string) file_get_contents($api);

        if (str_contains($conteudo, 'sql-to-eloquent.php')) {
            return;
        }

        $require = "require base_path('routes/sql-to-eloquent.php');";
        file_put_contents($api, rtrim($conteudo).PHP_EOL.PHP_EOL.$require.PHP_EOL);
        $this->components->info('Rotas registradas em routes/api.php.');
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

            if ($opcoes->campoEmail !== null) {
                $this->line("  E-mail auth: <fg=cyan>{$opcoes->campoEmail}</>");
            }

            if ($opcoes->campoSenha !== null) {
                $this->line("  Senha auth: <fg=cyan>{$opcoes->campoSenha}</>");
            }
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
