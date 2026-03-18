---
id: input-validation
title: Validacao de Entrada
sidebar_position: 6
---

# Validacao de Entrada

A Fase 6 transforma a validacao do PachyBase em uma camada previsivel de runtime, em vez de um conjunto solto de checagens no CRUD.

## O que essa camada entrega

- `services/Crud/EntityCrudValidator.php`: validador central para create, replace e patch
- regras de required e nullable baseadas em metadata
- sobrescritas de regra por campo vindas de `modules/Crud/CrudEntityRegistry.php`
- respostas `422` estruturadas pelo contrato publico da API

## Fontes das regras

A validacao agora combina duas fontes:

- metadata de entidade em `database/Metadata/FieldDefinition.php`
- regras explicitas por campo no registry de CRUD

Com isso, o validador consegue aplicar:

- protecao de campos readonly
- required vs nullable
- validacao por tipo
- limites de tamanho para strings
- min/max numerico
- listas enum
- formatos de email, URL e UUID

## Chaves de regra suportadas

As regras por campo atualmente incluem:

- `min`
- `max`
- `enum`
- `email`
- `url`
- `uuid`
- `required`
- `required_on_create`
- `required_on_replace`
- `required_on_patch`

## Comportamento por operacao

- `create`: valida campos gravaveis e exige campos obrigatorios
- `replace`: valida campos gravaveis e exige os campos do replace completo
- `patch`: valida apenas os campos enviados, salvo quando um campo usa `required_on_patch`

## Formato de erro

Payloads invalidos falham com HTTP `422` e detalhes no formato:

```json
{
  "field": "setting_key",
  "code": "min",
  "message": "The field length must be at least 3 characters."
}
```

## Exemplo de declaracao

```php
new CrudEntity(
    'system-settings',
    'pb_system_settings',
    validationRules: [
        'setting_key' => ['min' => 3, 'max' => 120],
        'value_type' => ['enum' => ['string', 'text', 'integer', 'float', 'boolean', 'json']],
    ]
);
```

## Cobertura de validacao

A camada de validacao esta coberta por:

- testes unitarios de tipos e comportamento por operacao
- testes de integracao do CRUD para min/max, enum, email, URL, UUID, conflitos e replace vs patch
- testes do kernel HTTP para respostas `422` estruturadas
