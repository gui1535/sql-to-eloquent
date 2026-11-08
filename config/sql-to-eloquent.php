<?php

return [
    'caminhos' => [
        'migrations' => database_path('migrations'),
        'models' => app_path('Models'),
        'controllers' => app_path('Http/Controllers'),
        'seeders' => database_path('seeders'),
    ],

    'artefatos' => [
        'migrations' => true,
        'models' => true,
        'controllers' => true,
        'seeders' => true,
    ],

    'sobrescrever' => false,

    'namespace_models' => 'App\\Models',
    'namespace_controllers' => 'App\\Http\\Controllers',
];
