---
id: configuration
title: Configuracao
---

# Configuracao

O PachyBase usa `.env` como fonte principal de configuracao em runtime e um conjunto pequeno de arquivos PHP para comportamentos que devem ficar versionados com o projeto.

## Camadas de configuracao

- `.env`: valores especificos do ambiente, como modo da aplicacao, banco e segredos de auth
- `config/AppConfig.php`: helpers de ambiente e debug
- `config/AuthConfig.php`: JWT, TTL de refresh e defaults do admin bootstrap
- `config/CrudEntities.php`: exposicao declarativa do CRUD, filtros, campos gravaveis, ordenacao e validacao

## Valores obrigatorios no `.env`

```env
APP_NAME=PachyBase
APP_ENV=development
APP_DEBUG=true

DB_DRIVER=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=pachybase
DB_USERNAME=pachybase
DB_PASSWORD=change_this_password
```

## Valores opcionais de banco

- `DB_SCHEMA`: schema do PostgreSQL. O padrao e `public`.

## Valores opcionais de autenticacao

- `AUTH_JWT_SECRET`: obrigatorio em producao; em desenvolvimento existe fallback local
- `AUTH_JWT_ISSUER`: emissor do token
- `AUTH_ACCESS_TTL_MINUTES`: TTL do access token, padrao `15`
- `AUTH_REFRESH_TTL_DAYS`: TTL do refresh token, padrao `30`
- `AUTH_BOOTSTRAP_ADMIN_EMAIL`: email do admin bootstrap de desenvolvimento
- `AUTH_BOOTSTRAP_ADMIN_PASSWORD`: senha do admin bootstrap de desenvolvimento
- `AUTH_BOOTSTRAP_ADMIN_NAME`: nome exibido do admin bootstrap de desenvolvimento

## Configuracao do CRUD

`config/CrudEntities.php` e onde a superficie do CRUD automatico e curada. Cada entidade pode definir:

- `slug` e `table`
- se a entidade fica exposta no modulo de CRUD automatico
- campos permitidos, ocultos e readonly
- campos pesquisaveis, filtraveis e ordenaveis
- ordenacao padrao e `max_per_page`
- regras de validacao e hooks leves

## Checklist recomendado para producao

- Definir `APP_ENV=production`
- Definir `APP_DEBUG=false`
- Configurar `AUTH_JWT_SECRET`
- Revisar as entidades expostas em `config/CrudEntities.php`
- Trocar as credenciais bootstrap antes do primeiro uso publico

## Onde alterar comportamento

- Runtime da app: `.env` e `config/AppConfig.php`
- Autenticacao: `.env` e `config/AuthConfig.php`
- Superficie CRUD: `config/CrudEntities.php`
- Composicao de rotas: `routes/api.php` e `modules/`
