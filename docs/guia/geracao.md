# O que é gerado

## Migrations

Uma migration por tabela, ordenada pelas foreign keys, com mapeamento para Blueprint:

- `id()`, `string`, `decimal`, `enum`, `boolean`
- índices e uniques
- foreign keys
- `timestamps()` quando aplicável

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

## Controllers

Controllers CRUD com:

- `index`
- `store`
- `show`
- `update`
- `destroy`

A validação é montada com base nas colunas da tabela.

As rotas **não** são geradas. Você registra os endpoints no projeto, por exemplo:

```php
Route::apiResource('pedidos', PedidoController::class);
```

## Seeders

Gerados somente quando o SQL contém `INSERT`.

Sem dados de inserção, nenhum seeder é criado, mesmo com o artefato `seeders` selecionado.
