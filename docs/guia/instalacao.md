# Instalação

## Requisitos

- PHP 8.2+
- Laravel 11 ou 12

## Via Composer

```bash
composer require gui1535/sql-to-eloquent
```

Versão atual da documentação: **v1.1.0**.

```bash
composer require gui1535/sql-to-eloquent:^1.1
```

O service provider é registrado automaticamente pelo Laravel Package Discovery.

## Publicar configuração

Opcional:

```bash
php artisan vendor:publish --tag=sql-to-eloquent-config
```

Isso cria `config/sql-to-eloquent.php` no seu projeto.

## Verificar o comando

```bash
php artisan list | grep sql
```

Deve aparecer `sql:gerar`.
