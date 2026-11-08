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
        'requests' => app_path('Http/Requests'),
        'seeders' => database_path('seeders'),
        'rotas' => base_path('routes'),
    ],

    'artefatos' => [
        'migrations' => true,
        'models' => true,
        'controllers' => false,
        'requests' => false,
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
```

O default (e o preset **essencial**) gera migrations + models + seeders. Controllers, requests, rotas e auth ficam desligados até o preset **api** ou o modo personalizado.

## Campos

| Chave | Função |
| --- | --- |
| `caminhos` | Diretórios padrão de saída |
| `artefatos` | Quais artefatos vêm marcados por padrão |
| `sobrescrever` | Se arquivos existentes podem ser substituídos |
| `soft_deletes` | SoftDeletes padrão (off) |
| `namespace_*` | Namespaces padrão |
| `tabela_users` / `campo_email` / `campo_senha` | Auth padrão (opcional) |

Flags da CLI (`--dir-*`, `--namespace-*`, `--artefatos`, `--preset`) têm prioridade sobre a config.
