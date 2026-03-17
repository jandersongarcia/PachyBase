# Contributing to PachyBase

Thanks for contributing to PachyBase.

## Local setup

```bash
cp .env.example .env
./pachybase install
./pachybase test
```

On Windows, use `.\pachybase.bat`.

## Expectations

- Keep behavior changes covered by tests.
- Update documentation when routes, CLI commands, configuration, or operational flows change.
- Keep English docs in `docs-site/docs/` and Portuguese docs in `docs-site/i18n/pt-BR/docusaurus-plugin-content-docs/current/` aligned for user-facing pages.
- Prefer declarative CRUD and module changes over one-off runtime shortcuts.

## Before opening a PR

- Run the automated tests that apply to your change.
- Document new environment variables and operational steps.
- Reflect contract changes in OpenAPI or AI docs when relevant.

See the full guide in [`docs-site/docs/contributing.md`](docs-site/docs/contributing.md).
