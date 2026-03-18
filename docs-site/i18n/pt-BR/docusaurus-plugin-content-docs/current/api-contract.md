---
id: api-contract
title: Contrato da API
sidebar_position: 2
---

# Contrato da API

As respostas do PachyBase sao previsiveis, estruturadas e sem ambiguidade para desenvolvedores, SDKs, frontends e agentes de IA. Este documento e a especificacao oficial do contrato publico da API.

## Envelope de resposta

Toda resposta deve expor o mesmo envelope:

- `success`: boolean obrigatorio.
- `data`: valor obrigatorio. Em falha, deve ser `null`.
- `meta`: objeto obrigatorio com metadados da requisicao.
- `error`: `null` em sucesso e objeto estruturado em falha.

## Resposta de sucesso

```json
{
  "success": true,
  "data": {},
  "meta": {
    "contract_version": "1.0",
    "request_id": "b0bb2f930d4b4f5ab9e2d1f7b74b9df6",
    "timestamp": "2026-03-11T03:00:00+00:00",
    "path": "/",
    "method": "GET"
  },
  "error": null
}
```

## Resposta de erro

```json
{
  "success": false,
  "data": null,
  "meta": {
    "contract_version": "1.0",
    "request_id": "b0bb2f930d4b4f5ab9e2d1f7b74b9df6",
    "timestamp": "2026-03-11T03:00:00+00:00",
    "path": "/users",
    "method": "POST"
  },
  "error": {
    "code": "VALIDATION_ERROR",
    "type": "validation_error",
    "message": "The request payload is invalid.",
    "details": [
      {
        "field": "email",
        "code": "required",
        "message": "The email field is required."
      }
    ]
  }
}
```

## Metadados obrigatorios

Todo objeto `meta` deve incluir:

- `contract_version`
- `request_id`
- `timestamp`
- `method`
- `path`

## Convencao de paginacao

Colecoes paginadas devem expor os dados em `meta.pagination`:

```json
{
  "pagination": {
    "total": 25,
    "per_page": 10,
    "current_page": 2,
    "last_page": 3,
    "from": 11,
    "to": 20
  }
}
```

## Convencao de erros

Erros estruturados devem expor:

- `error.code`: identificador estavel para maquina.
- `error.type`: um de `validation_error`, `authentication_error`, `authorization_error`, `application_error` ou `server_error`.
- `error.message`: resumo legivel da falha.
- `error.details`: lista de objetos estruturados. Use lista vazia quando nao houver detalhes adicionais.

## Convencao de validacao

Falhas de validacao devem usar HTTP `422`.

- `error.code`: `VALIDATION_ERROR`, exceto quando houver um codigo mais especifico documentado.
- `error.type`: `validation_error`.
- `error.message`: `The request payload is invalid.` por padrao.
- `error.details`: lista de objetos por campo com `field`, `code` e `message`.

## Convencao de autenticacao e autorizacao

Falhas de autenticacao devem usar HTTP `401` quando a credencial estiver ausente, invalida ou expirada.

- `error.type`: `authentication_error`
- Codigo padrao recomendado: `AUTHENTICATION_REQUIRED`

Falhas de autorizacao devem usar HTTP `403` quando o cliente estiver autenticado, mas sem permissao.

- `error.type`: `authorization_error`
- Codigo padrao recomendado: `INSUFFICIENT_PERMISSIONS`

## Regras de blindagem

- `success` e sempre um boolean.
- `data` esta sempre presente, mesmo quando o valor e `null`.
- `meta` esta sempre presente e contem metadados da requisicao.
- `error` e sempre `null` em sucesso e sempre um objeto em falha.
- A API nunca deve alternar entre JSON, HTML e texto puro conforme o tipo de erro.
- Endpoints novos devem responder via `core/Http/ApiResponse.php`.
- Todo novo endpoint deve ganhar teste de contrato.
- Camadas de runtime fora de `core/Http/ApiResponse.php` nao podem emitir saida HTTP crua diretamente.

## Implementacao atual

- `core/Http/ApiResponse.php` formata respostas de sucesso e falha.
- `core/Http/ErrorHandler.php` converte excecoes e erros PHP para o mesmo contrato.
- `public/index.php` delega o tratamento da requisicao para o bootstrap e para o kernel modular.
- `routes/api.php` centraliza o registro das rotas HTTP.
- `api/Controllers/` e `services/` separam a orquestracao do endpoint da logica de negocio.
- `tests/Architecture/ApiContractEnforcementTest.php` bloqueia logica manual de resposta nas camadas de runtime.
