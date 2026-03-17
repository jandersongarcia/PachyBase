---
id: ai-endpoints
title: Endpoints para IA
---

# Endpoints para IA

O PachyBase expoe endpoints de descoberta orientados a maquina para que geradores de frontend, agentes e automacoes entendam o contrato real do backend sem precisar inferir pelo codigo.

## Superficie de endpoints

- `GET /ai/schema`: documento completo de schema orientado a maquina
- `GET /ai/entities`: lista compacta das entidades expostas
- `GET /ai/entity/{name}`: contrato de uma entidade exposta

## O que os documentos incluem

- entidades CRUD expostas e seus caminhos de colecao/item
- metadata de campo como tipo, nullable, readonly, writable e default
- capacidades de paginacao, filtro, ordenacao e busca
- operacoes CRUD disponiveis e scopes requeridos
- metadata de compatibilidade com OpenAPI e referencias de components

## Relacao com OpenAPI

Os endpoints de IA nao substituem o OpenAPI. Eles complementam o documento transport-level com semantica mais conveniente para geradores e fluxos com LLM:

- nomes de entidade estaveis
- indicacao de campos writable vs readonly
- filtros e paginacao como capacidades estruturadas
- expectativa de scopes por operacao CRUD

## Exemplos de requisicao

```bash
curl http://localhost:8080/ai/schema
curl http://localhost:8080/ai/entities
curl http://localhost:8080/ai/entity/system-settings
```

## Quando usar cada documento

- Use `/openapi.json` para geracao de clientes e schemas de transporte
- Use `/ai/schema` quando uma ferramenta precisa de um mapa compacto do produto
- Use `/ai/entity/{name}` quando o fluxo precisa do contrato de uma unica entidade
