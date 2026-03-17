---
id: intro
title: Visao Geral
slug: /
sidebar_position: 1
---

# PachyBase

PachyBase e uma base backend self-hosted em PHP focada em APIs JSON previsiveis, setup local com Docker e contratos legiveis por maquina, seguros para humanos e para IA consumirem.

## O que um dev novo consegue fazer hoje

- Instalar a stack com Docker e uma unica CLI
- Configurar a aplicacao via `.env` e arquivos PHP declarativos
- Expor CRUD automatico para entidades habilitadas sem escrever controller por recurso
- Autenticar com JWT ou API tokens
- Publicar OpenAPI e documentos de schema para IA a partir do runtime real
- Rodar testes, inspecionar entidades, sincronizar CRUD e fazer bootstrap do banco pela CLI

## Principios atuais

- Respostas previsiveis com um contrato externo fixo
- Desenvolvimento local simples com Docker
- Pontos claros de extensao para rotas, modulos e geracao de CRUD
- Documentacao que pode ser consumida em ingles e portugues do Brasil

## Inicio rapido

```bash
cp .env.example .env
./pachybase install
```

No Windows, use `Copy-Item .env.example .env` e `.\pachybase.bat install`.

Depois que a stack subir:

- Base da API: `http://localhost:8080`
- Documento OpenAPI: `http://localhost:8080/openapi.json`
- Schema para IA: `http://localhost:8080/ai/schema`
- Admin de desenvolvimento: `admin@pachybase.local` / `pachybase123`

## Mapa da documentacao

### Produto e setup

- [Instalacao](./install.md)
- [Configuracao](./configuration.md)
- [Bancos suportados](./supported-databases.md)
- [Arquitetura](./architecture.md)

### API e integracoes

- [Contrato da API](./api-contract.md)
- [Autenticacao e autorizacao](./auth-security.md)
- [CRUD automatico](./automatic-crud.md)
- [Filtros e paginacao](./filters-pagination.md)
- [OpenAPI](./openapi.md)
- [Endpoints para IA](./ai-endpoints.md)

### Operacao e manutencao

- [CLI](./cli.md)
- [Testes](./testing.md)
- [Instalacao Docker](./docker-install.md)
- [Exemplos](./examples.md)
- [Contribuicoes](./contributing.md)
- [Roadmap](./roadmap.md)

## Rodando o site de documentacao localmente

```bash
npm install
npm run start
```

Por padrao, a documentacao abre em ingles. Use o seletor de idioma na navegacao superior para alternar para `pt-BR`.
