<?php

namespace SqlToEloquent\Dominio;

enum TipoArtefato: string
{
    case Migration = 'migrations';
    case Model = 'models';
    case Factory = 'factories';
    case Seeder = 'seeders';
    case Controller = 'controllers';
    case Request = 'requests';
    case Resource = 'resources';
    case Rotas = 'rotas';
    case Teste = 'testes';
}
