---
sidebar_position: 8
---

# OpenAPI automático

O PachyBase agora expõe um documento OpenAPI 3.0.3 gerado em `/openapi.json`.

O documento é montado a partir das mesmas fontes usadas pela API em runtime:

- rotas HTTP registradas
- exigências de autenticação por middleware
- entidades CRUD expostas em `config/CrudEntities.php`
- metadados reais do banco obtidos por introspecção das tabelas
- regras de validação já aplicadas pela API

## O que é documentado

- endpoints fixos como `/`, `/api/auth/*` e `/openapi.json`
- endpoints CRUD automáticos expandidos por entidade exposta, como `/api/system-settings` e `/api/api-tokens`
- schemas de request e response
- envelopes padrão de erro
- requisitos de autenticação bearer
- tags para sistema, documentação, autenticação e cada entidade CRUD exposta

## Por que isso importa

Como o documento OpenAPI é gerado a partir do mapa ativo de rotas e dos metadados das entidades, ele descreve a API real em vez de uma documentação manual paralela.

Isso melhora:

- DX na exploração da API
- geração automática de clientes e SDKs
- integrações com IA e ferramentas
- visibilidade de contrato para integradores

## Escopo atual

- endpoint OpenAPI JSON: disponível
- Swagger UI / Redoc: ainda não incluídos

Exemplo:

```bash
curl http://localhost:8080/openapi.json
```
