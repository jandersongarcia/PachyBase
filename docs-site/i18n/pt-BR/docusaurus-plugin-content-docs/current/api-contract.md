---
id: api-contract
title: Contrato da API
sidebar_position: 2
---

# Contrato da API

As respostas do PachyBase foram desenhadas para serem previsíveis, estruturadas e sem ambiguidade, tanto para desenvolvedores quanto para agentes de IA.

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
    "type": "application_error",
    "message": "The request payload is invalid.",
    "details": []
  }
}
```

## Regras do contrato

- `success` é sempre um boolean.
- `data` está sempre presente, mesmo quando o valor é `null`.
- `meta` está sempre presente e contém metadados da requisição.
- `error` é sempre `null` em sucesso e sempre um objeto em falha.
- A API nunca deve alternar entre JSON, HTML e texto puro conforme o tipo de erro.

## Implementação atual

- `core/Http/ApiResponse.php` formata respostas de sucesso e falha.
- `core/Http/ErrorHandler.php` converte exceções e erros PHP para o mesmo contrato.
- `public/index.php` já usa essa camada de resposta compartilhada.
