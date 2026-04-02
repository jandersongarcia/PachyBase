---
id: install
title: Instalacao
sidebar_position: 2
---

# Instalacao

O PachyBase agora tem duas trilhas oficiais de instalacao:

- Instalacao com Docker: trilha principal e mais rapida para a maioria dos times
- Instalacao local: trilha manual oficial para times que querem rodar PHP, Composer e banco direto na maquina host

Escolha a trilha que combina com o seu ambiente. As duas funcionam em Windows e Linux.

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

## Guia rapido de decisao

Escolha [Instalacao com Docker](./docker-install.md) se voce quer:

- o setup suportado mais rapido
- o fluxo padrao de onboarding
- usar os comandos do projeto via `./pachybase` ou `.\pachybase.bat`
- provisioning automatico do banco

Escolha [Instalacao local](./local-install.md) se voce quer:

- PHP, Composer e banco gerenciados direto na maquina host
- desenvolvimento sem dependencia de Docker
- controle direto sobre runtime e servicos de banco mantendo a mesma CLI do projeto

## Trilha 1: Instalacao com Docker

Essa e a trilha principal e o melhor ponto de partida.

```bash
cp .env.example .env
./pachybase install
./pachybase doctor
```

No Windows, troque `./pachybase` por `.\pachybase.bat`.

Leia o guia completo: [Instalacao com Docker](./docker-install.md)

## Trilha 2: Instalacao local

Essa e a trilha oficial alternativa para times que nao querem o runtime dentro do Docker.

Fluxo tipico:

```bash
cp .env.example .env
composer install
# ajuste APP_RUNTIME=local e os valores de DB_*
./pachybase install
./pachybase status
```

Os comandos PHP diretos continuam disponiveis quando voce quiser operar etapa por etapa:

```bash
cp .env.example .env
composer install
php scripts/bootstrap-database.php
php -S 127.0.0.1:8080 -t public public/router.php
```

Leia o guia completo: [Instalacao local](./local-install.md)

## Pronto para release

Antes de compartilhar o ambiente com outros devs ou publicar uma release, rode as verificacoes da trilha escolhida:

- Trilha Docker: `./pachybase doctor`
- Trilha local: `./pachybase doctor` (ou `php scripts/doctor.php` se voce quiser o entrypoint PHP direto)
