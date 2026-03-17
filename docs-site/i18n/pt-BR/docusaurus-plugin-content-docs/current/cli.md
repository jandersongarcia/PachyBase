---
id: cli
title: CLI
sidebar_position: 3
---

# CLI

O PachyBase agora inclui uma CLI de projeto para que instalacao, ciclo de vida do Docker, migrations, inspecao de metadata, sincronizacao de CRUD, geracao de OpenAPI e testes usem uma superficie operacional previsivel.

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

- `install`: prepara o Docker, instala dependencias, sobe a stack e faz o bootstrap do banco
- `env:init`: cria `.env` a partir de `.env.example`
- `docker:install`: gera `docker/docker-compose.yml`, faz o build da imagem PHP e roda o Composer dentro do Docker
- `docker:up`: sobe a stack local
- `docker:down`: derruba a stack local
- `migrate`: aplica migrations pendentes
- `migrate:rollback`: desfaz migrations
- `seed`: executa seeders pendentes
- `entity:list`: inspeciona a metadata normalizada das entidades
- `crud:sync`: regenera `config/CrudEntities.php` a partir do schema atual
- `crud:generate`: alias de `crud:sync`
- `openapi:generate`: grava um documento OpenAPI estatico, por padrao em `build/openapi.json`
- `test`: executa a suite PHPUnit dentro do container PHP

## Fluxo tipico

```bash
./pachybase env:init
./pachybase docker:install
./pachybase docker:up
./pachybase migrate
./pachybase seed
./pachybase entity:list
./pachybase crud:sync
./pachybase openapi:generate
./pachybase test
```

## Opcoes uteis

- `env:init --force`: sobrescreve o `.env` atual
- `crud:sync --expose-new`: marca entidades novas introspectadas como expostas
- `crud:sync --output=path/to/CrudEntities.php`: grava a configuracao CRUD em outro caminho
- `openapi:generate --output=docs-site/static/openapi.json`: publica a especificacao gerada em um caminho customizado
