---
id: testing
title: Testing
sidebar_position: 4
---

# Testing

A Fase 12 estabelece uma suite automatizada focada em regressao sobre o runtime central, o contrato HTTP, a infraestrutura de banco, as superficies machine-readable e a CLI do projeto.

## Como rodar a suite

### Comando preferencial

```bash
./pachybase test
```

No Windows, use:

```powershell
.\pachybase.bat test
```

### Execucao direta via Docker

```bash
docker compose -f docker/docker-compose.yml run --rm php php vendor/bin/phpunit --testdox
```

## Matriz de cobertura

- Core HTTP e roteamento: `tests/Http/RequestTest.php`, `tests/Http/ApiResponseTest.php`, `tests/Http/ErrorHandlerTest.php`, `tests/Http/RouterTest.php` e `tests/Api/HttpKernelTest.php`
- Roteador e registro de modulos pelo kernel real: `tests/Api/HttpKernelTest.php`, `tests/Api/CrudHttpKernelTest.php`, `tests/Api/AuthHttpKernelTest.php`, `tests/Api/AiHttpKernelTest.php` e `tests/Api/OpenApiHttpKernelTest.php`
- Comportamento de request e response: `tests/Http/RequestTest.php` e `tests/Http/ApiResponseTest.php`
- Tratamento de erro e falhas aderentes ao contrato: `tests/Http/ErrorHandlerTest.php`, `tests/Auth/RequireBearerTokenTest.php` e os cenarios protegidos em `tests/Api/CrudHttpKernelTest.php`
- Adapters de banco e execucao de queries: `tests/Database/AdapterFactoryTest.php`, `tests/Database/Adapters/MySqlAdapterTest.php`, `tests/Database/Adapters/PostgresAdapterTest.php` e `tests/Database/QueryExecutorIntegrationTest.php`
- Migrations, seeds e fluxos sensiveis de persistencia: `tests/Database/FilesystemMigrationLoaderTest.php`, `tests/Database/MigrationRunnerIntegrationTest.php`, `tests/Database/FilesystemSeedLoaderTest.php` e `tests/Database/SeedRunnerIntegrationTest.php`
- Inspecao de schema e introspeccao de entidades: `tests/Database/SchemaInspectorIntegrationTest.php`, `tests/Database/EntityIntrospectorTest.php` e `tests/Database/EntityIntrospectorIntegrationTest.php`
- CRUD automatico e validacao declarativa: `tests/Modules/Crud/CrudEntityRegistryTest.php`, `tests/Services/Crud/EntityCrudServiceIntegrationTest.php`, `tests/Services/Crud/EntityCrudDeclarativeConfigIntegrationTest.php`, `tests/Services/Crud/EntityCrudValidatorTest.php` e `tests/Api/CrudHttpKernelTest.php`
- Autenticacao e autorizacao: `tests/Auth/JwtCodecTest.php`, `tests/Auth/AuthServiceIntegrationTest.php`, `tests/Auth/AuthorizationServiceTest.php`, `tests/Auth/RequireBearerTokenTest.php` e `tests/Api/AuthHttpKernelTest.php`
- Endpoints AI-friendly: `tests/Services/Ai/AiSchemaServiceIntegrationTest.php` e `tests/Api/AiHttpKernelTest.php`
- Geracao e publicacao de OpenAPI: `tests/Services/OpenApi/OpenApiDocumentBuilderTest.php`, `tests/Api/OpenApiHttpKernelTest.php` e `tests/Scripts/OpenApiGenerateTest.php`
- CLI e tooling do desenvolvedor: `tests/Cli/PachybaseCliTest.php`, `tests/Scripts/CrudSyncTest.php` e `tests/Scripts/DockerInstallTest.php`
- Superficie de integracao com Docker, quando aplicavel: `tests/Scripts/DockerInstallTest.php` mais o caminho containerizado de PHPUnit usado por `pachybase test`

## Politica minima de regressao

- Toda superficie central do runtime deve ter ao menos um teste unitario ou de integracao protegendo o fluxo feliz
- Autenticacao, autorizacao, validacao e falhas de transporte devem ter pelo menos uma assercao explicita de erro
- Novas entidades CRUD devem ser protegidas na camada de servico e, quando expostas publicamente, por ao menos um teste HTTP
- Novos comandos da CLI devem validar o mapeamento do comando e o comando containerizado gerado

## Expectativa atual

O criterio de conclusao da Fase 12 e manter uma rede minima de seguranca automatizada sobre todas as features centrais antes que as proximas fases ampliem o comportamento.
