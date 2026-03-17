---
id: docker-install
title: Instalacao Docker
sidebar_position: 3
---

# Instalacao Docker

O PachyBase pode provisionar sua stack Docker local diretamente pelo Docker, sem exigir Composer instalado na maquina host.

Antes de executar o instalador Docker, obtenha o codigo-fonte pelo [GitHub](https://github.com/jandersongarcia/pachybase) ou pelo [download do ZIP do projeto](https://github.com/jandersongarcia/pachybase/archive/refs/heads/main.zip).

## Etapa manual obrigatoria

Crie `.env` a partir de `.env.example` e preencha as configuracoes do banco antes de rodar o instalador. `DB_DRIVER` define se a stack gerada usara MySQL ou PostgreSQL.

```bash
cp .env.example .env
```

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

### Windows

```powershell
.\install.bat
```

### Linux

```bash
chmod +x install.sh
./install.sh
```

O instalador executa estas etapas:

1. Valida a configuracao do banco no `.env`.
2. Gera `docker/docker-compose.yml`.
3. Faz o build da imagem PHP com Composer disponivel dentro do container.
4. Executa `composer install` dentro do container PHP.
5. Sobe os containers com `docker compose up -d`.

## Observacoes de configuracao

O instalador nao cria `.env` automaticamente. Configure esse arquivo manualmente antes de rodar o setup.

```bash
docker compose -f docker/docker-compose.yml up -d
docker compose -f docker/docker-compose.yml down
```
