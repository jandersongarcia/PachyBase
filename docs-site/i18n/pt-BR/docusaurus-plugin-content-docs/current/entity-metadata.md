---
id: entity-metadata
title: Metadata de Entidades
sidebar_position: 4
---

# Metadata de Entidades

A Fase 4 introduz a ponte entre o schema bruto do banco e o metadata interno usado pelo core do PachyBase.

## O que essa camada entrega

- `database/Metadata/EntityDefinition.php`: representacao canonica de entidade para o core.
- `database/Metadata/FieldDefinition.php`: representacao canonica de campo com flags semanticas.
- `database/Metadata/EntityIntrospector.php`: servico central que transforma `TableSchema` em `EntityDefinition`.
- `database/Metadata/InMemoryMetadataCache.php`: cache em memoria por execucao para evitar remontar o mesmo metadata repetidamente.

## O que passa a ser identificado

Para cada tabela suportada, o PachyBase agora resolve:

- nome da entidade
- tabela de origem
- schema de origem
- campo primario
- campos obrigatorios
- campos readonly
- tipos normalizados
- campos nullable
- valores default

## Heuristicas de metadata

As regras atuais foram pensadas para serem previsiveis:

- nomes de tabela sao normalizados em nomes de entidade com remocao de prefixos estaveis como `pb_` e `pachybase_`
- o ultimo segmento da tabela e singularizado quando seguro, entao `pb_system_settings` vira `system_setting`
- chaves primarias sao marcadas como readonly
- campos auto incremento sao marcados como readonly
- timestamps de sistema como `created_at`, `updated_at` e `deleted_at` sao marcados como readonly
- campos obrigatorios sao os campos nao nulos, sem default e que nao sao readonly

## Exemplo de uso

```php
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Metadata\EntityIntrospector;
use PachyBase\Database\Schema\SchemaInspector;

$introspector = new EntityIntrospector(new SchemaInspector(AdapterFactory::make()));
$entity = $introspector->inspectTable('pb_system_settings');

print_r($entity->toArray());
```

## Inspecao via CLI

Voce pode inspecionar todas as entidades visiveis no driver ativo com:

```bash
docker compose -f docker/docker-compose.yml exec php php scripts/inspect-entities.php
```

Esse comando gera o metadata normalizado em JSON e ajuda a validar o trabalho das proximas fases de CRUD automatico.

## Validacao

A camada de metadata da Fase 4 esta coberta por:

- testes unitarios das heuristicas de mapeamento de entidade e campo
- testes de integracao no driver de banco ativo
- validacao do comportamento de cache dentro da mesma execucao
