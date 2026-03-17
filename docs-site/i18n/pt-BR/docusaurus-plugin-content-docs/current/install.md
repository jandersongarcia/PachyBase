---
id: install
title: Instalacao
sidebar_position: 2
---

# Instalacao

O PachyBase pode ser instalado no Windows e no Linux usando apenas Docker e Docker Compose. O Composer roda dentro do container PHP durante o setup.

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

## Etapa manual obrigatoria

Antes de rodar o instalador, crie `.env` a partir de `.env.example` e preencha as configuracoes do banco. Essa etapa e obrigatoria porque `DB_DRIVER` define qual container de banco sera gerado durante o setup.

```bash
cp .env.example .env
```

## Windows

Depois de configurar o `.env`, rode isto no PowerShell ou Prompt de Comando, na raiz do projeto:

```powershell
.\install.bat
```

## Linux

Depois de configurar o `.env`, rode isto no terminal, na raiz do projeto:

```bash
chmod +x install.sh
./install.sh
```

## Proximo passo

Os instaladores executam o mesmo fluxo:

1. Leem a configuracao do banco no `.env`.
2. Geram `docker/docker-compose.yml` com base na configuracao do banco.
3. Fazem o build da imagem PHP com Composer disponivel dentro do Docker.
4. Executam `composer install` dentro do container PHP.
5. Sobem os containers.
6. Esperam o banco ficar pronto.
7. Executam automaticamente as migrations e seeds padrao.

Depois que o instalador termina, o ambiente local ja inclui:

- a tabela de controle de migrations
- a tabela de controle de seeds
- as tabelas-base do sistema
- os dados iniciais padrao

Para reconstruir o ambiente local completo sem trabalho manual no banco:

```bash
docker compose -f docker/docker-compose.yml down -v
./install.sh
```

Depois que o codigo estiver localmente disponivel, siga para [Instalacao Docker](./docker-install.md).
