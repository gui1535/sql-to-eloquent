# SQL to Eloquent

[![Latest Version on Packagist](https://img.shields.io/packagist/v/gui1535/sql-to-eloquent.svg?style=flat-square)](https://packagist.org/packages/gui1535/sql-to-eloquent)
[![Total Downloads](https://img.shields.io/packagist/dt/gui1535/sql-to-eloquent.svg?style=flat-square)](https://packagist.org/packages/gui1535/sql-to-eloquent)
[![License](https://img.shields.io/packagist/l/gui1535/sql-to-eloquent.svg?style=flat-square)](LICENSE)
[![Docs](https://img.shields.io/badge/docs-github%20pages-blue?style=flat-square)](https://gui1535.github.io/sql-to-eloquent/)

Gera artefatos Laravel a partir de schemas e dumps SQL MySQL/MariaDB.

Documentação: [gui1535.github.io/sql-to-eloquent](https://gui1535.github.io/sql-to-eloquent/)

A biblioteca interpreta dumps MySQL/MariaDB (sem executá-los) e cria migrations, models Eloquent, controllers CRUD e seeders quando houver `INSERT` no arquivo.

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

1. Mostra um resumo do schema (tabelas, colunas, foreign keys e inserts)
2. Pergunta quais tabelas incluir
3. Pergunta o que gerar (Controllers CRUD já incluem Form Requests)
4. Se houver CRUD, pergunta quais tabelas devem ter controller + requests
5. Pergunta se deseja SoftDeletes e quais tabelas excluir
6. Pergunta qual tabela é a de usuários/autenticação
7. Exibe o plano de arquivos (`novo` / `conflito` / `substituir`)
8. Confirma antes de gravar

### Exemplos

Geração interativa:

```bash
php artisan sql:gerar database/schema.sql
```

Sem perguntas:

```bash
php artisan sql:gerar database/schema.sql \
  --artefatos=migrations,models,controllers \
  --tabelas-crud=usuarios,pedidos \
  --tabela-users=usuarios \
  --sem-interacao
```

Filtrar tabelas e simular sem gravar:

```bash
php artisan sql:gerar database/schema.sql \
  --tabelas=usuarios,pedidos \
  --dry-run
```

Definir diretórios e namespaces:

```bash
php artisan sql:gerar database/schema.sql \
  --dir-models=app/Models \
  --dir-controllers=app/Http/Controllers \
  --namespace-models=App\\Models \
  --namespace-controllers=App\\Http\\Controllers \
  --sobrescrever
```

## Opções da CLI

| Opção | Descrição |
| --- | --- |
| `arquivo` | Caminho do arquivo SQL |
| `--artefatos=` | Lista separada por vírgula: `migrations`, `models`, `controllers`, `seeders` (`controllers` já inclui Form Requests) |
| `--tabelas=` | Filtra tabelas (separadas por vírgula) |
| `--tabelas-crud=` | Tabelas com Controller CRUD + Form Requests |
| `--soft-deletes` | Ativa SoftDeletes nas tabelas incluídas |
| `--sem-soft-delete=` | Tabelas sem SoftDeletes (usar com `--soft-deletes`) |
| `--tabelas-soft-delete=` | Lista explícita de tabelas com SoftDeletes |
| `--tabela-users=` | Tabela de autenticação Laravel (`users`, `usuarios`, ou `nenhuma`) |
| `--dir-migrations=` | Diretório de saída das migrations |
| `--dir-models=` | Diretório de saída dos models |
| `--dir-controllers=` | Diretório de saída dos controllers |
| `--dir-seeders=` | Diretório de saída dos seeders |
| `--namespace-models=` | Namespace dos models |
| `--namespace-controllers=` | Namespace dos controllers |
| `--sobrescrever` | Permite sobrescrever arquivos existentes |
| `--dry-run` | Mostra o plano sem gravar arquivos |
| `--sem-interacao` | Executa sem perguntas |

## O que é gerado

### Migrations

Uma migration por tabela, ordenada pelas foreign keys, com mapeamento para Blueprint (`id()`, `string`, `decimal`, `enum`, índices, FKs, etc.).

### Models

- `$table`, `$fillable`, `$hidden`, `$casts`
- Timestamps padrão ou `CREATED_AT` / `UPDATED_AT` (`criado_em` / `atualizado_em`)
- Relacionamentos a partir das FKs: `belongsTo`, `hasMany`, `hasOne`

### Controllers e Form Requests

Controllers CRUD com `index`, `store`, `show`, `update`, `destroy`.

A validação fica em Form Requests separados (`StoreXRequest` / `UpdateXRequest`).

Se você informar a tabela de users (`--tabela-users=usuarios`), o model gerado será `User` estendendo `Authenticatable`.

As rotas não são geradas. Você registra os endpoints no seu projeto.

### Seeders

Gerados somente quando o SQL contém `INSERT`. Sem dados de inserção, nenhum seeder é criado.

## Configuração

Arquivo publicado: `config/sql-to-eloquent.php`

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

## Comportamento de segurança

- O SQL de entrada **não é executado**
- Arquivos existentes não são sobrescritos sem `--sobrescrever` (ou confirmação interativa)
- Estruturas não suportadas geram avisos em vez de código incorreto silencioso

## Suporte SQL

Dialectos: **MySQL** e **MariaDB**.

O parser interpreta:

- `CREATE TABLE`
- `ALTER TABLE` (`ADD`/`DROP`/`MODIFY`/`CHANGE` COLUMN, índices e foreign keys)
- `CREATE INDEX` / `CREATE UNIQUE INDEX`
- `INSERT`

Statements como `CREATE VIEW` ainda são ignorados com aviso.

## Limitações atuais

- Não gera factories, API resources nem rotas
- Algumas variantes avançadas de `ALTER TABLE` (rename de tabela, conversão de charset, drop de constraints) ainda são ignoradas com aviso

## Testes

```bash
composer install
./vendor/bin/phpunit
```

## Licença

MIT
