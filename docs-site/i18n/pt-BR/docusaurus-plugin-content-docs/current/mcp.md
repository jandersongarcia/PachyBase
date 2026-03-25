---
id: mcp
title: Adaptador MCP
sidebar_position: 11
---

# Adaptador MCP

O PachyBase agora inclui um adaptador MCP via stdio para que Claude, Codex e outros clientes compatíveis possam falar com o backend vivo por ferramentas em vez de prompts HTTP crus.

## Iniciar o servidor

Use o CLI do projeto:

```bash
./pachybase mcp:serve
```

Por padrão o adaptador usa:

- `APP_URL` do `.env` como base URL do PachyBase
- `PACHYBASE_MCP_TOKEN` como bearer token para ferramentas CRUD protegidas

Você pode sobrescrever a base URL na inicialização:

```bash
./pachybase mcp:serve --base-url=http://localhost:8080
```

## Ambiente recomendado

Crie antes um token de integração com escopo explícito:

```bash
./pachybase auth:token:create "Claude MCP" \
  --scope=crud:read \
  --scope=crud:create \
  --scope=crud:update \
  --scope=entity:system-settings:read \
  --scope=entity:system-settings:update
```

Depois exporte o token antes de subir o adaptador:

```bash
export PACHYBASE_MCP_TOKEN="<integration-token>"
./pachybase mcp:serve
```

No Windows PowerShell:

```powershell
$env:PACHYBASE_MCP_TOKEN="<integration-token>"
.\pachybase.bat mcp:serve
```

## Ferramentas expostas

- `pachybase_get_schema`
- `pachybase_list_entities`
- `pachybase_describe_entity`
- `pachybase_list_records`
- `pachybase_get_record`
- `pachybase_create_record`
- `pachybase_replace_record`
- `pachybase_update_record`
- `pachybase_delete_record`

As ferramentas CRUD chamam os mesmos endpoints HTTP documentados pelo OpenAPI e por `/ai/schema`, então herdam a mesma validação, auth, auditoria e rate limit do backend vivo.

## Notas de integração

- O adaptador fala JSON-RPC sobre stdio e declara a capability MCP de `tools`.
- As ferramentas de descoberta podem funcionar sem bearer token se os endpoints de documentação estiverem públicos.
- Ferramentas CRUD protegidas devem ser usadas com um token escopado em `PACHYBASE_MCP_TOKEN`.
- Como o adaptador embrulha a API HTTP real, ele funciona melhor apontando para um runtime do PachyBase em execução.
