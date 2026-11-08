# Opções da CLI

```bash
php artisan sql:gerar {arquivo} [opções]
```

| Opção | Descrição |
| --- | --- |
| `arquivo` | Caminho do arquivo SQL |
| `--artefatos=` | Lista separada por vírgula: `migrations`, `models`, `controllers`, `seeders`, `rotas`, `auth` (`controllers` já inclui Form Requests) |
| `--auth` | Gera autenticação API (login/register/logout com Sanctum) |
| `--sem-install-api` | Não executa `php artisan install:api` automaticamente |
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
| `--sem-interacao` | Executa sem perguntas |

## Artefatos disponíveis

| Valor | Gera |
| --- | --- |
| `migrations` | Migrations Laravel |
| `models` | Models Eloquent |
| `controllers` | Controllers CRUD + Form Requests |
| `seeders` | Seeders (somente se houver `INSERT` no SQL) |
| `rotas` | Arquivo `routes/sql-to-eloquent.php` com `apiResource` |
| `auth` | Auth API Sanctum (login/register/me/logout) |

## Exemplos rápidos

```bash
# models + CRUD só em pedidos
php artisan sql:gerar dump.sql --artefatos=models,controllers --tabelas-crud=pedidos --sem-interacao

# prévia sem gravar
php artisan sql:gerar dump.sql --dry-run

# sobrescrever conflitos
php artisan sql:gerar dump.sql --sobrescrever --sem-interacao
```
