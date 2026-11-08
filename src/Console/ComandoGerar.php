<?php

namespace SqlToEloquent\Console;

use Illuminate\Console\Command;
use SqlToEloquent\Analise\AnalisadorMysql;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Escrita\EscritorArquivos;
use SqlToEloquent\Planejamento\OpcoesGeracao;
use SqlToEloquent\Planejamento\Planejador;
use SqlToEloquent\Validacao\Validador;

class ComandoGerar extends Command
{
    protected $signature = 'sql:gerar
        {arquivo : Caminho do arquivo SQL}
        {--artefatos= : Lista separada por virgula (migrations,models,factories,...)}
        {--sobrescrever : Permite sobrescrever arquivos existentes}
        {--sem-interacao : Executa sem perguntas interativas}';

    protected $description = 'Gera artefatos Laravel a partir de um schema SQL';

    public function handle(
        AnalisadorMysql $analisador,
        Planejador $planejador,
        Validador $validador,
        EscritorArquivos $escritor,
    ): int {
        $caminho = $this->argument('arquivo');

        try {
            $schema = $analisador->analisarArquivo($caminho);
        } catch (\Throwable $erro) {
            $this->error($erro->getMessage());

            return self::FAILURE;
        }

        $opcoes = $this->montarOpcoes();
        $plano = $planejador->montar($schema, $opcoes);

        $this->exibirPrevia($plano);

        if ($plano->total() === 0) {
            $this->warn('Nenhum arquivo para gerar.');

            return self::SUCCESS;
        }

        if (! $this->option('sem-interacao') && ! $this->confirm('Deseja gravar os arquivos?', true)) {
            $this->info('Operacao cancelada.');

            return self::SUCCESS;
        }

        $erros = $validador->validar($plano);

        if ($erros !== []) {
            foreach ($erros as $erro) {
                $this->error($erro);
            }

            return self::FAILURE;
        }

        $resultado = $escritor->gravar($plano, (bool) $this->option('sobrescrever'));

        $this->newLine();
        $this->info('Criados: '.count($resultado['criados']));
        $this->info('Ignorados: '.count($resultado['ignorados']));

        foreach ($plano->avisos as $aviso) {
            $this->warn($aviso);
        }

        return self::SUCCESS;
    }

    private function montarOpcoes(): OpcoesGeracao
    {
        $artefatos = $this->resolverArtefatos();
        $config = config('sql-to-eloquent');

        return new OpcoesGeracao(
            artefatos: $artefatos,
            caminhos: $config['caminhos'] ?? [],
            sobrescrever: (bool) $this->option('sobrescrever') || ($config['sobrescrever'] ?? false),
            namespaceModels: $config['namespace_models'] ?? 'App\\Models',
            namespaceControllers: $config['namespace_controllers'] ?? 'App\\Http\\Controllers',
            namespaceRequests: $config['namespace_requests'] ?? 'App\\Http\\Requests',
            namespaceResources: $config['namespace_resources'] ?? 'App\\Http\\Resources',
        );
    }

    /**
     * @return list<TipoArtefato>
     */
    private function resolverArtefatos(): array
    {
        $opcao = $this->option('artefatos');

        if (is_string($opcao) && $opcao !== '') {
            $nomes = array_map('trim', explode(',', $opcao));

            return array_values(array_filter(
                array_map(
                    fn (string $nome) => TipoArtefato::tryFrom($nome),
                    $nomes,
                ),
            ));
        }

        $config = config('sql-to-eloquent.artefatos', []);
        $selecionados = [];

        foreach (TipoArtefato::cases() as $tipo) {
            if (($config[$tipo->value] ?? false) === true) {
                $selecionados[] = $tipo;
            }
        }

        return $selecionados;
    }

    private function exibirPrevia($plano): void
    {
        $this->newLine();
        $this->info("Plano: {$plano->total()} arquivo(s)");

        foreach ($plano->arquivos as $arquivo) {
            $marca = $arquivo->conflito ? '[conflito]' : '[novo]';
            $this->line("  {$marca} {$arquivo->caminho}");
        }

        foreach ($plano->avisos as $aviso) {
            $this->warn("  aviso: {$aviso}");
        }
    }
}
