---
id: database-layer
title: Camada de Banco
sidebar_position: 3
---

# Camada de Banco

As Fases 2, 3 e 4 introduzem uma base de persistencia reutilizavel para o PachyBase. O projeto deixa de depender de SQL de schema espalhado ou acesso cru a PDO distribuido entre services e controllers.

## O que essa camada entrega

- `database/Connection.php`: conexao PDO central com metadados de driver, banco e schema.
- `database/Query/PdoQueryExecutor.php`: execucao segura de queries preparadas com bindings e transacoes.
- `database/Adapters/DatabaseAdapterInterface.php`: contrato comum para adapters de banco.
- `database/Adapters/MySqlAdapter.php`: adapter de schema para MySQL.
- `database/Adapters/PostgresAdapter.php`: adapter de schema para PostgreSQL.
- `database/Schema/TypeNormalizer.php`: normalizacao canonica de tipos entre engines.
- `database/Schema/SchemaInspector.php`: servico central de inspecao de schema.
- `database/Metadata/EntityIntrospector.php`: servico central de metadata de entidades construido sobre a inspecao normalizada de schema.
- `database/Metadata/EntityDefinition.php`: representacao semantica de entidade para o core.
- `database/Metadata/FieldDefinition.php`: representacao semantica de campo com metadata de obrigatoriedade, readonly, nullable e default.
- `database/Migrations/MigrationRunner.php`: orquestracao central de migrations para aplicar, inspecionar e reverter lotes.
- `database/Migrations/MigrationRepository.php`: rastreamento do historico de migrations no banco.
- `database/Migrations/FilesystemMigrationLoader.php`: descoberta de migrations em `database/migration-files/`.
- `database/Seeds/SeedRunner.php`: orquestracao central de seeds para status e execucao.
- `database/Seeds/SeedRepository.php`: rastreamento da execucao de seeds no banco.
- `database/Seeds/FilesystemSeedLoader.php`: descoberta de seeds em `database/seed-files/`.
- `database/Schema/SystemTableBlueprint.php`: convencoes compartilhadas para tabelas-base do PachyBase.

## Modelo normalizado de schema

A camada de schema expoe objetos estaveis para:

- tabelas
- colunas
- chaves primarias
- indices
- relacoes

Isso permite que CRUD automatico e automacoes futuras dependam de uma representacao interna unica, em vez de SQL especifico por banco.

## Modelo de metadata de entidades

A Fase 4 adiciona a ponte semantica entre o schema bruto e o runtime do core:

- `EntityDefinition` representa uma entidade interna com nome, tabela, schema, campo primario e lista de campos.
- `FieldDefinition` captura tipo normalizado, nullable, default, required, readonly, primary e auto incremento.
- `EntityIntrospector` aplica convencoes estaveis como:
  - remover prefixos `pb_` e `pachybase_` do nome da entidade
  - singularizar o ultimo segmento da tabela quando seguro
  - marcar chaves primarias e timestamps de sistema como readonly
  - marcar campos nao nulos e sem default como required
- `InMemoryMetadataCache` mantem o metadata aquecido durante a execucao atual.

## Migrations de banco

A camada de banco agora tambem inclui uma camada reutilizavel de migrations para as duas engines suportadas.

- Os arquivos de migration ficam em `database/migration-files/`.
- Cada migration retorna uma instancia de `PachyBase\Database\Migrations\MigrationInterface`.
- `AbstractSqlMigration` pode ser estendida para declarar SQL por driver com menos boilerplate.
- As migrations aplicadas ficam registradas na tabela `pachybase_migrations`.

O schema base atual do PachyBase padroniza estas tabelas:

- `pb_system_settings`
- `pb_api_tokens`

Exemplo:

```php
use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Migrations\AbstractSqlMigration;

return new class extends AbstractSqlMigration {
    public function version(): string
    {
        return '202603170001';
    }

    public function description(): string
    {
        return 'Criar tabela users';
    }

    protected function upStatements(DatabaseAdapterInterface $adapter): array
    {
        $table = $adapter->quoteIdentifier('users');

        return match ($adapter->driver()) {
            'mysql' => [
                "CREATE TABLE {$table} (`id` BIGINT AUTO_INCREMENT PRIMARY KEY, `email` VARCHAR(190) NOT NULL)"
            ],
            default => [
                "CREATE TABLE {$table} (\"id\" BIGSERIAL PRIMARY KEY, \"email\" VARCHAR(190) NOT NULL)"
            ],
        };
    }

    protected function downStatements(DatabaseAdapterInterface $adapter): array
    {
        return [
            'DROP TABLE IF EXISTS ' . $adapter->quoteIdentifier('users'),
        ];
    }
};
```

## Exemplo de uso

```php
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Schema\SchemaInspector;

$inspector = new SchemaInspector(AdapterFactory::make());
$database = $inspector->inspectDatabase();
$users = $database->table('users');
```

## Execucao segura de queries

Use `PdoQueryExecutor` para queries parametrizadas:

```php
use PachyBase\Database\Connection;
use PachyBase\Database\Query\PdoQueryExecutor;

$executor = new PdoQueryExecutor(Connection::getInstance()->getPDO());
$user = $executor->selectOne(
    'SELECT id, email FROM users WHERE id = :id',
    ['id' => 1]
);
```

## Inspecao via CLI

O PachyBase tambem inclui um utilitario de inspecao de schema:

```bash
docker compose -f docker/docker-compose.yml exec php php scripts/inspect-schema.php
```

A camada de metadata tambem inclui um utilitario de inspecao de entidades:

```bash
docker compose -f docker/docker-compose.yml exec php php scripts/inspect-entities.php
```

Os comandos de migration tambem estao disponiveis:

```bash
docker compose -f docker/docker-compose.yml exec php php scripts/migrate.php status
docker compose -f docker/docker-compose.yml exec php php scripts/migrate.php up
docker compose -f docker/docker-compose.yml exec php php scripts/migrate.php down
```

## Seeds e bootstrap

O fluxo local agora tambem inclui suporte minimo a seeds e um bootstrap unico do banco:

- Os arquivos de seed ficam em `database/seed-files/`.
- Cada seed retorna uma instancia de `PachyBase\Database\Seeds\SeederInterface`.
- As seeds executadas ficam registradas em `pachybase_seeders`.
- `scripts/bootstrap-database.php` espera o banco ficar pronto, aplica migrations pendentes e executa seeds pendentes.

Comandos:

```bash
docker compose -f docker/docker-compose.yml exec php php scripts/seed.php status
docker compose -f docker/docker-compose.yml exec php php scripts/seed.php run
docker compose -f docker/docker-compose.yml exec php php scripts/bootstrap-database.php
```

## Validacao

A camada de banco esta coberta por:

- testes unitarios de normalizacao de tipos
- testes unitarios dos adapters MySQL e PostgreSQL
- testes de integracao da execucao de queries
- testes de integracao da inspecao de schema no driver ativo
- testes unitarios das heuristicas de metadata de entidades
- testes de integracao da introspecao de entidades e do comportamento de cache no driver ativo
- testes unitarios da descoberta de migrations em filesystem
- testes de integracao para aplicar e reverter migrations no driver ativo
- testes unitarios da descoberta de seeds em filesystem
- testes de integracao para executar seeds no driver ativo
