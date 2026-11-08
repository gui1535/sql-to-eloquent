<?php

namespace SqlToEloquent\Dominio;

enum TipoArtefato: string
{
    case Migration = 'migrations';
    case Model = 'models';
    case Controller = 'controllers';
    case Seeder = 'seeders';
}
