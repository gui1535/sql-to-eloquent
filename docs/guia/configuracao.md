# Configuração

Publique o arquivo:

```bash
php artisan vendor:publish --tag=sql-to-eloquent-config
```

Arquivo: `config/sql-to-eloquent.php`

```php
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
```

## Campos

| Chave | Função |
| --- | --- |
| `caminhos` | Diretórios padrão de saída |
| `artefatos` | Quais artefatos vêm marcados por padrão |
| `sobrescrever` | Se arquivos existentes podem ser substituídos |
| `namespace_models` | Namespace padrão dos models |
| `namespace_controllers` | Namespace padrão dos controllers |

Flags da CLI (`--dir-*`, `--namespace-*`, `--artefatos`) têm prioridade sobre a config.
