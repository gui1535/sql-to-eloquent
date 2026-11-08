# Primeiros passos

## Comando básico

```bash
php artisan sql:gerar caminho/para/schema.sql
```

No modo interativo, o comando:

1. Mostra um resumo do schema (tabelas, colunas, foreign keys e inserts)
2. Pergunta quais artefatos gerar
3. Permite filtrar tabelas
4. Exibe o plano de arquivos (`novo` / `conflito`)
5. Confirma antes de gravar

## Exemplos

### Geração interativa

```bash
php artisan sql:gerar database/schema.sql
```

### Sem perguntas

```bash
php artisan sql:gerar database/schema.sql \
  --artefatos=migrations,models,controllers \
  --sem-interacao
```

### Filtrar tabelas e simular

```bash
php artisan sql:gerar database/schema.sql \
  --tabelas=usuarios,pedidos \
  --dry-run
```

### Diretórios e namespaces

```bash
php artisan sql:gerar database/schema.sql \
  --dir-models=app/Models \
  --dir-controllers=app/Http/Controllers \
  --namespace-models=App\\Models \
  --namespace-controllers=App\\Http\\Controllers \
  --sobrescrever
```

## Segurança

- O SQL de entrada **não é executado**
- Arquivos existentes não são sobrescritos sem `--sobrescrever` (ou confirmação interativa)
- Estruturas não suportadas geram avisos em vez de código incorreto silencioso
