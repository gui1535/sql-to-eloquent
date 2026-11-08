<?php

return [
    'caminhos' => [
        'migrations' => database_path('migrations'),
        'models' => app_path('Models'),
        'factories' => database_path('factories'),
        'seeders' => database_path('seeders'),
        'controllers' => app_path('Http/Controllers'),
        'requests' => app_path('Http/Requests'),
        'resources' => app_path('Http/Resources'),
        'rotas' => base_path('routes'),
        'testes' => base_path('tests'),
    ],

    'artefatos' => [
        'migrations' => true,
        'models' => true,
        'factories' => false,
        'seeders' => false,
        'controllers' => false,
        'requests' => false,
        'resources' => false,
        'rotas' => false,
        'testes' => false,
    ],

    'sobrescrever' => false,

    'namespace_models' => 'App\\Models',
    'namespace_controllers' => 'App\\Http\\Controllers',
    'namespace_requests' => 'App\\Http\\Requests',
    'namespace_resources' => 'App\\Http\\Resources',
];
