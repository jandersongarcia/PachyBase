---
id: testing
title: Testing
sidebar_position: 4
---

# Testing

The current regression suite protects the core runtime, the HTTP contract, the database infrastructure, the machine-readable surfaces, and the project CLI.

## Run the suite

### Preferred command

```bash
./pachybase test
```

On Windows, use:

```powershell
.\pachybase.bat test
```

### Direct Docker execution

```bash
docker compose -f docker/docker-compose.yml run --rm php vendor/bin/phpunit --testdox
```

### Local installation execution

If you are using the host-managed runtime instead of Docker:

```bash
vendor/bin/phpunit --testdox
```

## Release readiness

Run the release check alongside the test suite:

```bash
./pachybase doctor
./pachybase env:validate
./pachybase status
```

For local installation, run:

```bash
php scripts/doctor.php
```

These commands validate production-sensitive configuration, supported database drivers, and runtime posture before publication.

## Coverage matrix

- Core HTTP and routing: `tests/Http/RequestTest.php`, `tests/Http/ApiResponseTest.php`, `tests/Http/ErrorHandlerTest.php`, `tests/Http/RouterTest.php`, and `tests/Api/HttpKernelTest.php`
- Router and module registration through the live kernel: `tests/Api/HttpKernelTest.php`, `tests/Api/CrudHttpKernelTest.php`, `tests/Api/AuthHttpKernelTest.php`, `tests/Api/AiHttpKernelTest.php`, and `tests/Api/OpenApiHttpKernelTest.php`
- Request and response contract behavior: `tests/Http/RequestTest.php` and `tests/Http/ApiResponseTest.php`
- Error handling and contract-safe failures: `tests/Http/ErrorHandlerTest.php`, `tests/Auth/RequireBearerTokenTest.php`, and the protected endpoint scenarios in `tests/Api/CrudHttpKernelTest.php`
- Database adapters and query execution: `tests/Database/AdapterFactoryTest.php`, `tests/Database/Adapters/MySqlAdapterTest.php`, `tests/Database/Adapters/PostgresAdapterTest.php`, and `tests/Database/QueryExecutorIntegrationTest.php`
- Migrations, seeds, and bootstrap-sensitive persistence flows: `tests/Database/FilesystemMigrationLoaderTest.php`, `tests/Database/MigrationRunnerIntegrationTest.php`, `tests/Database/FilesystemSeedLoaderTest.php`, and `tests/Database/SeedRunnerIntegrationTest.php`
- Schema inspection and entity introspection: `tests/Database/SchemaInspectorIntegrationTest.php`, `tests/Database/EntityIntrospectorTest.php`, and `tests/Database/EntityIntrospectorIntegrationTest.php`
- Automatic CRUD and declarative validation: `tests/Modules/Crud/CrudEntityRegistryTest.php`, `tests/Services/Crud/EntityCrudServiceIntegrationTest.php`, `tests/Services/Crud/EntityCrudDeclarativeConfigIntegrationTest.php`, `tests/Services/Crud/EntityCrudValidatorTest.php`, and `tests/Api/CrudHttpKernelTest.php`
- Authentication and authorization: `tests/Auth/JwtCodecTest.php`, `tests/Auth/AuthServiceIntegrationTest.php`, `tests/Auth/AuthorizationServiceTest.php`, `tests/Auth/RequireBearerTokenTest.php`, and `tests/Api/AuthHttpKernelTest.php`
- AI-friendly discovery endpoints: `tests/Services/Ai/AiSchemaServiceIntegrationTest.php` and `tests/Api/AiHttpKernelTest.php`
- OpenAPI generation and publication: `tests/Services/OpenApi/OpenApiDocumentBuilderTest.php`, `tests/Api/OpenApiHttpKernelTest.php`, and `tests/Scripts/OpenApiGenerateTest.php`
- CLI and developer tooling: `tests/Cli/PachybaseCliTest.php`, `tests/Cli/EnvironmentFileManagerTest.php`, `tests/Scripts/CrudSyncTest.php`, `tests/Scripts/DockerInstallTest.php`, and `tests/Scripts/AiBuildTest.php`
- Docker-backed integration surface, when applicable: `tests/Scripts/DockerInstallTest.php` plus the containerized PHPUnit execution path used by `pachybase test`

## Regression policy

- Every central runtime surface should have at least one unit or integration test protecting the happy path
- Authentication, authorization, validation, and transport failures should have at least one explicit error-path assertion
- New CRUD entities should be protected at the service layer and, when exposed publicly, by at least one HTTP-level test
- New CLI commands should verify the command mapping and the generated container command

## Current expectation

The central features should always keep a minimum automated safety net before new runtime changes expand behavior further.
