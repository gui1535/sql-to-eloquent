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

Com `--sem-interacao`, os ajustes automáticos (`.bak` da migration `create_users_table` e `UserFactory`) rodam sem perguntar — útil para CI.

## Exemplos

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
