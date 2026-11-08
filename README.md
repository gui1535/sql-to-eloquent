# SQL to Eloquent

Gera artefatos Laravel a partir de schemas e dumps SQL MySQL/MariaDB.

A biblioteca interpreta o SQL (sem executá-lo) e cria migrations, models Eloquent, controllers CRUD e seeders quando houver `INSERT` no arquivo.

## Requisitos

- PHP 8.2+
- Laravel 11 ou 12

## Instalação

```bash
composer require sql-to-eloquent/sql-to-eloquent
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
2. Pergunta quais artefatos gerar
3. Permite filtrar tabelas
4. Exibe o plano de arquivos (`novo` / `conflito`)
5. Confirma antes de gravar

### Exemplos

Geração interativa:

```bash
php artisan sql:gerar database/schema.sql
```

Sem perguntas:

```bash
php artisan sql:gerar database/schema.sql \
  --artefatos=migrations,models,controllers \
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

## O que é gerado

### Migrations

Uma migration por tabela, ordenada pelas foreign keys, com mapeamento para Blueprint (`id()`, `string`, `decimal`, `enum`, índices, FKs, etc.).

### Models

- `$table`, `$fillable`, `$hidden`, `$casts`
- Timestamps padrão ou `CREATED_AT` / `UPDATED_AT` (`criado_em` / `atualizado_em`)
- Relacionamentos a partir das FKs: `belongsTo`, `hasMany`, `hasOne`

### Controllers

Controllers CRUD com:

- `index`, `store`, `show`, `update`, `destroy`
- Validação baseada nas colunas da tabela

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

## Limitações atuais

- Suporte focado em MySQL/MariaDB
- Parser principal: `CREATE TABLE` e `INSERT`
- Statements como `ALTER TABLE`, `CREATE VIEW` e `CREATE INDEX` soltos são ignorados com aviso
- Não gera factories, form requests, API resources nem rotas

## Testes

```bash
composer install
./vendor/bin/phpunit
```

## Licença

MIT
