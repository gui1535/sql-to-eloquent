<?php

namespace SqlToEloquent\Dominio;

enum TipoArtefato: string
{
    case Migration = 'migrations';
    case Model = 'models';
    case Controller = 'controllers';
    case Request = 'requests';
    case Seeder = 'seeders';
    case Rota = 'rotas';
    case Auth = 'auth';

    public function rotulo(): string
    {
        return match ($this) {
            self::Migration => 'Migrations',
            self::Model => 'Models',
            self::Controller => 'Controllers CRUD',
            self::Request => 'Form Requests',
            self::Seeder => 'Seeders',
            self::Rota => 'Rotas API',
            self::Auth => 'Autenticação API',
        };
    }
}
