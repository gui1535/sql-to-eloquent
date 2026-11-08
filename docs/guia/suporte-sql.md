# Suporte SQL

## Dialectos

- MySQL
- MariaDB

## Statements interpretados

| Statement | Situação |
| --- | --- |
| `CREATE TABLE` | Suportado |
| `ALTER TABLE` | Suportado para colunas, índices e foreign keys |
| `CREATE INDEX` / `CREATE UNIQUE INDEX` | Suportado |
| `INSERT` | Suportado (usado para seeders) |
| `CREATE VIEW` | Ignorado com aviso |
| `SET`, `USE`, `DROP TABLE` | Ignorados sem aviso |

## ALTER TABLE suportado

- `ADD COLUMN` / `DROP COLUMN`
- `MODIFY COLUMN` / `CHANGE COLUMN`
- `ADD INDEX` / `ADD UNIQUE` / `ADD FOREIGN KEY`
- `ADD PRIMARY KEY`

## Observação

O SQL de entrada nunca é executado. A biblioteca apenas interpreta a estrutura para gerar código PHP.
