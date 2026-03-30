# PachyBase CRUD Workflow Skill

## Use this skill when

- creating or changing an exposed CRUD entity
- updating `config/CrudEntities.php`
- adjusting filters, pagination, writable fields, defaults, or validation behavior
- syncing the runtime metadata after schema changes

## Primary sources of truth

- `config/CrudEntities.php`
- database schema and migrations under `database/`
- CRUD runtime code in `modules/Crud/`, `api/Controllers/CrudController.php`, and `services/Crud/`

## Recommended workflow

1. Confirm the table or schema change exists or add the needed migration first.
2. Update the entity declaration in `config/CrudEntities.php` when the change is declarative.
3. Run the appropriate sync or build command after the schema change.
4. Regenerate AI and OpenAPI artifacts when the exposed contract changes.
5. Add or update tests at the service and HTTP layers when behavior changes.

## Useful commands

```bash
./pachybase crud:sync
./pachybase crud:sync --expose-new
./pachybase entity:list
./pachybase openapi:build
./pachybase ai:build
./pachybase test
```

On Windows:

```powershell
.\pachybase.bat crud:sync
.\pachybase.bat entity:list
.\pachybase.bat openapi:build
.\pachybase.bat ai:build
.\pachybase.bat test
```

## Guardrails

- Keep exposed entity names stable unless the contract change is intentional and documented.
- Prefer declarative CRUD configuration over controller-specific branching.
- Preserve the standard envelope, auth checks, and rate limiting behavior on CRUD routes.
- Remember that AI endpoints and OpenAPI should reflect public CRUD changes.

## Typical test targets

- `tests/Services/Crud/`
- `tests/Api/CrudHttpKernelTest.php`
- entity metadata or introspection tests under `tests/Database/`

## References

- `docs-site/docs/automatic-crud.md`
- `docs-site/docs/entity-metadata.md`
- `docs-site/docs/filters-pagination.md`
- `docs-site/docs/openapi.md`
- `docs-site/docs/ai-endpoints.md`
