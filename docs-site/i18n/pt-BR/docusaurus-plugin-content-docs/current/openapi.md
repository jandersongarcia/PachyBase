---
id: openapi
title: OpenAPI
sidebar_position: 8
---

# OpenAPI

O PachyBase expoe um documento OpenAPI 3.0.3 gerado em `/openapi.json`.

O documento e montado a partir das mesmas fontes usadas pela API em runtime:

- rotas HTTP registradas
- exigencias de autenticacao por middleware
- entidades CRUD expostas em `config/CrudEntities.php`
- metadados reais do banco obtidos por introspeccao das tabelas
- regras de validacao ja aplicadas pela API

## O que e documentado

- endpoints fixos como `/`, `/api/auth/*` e `/openapi.json`
- endpoints CRUD automaticos expandidos por entidade exposta, como `/api/system-settings` e `/api/api-tokens`
- schemas de request e response
- envelopes padrao de erro
- requisitos de autenticacao bearer
- tags para sistema, documentacao, autenticacao e cada entidade CRUD exposta

## Por que isso importa

Como o documento OpenAPI e gerado a partir do mapa ativo de rotas e dos metadados das entidades, ele descreve a API real em vez de uma documentacao manual paralela.

Isso melhora:

- DX na exploracao da API
- geracao automatica de clientes e SDKs
- integracoes com IA e ferramentas
- visibilidade de contrato para integradores

## Escopo atual

- endpoint OpenAPI JSON: disponivel
- Swagger UI / Redoc: ainda nao incluidos

## Geracao estatica

Voce pode exportar o contrato atual para um arquivo:

```bash
./pachybase openapi:build
./pachybase openapi:build --output=docs-site/static/openapi.json
```

No Windows, use `.\pachybase.bat`.

Isso ajuda na publicacao da documentacao, na geracao de SDKs e na revisao de mudancas de contrato em CI.

Exemplo:

```bash
curl http://localhost:8080/openapi.json
```
