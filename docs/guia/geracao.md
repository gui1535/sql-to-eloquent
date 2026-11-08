# O que é gerado

## Migrations

Uma migration por tabela, ordenada pelas foreign keys, com mapeamento para Blueprint:

- `id()`, `string`, `decimal`, `enum`, `boolean`
- índices e uniques
- foreign keys
- `timestamps()` e `softDeletes()` quando aplicável

## Models

Cada tabela gera um model com:

- `$table`
- `$fillable`
- `$hidden` (ex.: `senha`, `password`)
- `$casts`
- timestamps padrão ou `CREATED_AT` / `UPDATED_AT` (`criado_em` / `atualizado_em`)
- relacionamentos a partir das FKs:
  - `belongsTo`
  - `hasMany`
  - `hasOne` (quando a FK é única)
  - `belongsToMany` (tabelas pivot)

Pivots puras não geram model. SoftDeletes é opcional via CLI.

Se você escolher a tabela de users, o model vira `User` (`Authenticatable`) com `$table` apontando para a tabela real (ex.: `usuarios`). Quando a senha não for `password`, gera `getAuthPasswordName()`.

## Controllers e Form Requests

Controllers CRUD com `index`, `store`, `show`, `update` e `destroy`, incluindo `with()` / `load()` nos relacionamentos.

A validação fica em:

- `Store{Model}Request` (`required` + `exists:` nas FKs)
- `Update{Model}Request` (`sometimes` + `exists:` nas FKs)

Pivots não geram CRUD.

## Rotas e autenticação API

Rotas são opcionais. Arquivo `routes/sql-to-eloquent.php` com `Route::apiResource(...)` e `->parameters()` quando o nome do parâmetro difere (ex.: `usuarios` → `user`).

Autenticação API (opcional, Sanctum):

- `AuthController` com `register`, `login`, `me`, `logout`
- `LoginRequest` / `RegisterRequest`
- `User` com `HasApiTokens`
- rotas em `/auth/*` e CRUD protegido por `auth:sanctum`

Quando auth ou rotas API forem geradas, o comando pode executar `php artisan install:api --without-migration-prompt` e registrar `routes/sql-to-eloquent.php` em `routes/api.php`.

Para pular a instalação automática: `--sem-install-api`.

## Seeders

Gerados somente quando o SQL contém `INSERT`.

Também gera `DatabaseSeeder` chamando os seeders na ordem das foreign keys.
