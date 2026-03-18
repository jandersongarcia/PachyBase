---
id: automatic-crud
title: CRUD Automatico
sidebar_position: 5
---

# CRUD Automatico

A Fase 5 transforma o metadata do PachyBase em endpoints REST utilizaveis sem obrigar o desenvolvedor a codificar cada fluxo manualmente.

## Entidades habilitadas por padrao

O runtime atual expõe CRUD automaticamente para:

- `system-settings` -> `pb_system_settings`
- `api-tokens` -> `pb_api_tokens`

Esses mapeamentos sao carregados a partir de `config/CrudEntities.php` e consumidos por `modules/Crud/CrudEntityRegistry.php`.

## Superficie de rotas

Cada entidade habilitada recebe:

- `GET /api/{entity}`
- `GET /api/{entity}/{id}`
- `POST /api/{entity}`
- `PUT /api/{entity}/{id}`
- `PATCH /api/{entity}/{id}`
- `DELETE /api/{entity}/{id}`

## Controle declarativo por entidade

A Fase 8 adiciona uma camada declarativa para que o desenvolvedor ajuste o comportamento sem editar o core inteiro do CRUD.

Cada entidade agora pode:

- expor ou ocultar a entidade
- desabilitar delete
- definir whitelist de campos gravaveis
- ocultar campos na serializacao
- definir `max_per_page` customizado
- marcar campos readonly extras
- declarar regras extras de validacao
- registrar hooks leves como `before_create`, `before_update`, `after_create`, `after_show`, `after_list_item` e `after_delete`

## Convencoes de requisicao

### Paginacao

- `page`: numero da pagina iniciado em `1`, padrao `1`
- `per_page`: tamanho da pagina, padrao `15`, maximo `100`

### Filtros

Filtros simples por igualdade usam:

```text
filter[field]=value
```

Exemplo:

```text
/api/system-settings?filter[is_public]=1
```

### Ordenacao

A ordenacao usa `sort`:

- `sort=field` para crescente
- `sort=-field` para decrescente
- `sort=field,-other_field` para varias colunas

### Busca

A busca basica usa:

```text
search=term
```

A busca roda sobre os campos configurados como pesquisaveis no registry de CRUD.

## Validacao e regras de campo

A camada de CRUD deriva as regras do metadata da entidade e combina isso com a configuracao declarativa:

- campos readonly nao podem ser escritos
- campos obrigatorios sao exigidos em create e replace
- campos fora da whitelist sao rejeitados antes da persistencia
- campos nullable aceitam `null`
- valores escalares sao normalizados por tipo quando possivel

Falhas de validacao retornam HTTP `422` com o contrato padrao `validation_error`.

## Comportamento de erro

O CRUD automatico agora mapeia os principais casos de persistencia para o contrato publico:

- `404` quando a entidade ou o registro nao existe
- `409` quando a escrita entra em conflito com restricao unica
- `422` quando payload, paginacao, filtros ou ordenacao sao invalidos
- `500` para falhas inesperadas de banco ou runtime

## Mapa da implementacao

- `config/CrudEntities.php`: comportamento declarativo das entidades
- `modules/Crud/CrudEntityRegistry.php`: carga da configuracao e lookup das entidades
- `modules/Crud/CrudModule.php`: registro automatico de rotas
- `api/Controllers/CrudController.php`: controller generico de CRUD
- `services/Crud/EntityCrudService.php`: orquestracao central do CRUD
- `services/Crud/EntityCrudValidator.php`: normalizacao e validacao de payload
- `services/Crud/EntityCrudSerializer.php`: serializacao de saida e cast de tipos

## Validacao

A camada de CRUD automatico esta coberta por:

- testes de integracao para list, show, create, replace, patch, delete, validacao, conflito e not found
- testes do kernel para despacho das rotas automaticas
- execucao da suite completa do projeto apos habilitar o novo modulo
