# Limitações

- Não gera factories novas, API resources nem policies
- Algumas variantes avançadas de `ALTER TABLE` ainda são ignoradas com aviso:
  - rename de tabela
  - conversão de charset
  - drop de constraints/índices
- `CREATE VIEW` é ignorado com aviso claro
- Singularização de nomes segue regras em português e pode precisar ajuste em casos raros
- `--sem-install-api` registra rotas, mas Sanctum (`HasApiTokens`) exige `php artisan install:api`

## Roadmap possível

Sugestões e issues: [github.com/gui1535/sql-to-eloquent/issues](https://github.com/gui1535/sql-to-eloquent/issues)
