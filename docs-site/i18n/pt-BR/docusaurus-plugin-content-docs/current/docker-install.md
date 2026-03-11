---
id: docker-install
title: Instalacao Docker
sidebar_position: 3
---

# Instalacao Docker

O PachyBase pode provisionar sua stack Docker local diretamente pelo Composer.

Antes de executar o instalador Docker, obtenha o codigo-fonte pelo [GitHub](https://github.com/jandersongarcia/pachybase) ou pelo [download do ZIP do projeto](https://github.com/jandersongarcia/pachybase/archive/refs/heads/main.zip).

## Valores obrigatorios no `.env`

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

## Fluxo de instalacao

```bash
composer install
composer docker-install
```

O instalador executa estas etapas:

1. Valida a configuracao do banco no `.env`.
2. Gera `docker/docker-compose.yml`.
3. Configura o container do banco conforme o driver selecionado.
4. Sobe os containers com `docker compose up -d`.

## Dry run

Use o modo dry-run para validar a configuracao sem subir containers:

```bash
composer docker-install -- --dry-run
```
