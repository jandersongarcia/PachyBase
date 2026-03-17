---
id: contributing
title: Contributing
---

# Contributing

PachyBase accepts code, docs, tests, and product feedback contributions. The main rule is simple: every change should leave the product easier to trust and easier to use.

## Local workflow

```bash
cp .env.example .env
./pachybase install
./pachybase test
```

On Windows, replace `./pachybase` with `.\pachybase.bat`.

## Contribution expectations

- Keep the public API contract stable unless the change is explicitly documented
- Add or update tests for behavior changes
- Update docs when adding routes, config, CLI commands, or operational steps
- Keep English and `pt-BR` docs in sync for user-facing pages
- Prefer declarative config changes over ad hoc controller logic when extending CRUD behavior

## Pull request checklist

- The feature or fix is explained clearly
- Tests were run, or an explicit reason is provided when they were not
- Documentation was updated where needed
- New config or environment variables are documented
- API behavior changes are reflected in OpenAPI and AI-facing docs when applicable

## Good first contribution areas

- documentation clarifications and examples
- new CRUD entity presets
- test coverage improvements
- OpenAPI and AI-documentation quality improvements
- CLI ergonomics
