# Opções da CLI

```bash
php artisan sql:gerar {arquivo} [opções]
```

| Opção | Descrição |
| --- | --- |
| `arquivo` | Caminho do arquivo SQL |
| `--artefatos=` | Lista separada por vírgula: `migrations`, `models`, `controllers`, `seeders` |
| `--tabelas=` | Filtra tabelas (separadas por vírgula) |
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
| `controllers` | Controllers CRUD |
| `seeders` | Seeders (somente se houver `INSERT` no SQL) |

## Exemplos rápidos

```bash
# só models e controllers
php artisan sql:gerar dump.sql --artefatos=models,controllers --sem-interacao

# prévia sem gravar
php artisan sql:gerar dump.sql --dry-run

# sobrescrever conflitos
php artisan sql:gerar dump.sql --sobrescrever --sem-interacao
```
