---
id: supported-databases
title: Bancos suportados
---

# Bancos suportados

O PachyBase suporta oficialmente dois drivers hoje:

- `mysql`
- `pgsql`

Esses dois drivers estao cobertos por conexao, introspeccao de schema, migrations, seeds, metadata de CRUD automatico, setup Docker e testes automatizados.

## MySQL

- Valor do driver: `DB_DRIVER=mysql`
- Imagem Docker padrao: `mysql:8`
- Host padrao do container: `db`
- Porta padrao: `3306`
- Caminho de storage no Docker: `/var/lib/mysql`

Exemplo:

```env
DB_DRIVER=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=pachybase
DB_USERNAME=pachybase
DB_PASSWORD=change_this_password
```

## PostgreSQL

- Valor do driver: `DB_DRIVER=pgsql`
- Imagem Docker padrao: `postgres:15`
- Host padrao do container: `db`
- Porta padrao: `5432`
- Variavel opcional de schema: `DB_SCHEMA`, com padrao `public`
- Caminho de storage no Docker: `/var/lib/postgresql/data`

Exemplo:

```env
DB_DRIVER=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=pachybase
DB_USERNAME=pachybase
DB_PASSWORD=change_this_password
DB_SCHEMA=public
```

## Garantias de comportamento

- O adapter e selecionado automaticamente por `DB_DRIVER`
- A metadata do schema e normalizada antes de alimentar CRUD e OpenAPI
- Migrations e seeds usam a mesma abstracao de adapter do runtime
- O instalador Docker so gera stacks para drivers oficialmente suportados

## Ainda nao suportado

Considere estes engines fora do escopo oficial ate haver suporte explicito:

- SQLite
- SQL Server
- Oracle
- MariaDB como alvo de compatibilidade separado
