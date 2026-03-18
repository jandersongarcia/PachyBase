---
id: filters-pagination
title: Filtros e paginacao
---

# Filtros e paginacao

As colecoes de CRUD automatico expoem uma interface consistente para paginacao, filtros, ordenacao e busca.

## Parametros de paginacao

- `page`: numero da pagina, comecando em `1`
- `per_page`: tamanho da pagina, padrao `15`

Cada entidade pode reduzir o limite maximo em `config/CrudEntities.php`. Quando `per_page` ultrapassa o limite da entidade, o PachyBase retorna `422` com erro estruturado.

## Resposta de paginacao

Os resultados paginados colocam os metadados em `meta.pagination`:

```json
{
  "meta": {
    "pagination": {
      "total": 25,
      "per_page": 10,
      "current_page": 2,
      "last_page": 3,
      "from": 11,
      "to": 20
    }
  }
}
```

## Filtros por campo

Use filtros de igualdade com `filter[field]=value`:

```text
/api/system-settings?filter[is_public]=1
```

Os campos permitidos para filtro vem da configuracao da entidade e podem ser consultados via OpenAPI e `/ai/entity/{name}`.

## Ordenacao

Use `sort` com um ou mais campos separados por virgula:

- `sort=setting_key`
- `sort=-created_at`
- `sort=setting_key,-id`

O prefixo `-` indica ordem decrescente.

## Busca

Use `search=term` para buscar nos campos pesquisaveis da entidade:

```text
/api/system-settings?search=app
```

Os campos pesquisaveis sao definidos em `config/CrudEntities.php`.

## Exemplos

```bash
curl "http://localhost:8080/api/system-settings?page=2&per_page=10"
curl "http://localhost:8080/api/system-settings?filter[is_public]=1&sort=setting_key"
curl "http://localhost:8080/api/system-settings?search=app"
```

## Comportamento de erro

Paginacao, filtros ou ordenacao invalidos retornam o contrato padrao com:

- HTTP `422`
- `error.type = "validation_error"`
- `error.details` estruturado
