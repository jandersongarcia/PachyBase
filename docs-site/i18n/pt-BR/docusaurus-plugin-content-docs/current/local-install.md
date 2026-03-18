---
id: local-install
title: Instalacao local
sidebar_position: 4
---

# Instalacao local

Instalacao local e a alternativa oficial ao setup docker-first. Escolha essa trilha quando voce quiser rodar PHP, Composer e banco diretamente na maquina host.

## O que essa trilha assume

Voce mesmo fornece e gerencia estas dependencias:

- PHP 8.2 ou mais recente
- Composer 2
- MySQL 8 ou PostgreSQL 15
- As extensoes PHP usadas pelo runtime do projeto: `pdo_mysql`, `pdo_pgsql`, `mbstring`, `exif`, `pcntl`, `bcmath` e `gd`

## Repositorio

- GitHub: [jandersongarcia/pachybase](https://github.com/jandersongarcia/pachybase)
- Download ZIP: [main.zip](https://github.com/jandersongarcia/pachybase/archive/refs/heads/main.zip)

## Opcao com clone

```bash
git clone https://github.com/jandersongarcia/pachybase.git
cd pachybase
```

## Opcao com ZIP

1. Baixe o arquivo [main.zip](https://github.com/jandersongarcia/pachybase/archive/refs/heads/main.zip).
2. Extraia os arquivos do projeto.
3. Abra a pasta extraida.

## 1. Criar o `.env`

Crie `.env` a partir de `.env.example`, defina `APP_RUNTIME=local` e aponte para o banco gerenciado na host.

```bash
cp .env.example .env
```

Exemplo para MySQL local:

```env
APP_RUNTIME=local
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pachybase
DB_USERNAME=root
DB_PASSWORD=change_this_password
```

Exemplo para PostgreSQL local:

```env
APP_RUNTIME=local
DB_DRIVER=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pachybase
DB_USERNAME=postgres
DB_PASSWORD=change_this_password
```

## 2. Instalar dependencias PHP

```bash
composer install
```

## 3. Instalar e subir o runtime local

Use a CLI do projeto depois que as dependencias Composer estiverem presentes:

```bash
./pachybase install
```

Esse fluxo local da CLI:

- valida o `.env`
- configura `APP_KEY` e `AUTH_JWT_SECRET` quando estiverem ausentes
- aplica as migrations e seeds padrao
- gera `build/openapi.json` e `build/ai-schema.json`
- sobe o servidor embutido do PHP via `public/router.php`

## 4. Verificar o runtime

```bash
./pachybase status
```

O runtime local grava PID e log em `.pachybase/runtime/`.

Depois que o servidor subir, as URLs padrao sao:

- Base da API: `http://127.0.0.1:8080`
- Documento OpenAPI: `http://127.0.0.1:8080/openapi.json`
- Schema para IA: `http://127.0.0.1:8080/ai/schema`
- Admin de desenvolvimento: `admin@pachybase.local` / `pachybase123`

## Windows

No PowerShell:

```powershell
Copy-Item .env.example .env
composer install
# ajuste APP_RUNTIME=local e os valores de DB_*
.\pachybase.bat install
.\pachybase.bat status
```

Os comandos diretos na host tambem continuam disponiveis:

```powershell
Copy-Item .env.example .env
composer install
php scripts/bootstrap-database.php
php -S 127.0.0.1:8080 -t public public/router.php
```

## Linux

No terminal:

```bash
cp .env.example .env
composer install
# ajuste APP_RUNTIME=local e os valores de DB_*
./pachybase install
./pachybase status
```

Os comandos diretos na host tambem continuam disponiveis:

```bash
cp .env.example .env
composer install
php scripts/bootstrap-database.php
php -S 127.0.0.1:8080 -t public public/router.php
```

## Observacoes operacionais

- `./pachybase start`, `stop`, `status`, `doctor` e `test` funcionam em modo local quando `APP_RUNTIME=local`.
- Comandos PHP diretos como `php scripts/migrate.php up` e `php scripts/seed.php run` continuam disponiveis para manutencao etapa por etapa.
- Rode `./pachybase doctor` antes de compartilhar o ambiente ou publicar uma release candidate.
- Rode os testes localmente com `./pachybase test` ou `vendor/bin/phpunit --testdox`.
