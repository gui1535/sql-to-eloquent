<?php

namespace SqlToEloquent;

use Illuminate\Support\ServiceProvider;
use SqlToEloquent\Analise\AnalisadorMysql;
use SqlToEloquent\Console\ComandoGerar;
use SqlToEloquent\Escrita\EscritorArquivos;
use SqlToEloquent\Geracao\Orquestrador;
use SqlToEloquent\Planejamento\Planejador;
use SqlToEloquent\Validacao\Validador;

class ProvedorServico extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sql-to-eloquent.php', 'sql-to-eloquent');

        $this->app->singleton(AnalisadorMysql::class);
        $this->app->singleton(Planejador::class);
        $this->app->singleton(Orquestrador::class);
        $this->app->singleton(EscritorArquivos::class);
        $this->app->singleton(Validador::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ComandoGerar::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/sql-to-eloquent.php' => config_path('sql-to-eloquent.php'),
            ], 'sql-to-eloquent-config');
        }
    }
}
