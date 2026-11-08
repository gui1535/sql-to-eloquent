# Limitações

- Não gera factories, form requests, API resources nem rotas
- Algumas variantes avançadas de `ALTER TABLE` ainda são ignoradas com aviso:
  - rename de tabela
  - conversão de charset
  - drop de constraints/índices
- `CREATE VIEW` é ignorado com aviso
- Singularização de nomes segue regras em português e pode precisar ajuste em casos raros

## Roadmap possível

Sugestões e issues: [github.com/gui1535/sql-to-eloquent/issues](https://github.com/gui1535/sql-to-eloquent/issues)
