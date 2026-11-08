# SQL to Eloquent

[![Latest Version on Packagist](https://img.shields.io/packagist/v/gui1535/sql-to-eloquent.svg?style=flat-square)](https://packagist.org/packages/gui1535/sql-to-eloquent)
[![Total Downloads](https://img.shields.io/packagist/dt/gui1535/sql-to-eloquent.svg?style=flat-square)](https://packagist.org/packages/gui1535/sql-to-eloquent)
[![License](https://img.shields.io/packagist/l/gui1535/sql-to-eloquent.svg?style=flat-square)](LICENSE)
[![Docs](https://img.shields.io/badge/docs-github%20pages-blue?style=flat-square)](https://gui1535.github.io/sql-to-eloquent/)

Gera artefatos Laravel a partir de schemas e dumps SQL MySQL/MariaDB.

Documentação: [gui1535.github.io/sql-to-eloquent](https://gui1535.github.io/sql-to-eloquent/)

A biblioteca interpreta dumps MySQL/MariaDB (sem executá-los) e cria migrations, models Eloquent, controllers CRUD, Form Requests, seeders, rotas API e auth Sanctum opcional.

## Requisitos

- PHP 8.2+
- Laravel 11 ou 12

## Instalação

```bash
composer require gui1535/sql-to-eloquent
```

O service provider é registrado automaticamente.

Publique a configuração (opcional):

```bash
php artisan vendor:publish --tag=sql-to-eloquent-config
```

## Uso

```bash
php artisan sql:gerar caminho/para/schema.sql
```

No modo interativo, o comando:

1. Mostra um resumo do schema e avisos de parse
2. Pergunta o preset: **Essencial**, **API completa** ou **Personalizado**
3. No personalizado, pergunta artefatos/tabelas/CRUD/SoftDeletes (lista positiva)
4. Pergunta tabela de users e colunas de e-mail/senha (quando fizer sentido)
5. Exibe plano compacto (`--detalhado` lista tudo)
6. Confirma, instala API se preciso, ajusta users/factory padrão e grava
7. Mostra próximos passos (`migrate`, `db:seed`, `route:list`)

### Exemplos

Geração interativa:

```bash
php artisan sql:gerar database/schema.sql
```

Preset essencial (migrations + models + seeders):

```bash
php artisan sql:gerar database/schema.sql --preset=essencial --sem-interacao
```

API completa:

```bash
php artisan sql:gerar database/schema.sql \
  --preset=api \
  --tabela-users=usuarios \
  --campo-email=email \
  --campo-senha=senha \
  --sem-interacao
```

Filtrar tabelas e simular sem gravar:

```bash
php artisan sql:gerar database/schema.sql \
  --tabelas=usuarios,pedidos \
  --dry-run
```

## Opções da CLI

| Opção | Descrição |
| --- | --- |
| `arquivo` | Caminho do arquivo SQL |
| `--preset=` | `essencial`, `api` ou `personalizado` |
| `--artefatos=` | Lista separada por vírgula: `migrations`, `models`, `controllers`, `seeders`, `rotas`, `auth` (`controllers` já inclui Form Requests) |
| `--auth` | Gera autenticação API (login/register/logout com Sanctum) |
| `--sem-install-api` | Não executa `php artisan install:api` (cria `routes/api.php` cru; Sanctum pode faltar) |
| `--detalhado` | Lista todos os arquivos no plano e no resumo |
| `--tabelas=` | Filtra tabelas (separadas por vírgula) |
| `--tabelas-crud=` | Tabelas com Controller CRUD + Form Requests |
| `--soft-deletes` | Ativa SoftDeletes nas tabelas incluídas |
| `--sem-soft-delete=` | Tabelas sem SoftDeletes (usar com `--soft-deletes`) |
| `--tabelas-soft-delete=` | Lista explícita de tabelas com SoftDeletes |
| `--tabela-users=` | Tabela de autenticação Laravel (`users`, `usuarios`, ou `nenhuma`) |
| `--campo-email=` | Coluna de e-mail da tabela de auth |
| `--campo-senha=` | Coluna de senha da tabela de auth |
| `--dir-migrations=` | Diretório de saída das migrations |
| `--dir-models=` | Diretório de saída dos models |
| `--dir-controllers=` | Diretório de saída dos controllers |
| `--dir-seeders=` | Diretório de saída dos seeders |
| `--namespace-models=` | Namespace dos models |
| `--namespace-controllers=` | Namespace dos controllers |
| `--sobrescrever` | Permite sobrescrever arquivos existentes |
| `--dry-run` | Mostra o plano sem gravar arquivos |
| `--sem-interacao` | Executa sem perguntas (também aplica `.bak` da migration users e ajuste da UserFactory sem confirmar) |

## O que é gerado

### Migrations

Uma migration por tabela, ordenada pelas foreign keys, com mapeamento para Blueprint (`id()`, `string`, `decimal`, `enum`, índices, FKs, etc.).

### Models

- `$table`, `$fillable`, `$hidden`, `$casts`
- Timestamps padrão ou `CREATED_AT` / `UPDATED_AT` (`criado_em` / `atualizado_em`)
- Relacionamentos: `belongsTo`, `hasMany`, `hasOne`, `belongsToMany` (pivots)
- SoftDeletes opcional; auth com `getAuthPasswordName()` quando a senha não for `password`

### Controllers, Form Requests, rotas e auth

Controllers CRUD com `with()` nos relacionamentos e Form Requests (`Store` / `Update` com `sometimes` e `exists:` nas FKs).

Rotas API (`routes/sql-to-eloquent.php`) usam `apiResource()->parameters([...])` com singular em português (ex.: `cupons/{cupom}`, `fornecedores/{fornecedor}`). Autenticação API (Sanctum) é opcional quando há tabela de users.

Ao gravar, o pacote pode: criar/registrar `routes/api.php`, fazer require de `sql-to-eloquent.php`, mover `create_users_table` para `.bak` e ajustar `UserFactory` (`name`→`nome`, `password`→`senha`). Avisos já resolvidos por esses ajustes não reaparecem no resumo.

Pivots N:N não geram CRUD.

### Seeders

Gerados somente quando o SQL contém `INSERT`, com `DatabaseSeeder` na ordem das FKs.

## Configuração

Arquivo publicado: `config/sql-to-eloquent.php`

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

O default da config (e o preset **essencial**) gera migrations + models + seeders. Controllers, rotas e auth ficam off até o preset **api** ou o modo personalizado.

## Comportamento de segurança

- O SQL de entrada **não é executado**
- Arquivos existentes não são sobrescritos sem `--sobrescrever` (ou confirmação interativa)
- Estruturas não suportadas geram avisos em vez de código incorreto silencioso

## Suporte SQL

Dialectos: **MySQL** e **MariaDB**.

O parser interpreta:

- `CREATE TABLE`
- `ALTER TABLE` (`ADD`/`DROP`/`MODIFY`/`CHANGE` COLUMN, índices e foreign keys, inclusive `AFTER`)
- `CREATE INDEX` / `CREATE UNIQUE INDEX`
- `INSERT`

Statements como `CREATE VIEW` ainda são ignorados com aviso claro.

## Limitações atuais

- Não gera factories novas, API resources nem policies
- Algumas variantes avançadas de `ALTER TABLE` (rename de tabela, conversão de charset, drop de constraints) ainda são ignoradas com aviso
- Singularização de nomes segue regras em português e pode precisar ajuste em casos raros
- `--sem-install-api` registra rotas, mas `HasApiTokens` exige Sanctum (`php artisan install:api`)

## Testes

```bash
composer install
./vendor/bin/phpunit
```

## Licença

MIT
