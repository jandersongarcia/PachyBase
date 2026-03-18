---
id: cli
title: CLI
sidebar_position: 3
---

# CLI

O PachyBase inclui uma CLI de projeto para as duas trilhas oficiais. Docker continua sendo o caminho principal, e a mesma superficie de comandos tambem suporta a alternativa gerenciada na host quando `APP_RUNTIME=local`.

## Launchers

### Windows

```powershell
.\pachybase.bat help
```

### Linux

```bash
chmod +x pachybase
./pachybase help
```

## Comandos

Ciclo de vida:

- `install`: sincroniza `.env`, detecta o runtime ativo, configura defaults de auth, prepara o banco e gera artefatos legiveis por maquina
- `start`: inicia os servicos Docker ou o runtime local de PHP com base em `APP_RUNTIME`
- `stop`: encerra o runtime ativo
- `doctor`: valida postura do runtime, consistencia do `.env`, alinhamento com Docker e configuracoes sensiveis de release
- `status`: retorna uma visao rapida de runtime, banco, auth e docs geradas
- `test`: executa a suite PHPUnit no Docker ou localmente, conforme o runtime configurado

Ambiente:

- `env:sync`: cria ou completa `.env` a partir de `.env.example` sem destruir customizacoes validas
- `env:validate`: valida variaveis obrigatorias e consistencia minima de configuracao
- `app:key`: gera ou regenera a chave principal da aplicacao

Docker:

- `docker:sync`: gera e sincroniza `docker/docker-compose.yml`
- `docker:up`: sobe os containers do projeto
- `docker:down`: para e remove os containers do projeto
- `docker:logs`: exibe logs do Docker para inspecao operacional

Banco:

- `db:setup`: aguarda o banco ficar pronto e prepara a base de migrations
- `db:migrate`: aplica migrations pendentes
- `db:rollback`: desfaz o ultimo lote de migrations
- `db:seed`: executa seeders configurados
- `db:fresh`: recria o banco de desenvolvimento do zero

Scaffolding:

- `make:module`: cria uma classe base de modulo em `modules/`
- `make:entity`: registra uma nova entrada CRUD em `config/CrudEntities.php`
- `make:migration`: cria um arquivo de migration com timestamp
- `make:seed`: cria um arquivo de seed com timestamp
- `make:controller`: cria um controller API-first com resposta JSON padrao
- `make:service`: cria um service base em `services/`
- `make:middleware`: cria um middleware compativel com o pipeline HTTP
- `make:test`: cria um esqueleto de teste unitario ou funcional em PHPUnit
- `crud:generate`: expoe novas entradas CRUD baseadas no schema ou registra rapidamente uma entidade nova

Build e inspecao:

- `auth:install`: configura os segredos padrao de autenticacao e, opcionalmente, prepara a persistencia de auth
- `entity:list`: inspeciona a metadata normalizada das entidades
- `crud:sync`: regenera `config/CrudEntities.php` a partir do schema ativo
- `openapi:build`: grava um documento OpenAPI estatico, por padrao em `build/openapi.json`
- `ai:build`: grava o documento de schema orientado a IA, por padrao em `build/ai-schema.json`
- `version`: imprime a versao atual do release

## Fluxo tipico

```bash
./pachybase install
./pachybase status
./pachybase entity:list
./pachybase crud:generate --expose-new
./pachybase openapi:build
./pachybase ai:build
./pachybase test
```

## Equivalentes para instalacao local

Se voce nao estiver usando Docker, defina `APP_RUNTIME=local` e use a mesma CLI primeiro. Os equivalentes diretos na host continuam disponiveis quando necessario:

```bash
composer install
php scripts/env-validate.php
php scripts/bootstrap-database.php
php scripts/migrate.php up
php scripts/seed.php run
php scripts/status.php
php scripts/openapi-generate.php
php scripts/ai-build.php
vendor/bin/phpunit --testdox
```

## Opcoes uteis

- `env:sync --force`: sobrescreve o `.env` atual
- `env:validate --json`: imprime o relatorio de validacao em JSON
- `app:key --force`: rotaciona intencionalmente a chave da aplicacao
- `crud:sync --expose-new`: marca entidades novas introspectadas como expostas
- `crud:sync --output=path/to/CrudEntities.php`: grava a configuracao CRUD em outro caminho
- `make:entity nome --table=pb_nome`: registra um mapeamento especifico entre entidade CRUD e tabela
- `make:test Example --type=functional`: cria um esqueleto de teste funcional
- `openapi:build --output=docs-site/static/openapi.json`: publica a especificacao gerada em um caminho customizado
- `ai:build --output=build/ai-schema.json`: publica o schema de IA em um caminho customizado
