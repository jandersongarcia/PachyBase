---
id: examples
title: Exemplos
---

# Exemplos

Estes exemplos existem para colocar um dev novo em producao local rapidamente, sem onboarding privado.

## 1. Instalar e subir o projeto

```bash
cp .env.example .env
./pachybase install
```

## 2. Autenticar e inspecionar o usuario atual

```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"admin@pachybase.local\",\"password\":\"pachybase123\"}"
```

Pegue o `access_token` retornado e rode:

```bash
curl http://localhost:8080/api/auth/me \
  -H "Authorization: Bearer <access-token>"
```

## 3. Ler uma colecao CRUD paginada

```bash
curl "http://localhost:8080/api/system-settings?page=1&per_page=10&sort=setting_key" \
  -H "Authorization: Bearer <access-token>"
```

## 4. Filtrar uma colecao CRUD

```bash
curl "http://localhost:8080/api/system-settings?filter[is_public]=1" \
  -H "Authorization: Bearer <access-token>"
```

## 5. Criar um registro CRUD

```bash
curl -X POST http://localhost:8080/api/system-settings \
  -H "Authorization: Bearer <access-token>" \
  -H "Content-Type: application/json" \
  -d "{\"setting_key\":\"homepage.title\",\"setting_value\":\"PachyBase\",\"value_type\":\"string\",\"is_public\":true}"
```

## 6. Exportar OpenAPI e inspecionar metadata para IA

```bash
./pachybase openapi:generate --output=build/openapi.json
curl http://localhost:8080/ai/schema
curl http://localhost:8080/ai/entity/system-settings
```
