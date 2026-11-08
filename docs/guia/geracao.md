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

Pivots puras não geram model. SoftDeletes é opcional via CLI (lista positiva no interativo).

Se você escolher a tabela de users, o model vira `User` (`Authenticatable`) com `$table` apontando para a tabela real (ex.: `usuarios`). Quando a senha não for `password` e a auth API estiver ligada, gera `getAuthPasswordName()`.

## Controllers e Form Requests

Controllers CRUD com `index`, `store`, `show`, `update` e `destroy`, incluindo `with()` / `load()` nos relacionamentos.

A validação fica em:

- `Store{Model}Request` (`required` + `exists:` nas FKs)
- `Update{Model}Request` (`sometimes` + `exists:` nas FKs)

Pivots não geram CRUD. No default da config (e no preset essencial) controllers/requests ficam desligados.

## Rotas e autenticação API

Rotas são opcionais. Arquivo `routes/sql-to-eloquent.php` com `Route::apiResource(...)->parameters([...])` usando singular em português (ex.: `cupons` → `{cupom}`, `fornecedores` → `{fornecedor}`, `usuarios` → `{user}`).

Autenticação API (opcional, Sanctum):

- `AuthController` com `register`, `login`, `me`, `logout`
- `LoginRequest` / `RegisterRequest`
- `User` com `HasApiTokens`
- rotas em `/auth/*` e CRUD protegido por `auth:sanctum`

Quando auth ou rotas API forem geradas, o comando pode:

1. Executar `php artisan install:api --without-migration-prompt` (ou criar `routes/api.php` com `--sem-install-api`)
2. Registrar a API em `bootstrap/app.php` se precisar
3. Fazer `require` de `sql-to-eloquent.php` em `routes/api.php`
4. Mover a migration padrão `create_users_table` para `.bak`
5. Ajustar `UserFactory` (`name`→`nome`, `password`→`senha`)

Avisos sobre users/factory que já foram resolvidos por esses ajustes não reaparecem no resumo final.

`--sem-install-api` basta para listar rotas; `HasApiTokens` ainda precisa do Sanctum instalado (`install:api` é o caminho completo).

## Seeders

Gerados somente quando o SQL contém `INSERT`.

Também gera `DatabaseSeeder` chamando os seeders na ordem das foreign keys.
