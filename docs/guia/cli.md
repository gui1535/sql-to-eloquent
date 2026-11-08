# Opções da CLI

```bash
php artisan sql:gerar {arquivo} [opções]
```

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
| `--tabela-users=` | Tabela de autenticação (`users`, `usuarios` ou `nenhuma`) |
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

## Presets

| Preset | Gera |
| --- | --- |
| `essencial` (default interativo) | Migrations + models (+ seeders se houver `INSERT`) |
| `api` | Essencial + controllers, requests, rotas e auth Sanctum |
| `personalizado` | Você escolhe cada item |

## Artefatos disponíveis

| Valor | Gera |
| --- | --- |
| `migrations` | Migrations Laravel |
| `models` | Models Eloquent |
| `controllers` | Controllers CRUD + Form Requests |
| `seeders` | Seeders (somente se houver `INSERT` no SQL) |
| `rotas` | Arquivo `routes/sql-to-eloquent.php` com `apiResource` e `parameters()` em português |
| `auth` | Auth API Sanctum (login/register/me/logout) |

## Exemplos rápidos

```bash
# essencial sem perguntas
php artisan sql:gerar dump.sql --preset=essencial --sem-interacao

# API completa (pula install:api)
php artisan sql:gerar dump.sql --preset=api --sem-install-api --sobrescrever --sem-interacao

# models + CRUD só em pedidos
php artisan sql:gerar dump.sql --artefatos=models,controllers --tabelas-crud=pedidos --sem-interacao

# prévia sem gravar
php artisan sql:gerar dump.sql --dry-run --detalhado
```
