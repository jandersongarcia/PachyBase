---
id: docker-install
title: Instalação Docker
sidebar_position: 3
---

# Instalação Docker

O PachyBase pode provisionar sua stack Docker local diretamente pelo Composer.

## Valores obrigatórios no `.env`

```env
DB_DRIVER=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=pachybase
DB_USERNAME=root
DB_PASSWORD=root
```

Drivers de banco suportados:

- `mysql`
- `pgsql`

## Fluxo de instalação

```bash
composer install
composer docker-install
```

O instalador executa estas etapas:

1. Valida a configuração do banco no `.env`.
2. Gera `docker/docker-compose.yml`.
3. Configura o container do banco conforme o driver selecionado.
4. Sobe os containers com `docker compose up -d`.

## Dry run

Use o modo dry-run para validar a configuração sem subir containers:

```bash
composer docker-install -- --dry-run
```
