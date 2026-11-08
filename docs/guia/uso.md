# Primeiros passos

## Comando básico

```bash
php artisan sql:gerar caminho/para/schema.sql
```

No modo interativo, o comando:

1. Mostra um resumo do schema e avisos de parse
2. Pergunta o preset: Essencial, API completa ou Personalizado
3. No personalizado, pergunta artefatos/tabelas/CRUD/SoftDeletes (lista positiva)
4. Pergunta tabela de users e colunas de e-mail/senha (quando fizer sentido)
5. Exibe plano compacto (`--detalhado` lista tudo)
6. Confirma, instala API se preciso, ajusta users/factory padrão e grava
7. Mostra próximos passos (`migrate`, `db:seed`, `route:list`)

Com `--sem-interacao`, os ajustes automáticos (`.bak` da migration `create_users_table` e `UserFactory`) rodam sem perguntar - útil para CI.

## Simulações

Sessões de terminal ilustrativas (valores fictícios). O que você digita/escolhe aparece depois de `$` ou destacado com `›`.

### 1) Preset essencial (interativo)

```shellsession
$ php artisan sql:gerar database/schema.sql

  INFO  SQL to Eloquent.
  Arquivo: database/schema.sql

  Tabelas .............................................. 8
  Colunas ............................................. 64
  Foreign keys ......................................... 9
  Inserts .............................................. 0

  Tabelas encontradas:
  • usuarios 12 colunas
  • produtos 10 colunas
  • pedidos 8 colunas
  • cupons 6 colunas
  • fornecedores 7 colunas
  ...

 ┌ Como deseja gerar? ─────────────────────────────────────────┐
 │ › Essencial - migrations + models (+ seeders se houver INSERT) │
 │   API completa - CRUD, requests, rotas e autenticação Sanctum │
 │   Personalizado - escolher cada item                          │
 └─────────────────────────────────────────────────────────────┘

 ┌ Qual tabela é a de usuários/autenticação do Laravel? ───────┐
 │ › usuarios                                                  │
 │   nenhuma                                                   │
 └─────────────────────────────────────────────────────────────┘
  // Essa tabela vira App\Models\User (Authenticatable)...

 ┌ Qual coluna é o e-mail de autenticação? ────────────────────┐
 │ › email                                                     │
 │   nome                                                      │
 │   senha                                                     │
 │   ...                                                       │
 └─────────────────────────────────────────────────────────────┘

 ┌ Qual coluna é a senha de autenticação? ─────────────────────┐
 │ › senha                                                     │
 │   email                                                     │
 │   ...                                                       │
 └─────────────────────────────────────────────────────────────┘
  // Se não for password, o model gera getAuthPasswordName()

  INFO  Plano de geração (16 arquivo(s)).
  Artefatos: migrations, models
  Tabela users: usuarios → User
  E-mail auth: email
  Senha auth: senha

  Migrations (8 - 8 novos)
  Models (8 - 8 novos)
  Use --detalhado para listar todos os arquivos.

 ┌ Gravar 16 arquivo(s)? ──────────────────────────────────────┐
 │ › Yes                                                       │
 │   No                                                        │
 └─────────────────────────────────────────────────────────────┘

 ┌ Mover create_users_table.php para .bak? ────────────────────┐
 │ › Yes                                                       │
 │   No                                                        │
 └─────────────────────────────────────────────────────────────┘

 ┌ Ajustar UserFactory (name→nome, password→senha)? ───────────┐
 │ › Yes                                                       │
 │   No                                                        │
 └─────────────────────────────────────────────────────────────┘

  INFO  Geração concluída.
  Criados ............................................. 16
  Ignorados ............................................ 0

  Próximos passos
  1. php artisan migrate
```

No essencial **não** pergunta SoftDeletes, CRUD nem rotas. SoftDeletes só entra com `--soft-deletes` / `--tabelas-soft-delete=` ou no preset personalizado.

### 2) Dry-run (simular sem gravar)

```shellsession
$ php artisan sql:gerar database/schema.sql --preset=essencial --dry-run --detalhado

  INFO  SQL to Eloquent.
  Arquivo: database/schema.sql

  Tabelas .............................................. 8
  ...

  INFO  Plano de geração (16 arquivo(s)).
  Artefatos: migrations, models

  Migrations (8 - 8 novos)
    novo       database/migrations/2026_11_08_000001_create_usuarios_table.php
    novo       database/migrations/2026_11_08_000002_create_produtos_table.php
    ...
  Models (8 - 8 novos)
    novo       app/Models/User.php
    novo       app/Models/Produto.php
    ...

  INFO  Dry-run: nenhum arquivo foi gravado.
```

### 3) Preset API completa

```shellsession
$ php artisan sql:gerar database/schema.sql --preset=api

  INFO  SQL to Eloquent.
  Arquivo: database/schema.sql
  ...

 ┌ Qual tabela é a de usuários/autenticação do Laravel? ───────┐
 │ › usuarios                                                  │
 │   nenhuma                                                   │
 └─────────────────────────────────────────────────────────────┘

 ┌ Qual coluna é o e-mail de autenticação? ────────────────────┐
 │ › email                                                     │
 └─────────────────────────────────────────────────────────────┘

 ┌ Qual coluna é a senha de autenticação? ─────────────────────┐
 │ › senha                                                     │
 └─────────────────────────────────────────────────────────────┘

  INFO  Plano de geração (61 arquivo(s)).
  Artefatos: migrations, models, controllers, requests, seeders, rotas, auth
  CRUD: usuarios, produtos, pedidos, cupons, fornecedores, ...
  Tabela users: usuarios → User
  E-mail auth: email
  Senha auth: senha

  Migrations (8 - 8 novos)
  Models (8 - 8 novos)
  Controllers (7 - 7 novos)
  Requests (14 - 14 novos)
  Rotas (1 - 1 novo)
  Auth (3 - 3 novos)
  Use --detalhado para listar todos os arquivos.

 ┌ Gravar 61 arquivo(s)? ──────────────────────────────────────┐
 │ › Yes                                                       │
 │   No                                                        │
 └─────────────────────────────────────────────────────────────┘

 ┌ Instalar API Laravel agora? (php artisan install:api - Sanctum) ┐
 │ › Yes                                                           │
 │   No                                                            │
 └─────────────────────────────────────────────────────────────────┘

  INFO  Executando php artisan install:api...
  INFO  API Laravel instalada.
  Rotas registradas em routes/api.php.

 ┌ Mover create_users_table.php para .bak? ────────────────────┐
 │ › Yes                                                       │
 └─────────────────────────────────────────────────────────────┘

 ┌ Ajustar UserFactory (name→nome, password→senha)? ───────────┐
 │ › Yes                                                       │
 └─────────────────────────────────────────────────────────────┘

  INFO  Geração concluída.
  Criados ............................................. 61
  Ignorados ............................................ 0

  Próximos passos
  1. php artisan migrate
  2. php artisan db:seed
  3. php artisan route:list --path=api
```

Para pular o `install:api` (cria `routes/api.php` cru):

```shellsession
$ php artisan sql:gerar database/schema.sql --preset=api --sem-install-api
```

### 4) Personalizado (escolhe cada item)

```shellsession
$ php artisan sql:gerar database/schema.sql --preset=personalizado

  INFO  SQL to Eloquent.
  ...

 ┌ Como deseja gerar? ─────────────────────────────────────────┐
 │   Essencial - migrations + models (+ seeders se houver INSERT) │
 │   API completa - CRUD, requests, rotas e autenticação Sanctum │
 │ › Personalizado - escolher cada item                          │
 └─────────────────────────────────────────────────────────────┘

 ┌ O que deseja gerar? ────────────────────────────────────────┐
 │ › migrations                                                │
 │ › models                                                    │
 │ › controllers                                               │
 │   seeders                                                   │
 │ › rotas                                                     │
 └─────────────────────────────────────────────────────────────┘
  // Controllers incluem Form Requests. Rotas API são opcionais

 ┌ Quais tabelas deseja incluir? ──────────────────────────────┐
 │ › usuarios                                                  │
 │ › produtos                                                  │
 │ › pedidos                                                  │
 │ › cupons                                                   │
 │ › fornecedores                                             │
 └─────────────────────────────────────────────────────────────┘

 ┌ Quais tabelas devem ter Controllers CRUD? ──────────────────┐
 │ › usuarios                                                  │
 │ › produtos                                                  │
 │ › pedidos                                                  │
 │ › cupons                                                   │
 │ › fornecedores                                             │
 └─────────────────────────────────────────────────────────────┘
  // Pivots ficam de fora. Cada tabela gera Controller + Requests

 ┌ Em quais tabelas aplicar SoftDeletes? ──────────────────────┐
 │   usuarios                                                  │
 │   produtos                                                  │
 │ › pedidos                                                  │
 │   cupons                                                   │
 │   fornecedores                                             │
 └─────────────────────────────────────────────────────────────┘
  // Deixe vazio para não aplicar. Espaço marca, Enter confirma

 ┌ Qual tabela é a de usuários/autenticação do Laravel? ───────┐
 │ › usuarios                                                  │
 │   nenhuma                                                   │
 └─────────────────────────────────────────────────────────────┘

 ┌ Gerar autenticação API (Sanctum: login/register/logout)? ───┐
 │   Yes                                                       │
 │ › No                                                        │
 └─────────────────────────────────────────────────────────────┘

  INFO  Plano de geração (42 arquivo(s)).
  Artefatos: migrations, models, controllers, requests, rotas
  SoftDeletes: pedidos
  Tabela users: usuarios → User
  ...

 ┌ Gravar 42 arquivo(s)? ──────────────────────────────────────┐
 │ › Yes                                                       │
 └─────────────────────────────────────────────────────────────┘
```

Se controllers estiverem marcados e rotas/auth não, ainda pode aparecer:

```shellsession
 ┌ Gerar rotas API (apiResource)? ─────────────────────────────┐
 │   Yes                                                       │
 │ › No                                                        │
 └─────────────────────────────────────────────────────────────┘
```

### 5) Sem interação (CI / script)

```shellsession
$ php artisan sql:gerar database/schema.sql \
    --preset=api \
    --tabela-users=usuarios \
    --campo-email=email \
    --campo-senha=senha \
    --sobrescrever \
    --sem-interacao

  INFO  SQL to Eloquent.
  Arquivo: database/schema.sql
  ...

  INFO  Plano de geração (61 arquivo(s)).
  Artefatos: migrations, models, controllers, requests, seeders, rotas, auth
  ...

  INFO  Executando php artisan install:api...
  INFO  API Laravel instalada.
  Rotas registradas em routes/api.php.
  Migration create_users_table movida para .bak.
  UserFactory ajustada (name→nome, password→senha).

  INFO  Geração concluída.
  Criados ............................................. 61

  Próximos passos
  1. php artisan migrate
  2. php artisan db:seed
  3. php artisan route:list --path=api
```

Não faz perguntas. Aplica `.bak` da migration users e ajuste da `UserFactory` automaticamente.

### 6) SoftDeletes só em algumas tabelas

```shellsession
$ php artisan sql:gerar database/schema.sql \
    --preset=essencial \
    --tabelas-soft-delete=pedidos,produtos \
    --sem-interacao \
    --dry-run

  INFO  Plano de geração (16 arquivo(s)).
  Artefatos: migrations, models
  SoftDeletes: pedidos, produtos

  INFO  Dry-run: nenhum arquivo foi gravado.
```

## Exemplos rápidos

### Geração interativa

```bash
php artisan sql:gerar database/schema.sql
```

### Preset essencial (não interativo)

```bash
php artisan sql:gerar database/schema.sql --preset=essencial --sem-interacao
```

### API completa

```bash
php artisan sql:gerar database/schema.sql \
  --preset=api \
  --tabela-users=usuarios \
  --campo-email=email \
  --campo-senha=senha \
  --sem-interacao
```

### Filtrar tabelas e simular

```bash
php artisan sql:gerar database/schema.sql \
  --tabelas=usuarios,pedidos \
  --dry-run
```

## Segurança

- O SQL de entrada **não é executado**
- Arquivos existentes não são sobrescritos sem `--sobrescrever` (ou confirmação interativa)
- Estruturas não suportadas geram avisos em vez de código incorreto silencioso
