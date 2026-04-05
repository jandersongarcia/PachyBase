# PachyBase test inventory and backlog

## Scope analyzed

- Source areas reviewed: `api`, `auth`, `config`, `core`, `database`, `modules`, `public`, `routes`, `scripts`, `sdk`, `services`, `utils`.
- Current code inventory found in this workspace: 156 source files and 60 PHPUnit test files.
- Current suite status in this environment: `vendor/bin/phpunit --testdox` finished with exit code `0`.
- Coverage is strongest in HTTP kernel flows, auth core, CRUD, database adapters/introspection, OpenAPI, MCP server, and part of the CLI/scripts surface.
- Coverage is weakest in multi-tenant platform services, tenancy rules, observability helpers, config wrappers, low-level utils, JS SDK, browser surface, and several operational scripts.

## Existing automated coverage

- [x] API and kernel flows: `tests/Api/AiHttpKernelTest.php`, `tests/Api/AuthHttpKernelTest.php`, `tests/Api/CrudHttpKernelTest.php`, `tests/Api/HttpKernelTest.php`, `tests/Api/OpenApiHttpKernelTest.php`, `tests/Api/PlatformHttpKernelTest.php`
- [x] Architecture guardrails: `tests/Architecture/ApiContractEnforcementTest.php`
- [x] Auth core: `tests/Auth/AuthorizationServiceTest.php`, `tests/Auth/AuthServiceIntegrationTest.php`, `tests/Auth/JwtCodecTest.php`, `tests/Auth/RequireBearerTokenTest.php`
- [x] CLI core: `tests/Cli/EnvironmentFileManagerTest.php`, `tests/Cli/PachybaseCliTest.php`
- [x] Database adapters and query layer: `tests/Database/AbstractDatabaseAdapterTest.php`, `tests/Database/AdapterFactoryTest.php`, `tests/Database/PdoQueryExecutorTest.php`, `tests/Database/QueryExecutorIntegrationTest.php`, `tests/Database/Adapters/MySqlAdapterTest.php`, `tests/Database/Adapters/PostgresAdapterTest.php`
- [x] Database metadata and schema: `tests/Database/EntityIntrospectorIntegrationTest.php`, `tests/Database/EntityIntrospectorTest.php`, `tests/Database/FileMetadataCacheTest.php`, `tests/Database/SchemaInspectorIntegrationTest.php`, `tests/Database/TypeNormalizerTest.php`
- [x] Migrations and seeds: `tests/Database/FilesystemMigrationLoaderTest.php`, `tests/Database/FilesystemSeedLoaderTest.php`, `tests/Database/MigrationRunnerIntegrationTest.php`, `tests/Database/SeedRunnerIntegrationTest.php`
- [x] HTTP infrastructure: `tests/Http/ApiResponseTest.php`, `tests/Http/CorsPolicyTest.php`, `tests/Http/ErrorHandlerTest.php`, `tests/Http/FileRateLimiterTest.php`, `tests/Http/RequestTest.php`, `tests/Http/RouterTest.php`, `tests/Http/SystemControllerTest.php`
- [x] CRUD module and services: `tests/Modules/Crud/CrudEntityRegistryTest.php`, `tests/Services/Crud/EntityCrudDeclarativeConfigIntegrationTest.php`, `tests/Services/Crud/EntityCrudFilterContractTest.php`, `tests/Services/Crud/EntityCrudServiceIntegrationTest.php`, `tests/Services/Crud/EntityCrudValidatorTest.php`
- [x] AI, docs, MCP, and OpenAPI services: `tests/Services/Ai/AiSchemaServiceContractTest.php`, `tests/Services/Ai/AiSchemaServiceIntegrationTest.php`, `tests/Services/Documentation/BuildDocumentRepositoryTest.php`, `tests/Services/Mcp/McpServerTest.php`, `tests/Services/OpenApi/OpenApiDocumentBuilderContractTest.php`, `tests/Services/OpenApi/OpenApiDocumentBuilderTest.php`
- [x] Audit and release metadata: `tests/Services/Audit/AuditLoggerTest.php`, `tests/Release/ProjectMetadataTest.php`
- [x] Script coverage already present: `tests/Scripts/AcceptanceCheckTest.php`, `tests/Scripts/AiBuildTest.php`, `tests/Scripts/AuthTokenCreateTest.php`, `tests/Scripts/BenchmarkLocalTest.php`, `tests/Scripts/CrudSyncTest.php`, `tests/Scripts/DockerInstallTest.php`, `tests/Scripts/DoctorTest.php`, `tests/Scripts/HttpSmokeTest.php`, `tests/Scripts/McpServeTest.php`, `tests/Scripts/OpenApiGenerateTest.php`, `tests/Scripts/StatusTest.php`, `tests/Scripts/StressTestTest.php`

## High-priority gaps

- [ ] `tests/Services/Platform/ProjectPlatformServiceTest.php`: cover project provisioning, duplicate slug rejection, quota upsert, backup creation, backup restore, secret CRUD, and filesystem cleanup for project data.
- [ ] `tests/Services/Platform/StorageServiceTest.php`: cover upload success, invalid base64 rejection, quota exhaustion, filename sanitization, and download failure when the blob is missing on disk.
- [ ] `tests/Services/Platform/WebhookServiceTest.php`: cover create/update/delete, required secret validation, inactive webhook rejection, queued test job payload, delivery log persistence, and non-2xx delivery failure.
- [ ] `tests/Services/Platform/AsyncJobServiceTest.php`: cover `noop`, `http.request`, and `webhook.delivery` jobs, invalid payload/type errors, retry behavior, and transition to terminal `failed` status after `max_attempts`.
- [ ] `tests/Services/Platform/OperationsOverviewServiceTest.php`: cover aggregate counters, last-24h windows, storage bytes, error counts, and ordering of recent backups.
- [ ] `tests/Services/Tenancy/TenantRepositoryTest.php`: cover default tenant bootstrap, slug/id resolution, missing tenant errors, and default settings seeding.
- [ ] `tests/Services/Tenancy/TenantQuotaServiceTest.php`: cover request quota consumption, monthly reset bucket behavior, API token limit checks, tenant entity limit checks, and snapshot payload structure.
- [ ] `tests/Services/Tenancy/TenantRequestResolverTest.php`: cover tenant resolution from header and payload, principal/tenant mismatch rejection, and empty reference fallback.
- [ ] `tests/Http/DatabaseRateLimiterTest.php`: cover first-hit bucket creation, counter increment, window reset, authorization-vs-IP bucket keys, tenant scoping, OPTIONS bypass, and 429 retry message.
- [ ] `tests/Http/RateLimitPolicyTest.php`: cover config parsing, backend normalization, absolute vs relative storage path resolution, and min-bound handling for limits/window.
- [ ] `tests/Services/SystemStatusServiceTest.php`: cover production vs development payload shape and degraded database health branch.

## Medium-priority gaps

- [ ] `tests/Auth/ApiTokenRepositoryTest.php`: cover create, find active token, touch last used, and revoke metadata fields.
- [ ] `tests/Auth/RefreshTokenRepositoryTest.php`: cover create, lookup, revoke by id, revoke by hash, and inactive token behavior.
- [ ] `tests/Auth/UserRepositoryTest.php`: cover active user lookup by email/id, tenant scoping, and login timestamp updates.
- [ ] `tests/Auth/BearerTokenAuthenticatorTest.php`: cover JWT path, API token path, missing header, malformed prefix, and invalid credential failure.
- [ ] `tests/Services/Crud/EntityCrudSerializerTest.php`: cover boolean, numeric, JSON, and hidden-field serialization rules.
- [ ] `tests/Services/Mcp/HttpMcpBackendClientTest.php`: cover CRUD request mapping, query serialization, status-code parsing, and backend error handling.
- [ ] `tests/Cli/ScaffoldGeneratorTest.php`: cover file generation for module/controller/service/middleware/test/migration/seed and force-overwrite behavior.
- [ ] `tests/Cli/LocalRuntimeManagerTest.php`: cover start/stop/status, pid file handling, runtime directory creation, and no-PHP-binary behavior.
- [ ] `tests/Services/Observability/RequestContextTest.php`: cover set/current/clear lifecycle.
- [ ] `tests/Services/Observability/RequestMetricsTest.php`: cover reset, lazy start, query/introspection accumulation, snapshot rounding, and response header formatting.
- [ ] `tests/Utils/BooleanParserTest.php`: cover boolean parsing for null, booleans, ints, and string aliases.
- [ ] `tests/Utils/CryptoTest.php`: cover round-trip encryption/decryption, invalid payload rejection, MAC integrity failure, and `APP_KEY` requirements.
- [ ] `tests/Utils/JsonTest.php`: cover JSON encoding success and encoding failure wrapping.
- [ ] `tests/Config/AppConfigTest.php`: cover load, override, reset, environment normalization, and debug flag behavior.
- [ ] `tests/Config/AuthConfigTest.php`: cover secret fallback rules, production failure, issuer fallback, TTL lower bounds, and bootstrap admin defaults.
- [ ] `tests/Config/TenancyConfigTest.php`: cover header/default slug/default name normalization and fallback values.
- [ ] `tests/Config/BootstrapTest.php`: cover config bootstrapping, error handler registration, and `HttpKernel` creation.

## Script backlog without dedicated tests

- [ ] `tests/Scripts/BootstrapDatabaseTest.php`: validate bootstrap flow, migration+seed invocation, and failure propagation.
- [ ] `tests/Scripts/DbFreshTest.php`: validate destructive refresh ordering and follow-up seed behavior.
- [ ] `tests/Scripts/EnvValidateTest.php`: validate missing required env vars, success output, and non-zero exit on invalid config.
- [ ] `tests/Scripts/InspectEntitiesTest.php`: validate entity inspection output and empty-entity behavior.
- [ ] `tests/Scripts/InspectSchemaTest.php`: validate schema dump output and adapter-specific paths.
- [ ] `tests/Scripts/JobsWorkTest.php`: validate due-job execution loop and non-fatal processing of failed jobs.
- [ ] `tests/Scripts/MigrateTest.php`: validate `status`, `up`, and `down` branches.
- [ ] `tests/Scripts/ProjectProvisionTest.php`: validate CLI argument parsing and provisioned project output contract.
- [ ] `tests/Scripts/ProjectBackupTest.php`: validate backup command success and missing-project failure.
- [ ] `tests/Scripts/ProjectRestoreTest.php`: validate restore command success and invalid backup id failure.
- [ ] `tests/Scripts/VersionTest.php`: validate emitted version string and formatting.

## Browser and SDK backlog

- [ ] `tests-js/sdk/pachybase-sdk.test.js`: cover request headers, tenant header injection, error mapping, and helper methods for project/backup/secret/job/webhook/storage endpoints.
- [ ] `tests-e2e/public/login.spec.ts`: cover login success, login failure, `/api/auth/me` flow with saved token, and token clear action for `public/login.html`.
- [ ] Manual smoke for `public/router.php` and `public/index.php`: verify static-file bypass and front-controller fallback in a real PHP built-in server session.

## Suggested execution order

- [ ] Phase 1: platform, tenancy, and database rate-limit tests because these are the biggest behavior and regression risks.
- [ ] Phase 2: config, observability, auth repositories, and CLI scaffolding/runtime helpers.
- [ ] Phase 3: remaining scripts, JS SDK, and browser-level smoke/E2E coverage.
