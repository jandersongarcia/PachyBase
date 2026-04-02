---
id: docker-install
title: Instalacao com Docker
sidebar_position: 3
---

# Instalacao com Docker

Docker e a trilha principal de instalacao do PachyBase. Ela provisiona a stack local sem exigir Composer instalado na maquina host.

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
DB_USERNAME=pachybase
DB_PASSWORD=change_this_password
```

Drivers de banco suportados:

- `mysql`
- `pgsql`

## Fluxo de instalacao recomendado

### Windows

```powershell
Copy-Item .env.example .env
.\pachybase.bat install
```

### Linux

```bash
cp .env.example .env
chmod +x pachybase
./pachybase install
```

O fluxo `install` da CLI executa estas etapas:

1. Sincroniza `.env` a partir de `.env.example` quando necessario e valida a configuracao final.
2. Configura `APP_KEY` e defaults de auth quando eles ainda nao existem.
3. Gera `docker/docker-compose.yml`.
4. Sobe o runtime Docker.
5. Aguarda o banco, aplica migrations e executa seeds.
6. Gera `build/openapi.json` e `build/ai-schema.json`.

O Compose gerado tambem publica a porta do banco no host (`3306` para MySQL ou `5432` para PostgreSQL). O container da aplicacao continua usando `DB_HOST=db`, enquanto clientes externos de banco devem usar o IP ou DNS da maquina junto com `DB_PORT`.

## Wrappers legados de setup

`install.sh` e `scripts/setup.ps1` continuam disponiveis quando voce quer chamar os wrappers de setup Docker diretamente. Eles ainda fazem o build da imagem PHP, instalam dependencias Composer no container, geram `docker/docker-compose.yml`, sobem a stack e fazem bootstrap do banco, mas a CLI continua sendo o entrypoint canonico da documentacao.

## Por que essa e a trilha principal

- E o setup suportado mais rapido.
- A CLI do projeto foi desenhada em torno dessa trilha.
- Ela mantem PHP, Composer e banco alinhados com o ambiente documentado.
- Ela reduz diferencas entre maquinas de contribuidores.

## Observacoes de configuracao

O instalador nao cria `.env` automaticamente. Configure esse arquivo manualmente antes de rodar o setup.

```bash
./pachybase status
./pachybase docker:logs
./pachybase stop
./pachybase start
```

```bash
docker compose -f docker/docker-compose.yml up -d
docker compose -f docker/docker-compose.yml down
```

Se necessario, voce tambem pode rodar novamente o bootstrap do banco manualmente:

```bash
docker compose -f docker/docker-compose.yml exec php php scripts/bootstrap-database.php
```

Antes de compartilhar o ambiente com outros devs ou publicar uma release, rode `./pachybase doctor`.
