<?php

return [
    'caminhos' => [
        'migrations' => database_path('migrations'),
        'models' => app_path('Models'),
        'controllers' => app_path('Http/Controllers'),
        'requests' => app_path('Http/Requests'),
        'seeders' => database_path('seeders'),
        'rotas' => base_path('routes'),
    ],

    'artefatos' => [
        'migrations' => true,
        'models' => true,
        'controllers' => true,
        'requests' => true,
        'seeders' => true,
        'rotas' => false,
        'auth' => false,
    ],

    'sobrescrever' => false,

    'soft_deletes' => false,

    'namespace_models' => 'App\\Models',
    'namespace_controllers' => 'App\\Http\\Controllers',
    'namespace_requests' => 'App\\Http\\Requests',

    'tabela_users' => null,
    'campo_email' => null,
    'campo_senha' => null,
];
