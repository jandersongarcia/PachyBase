# PachyBase Runtime Checks Skill

## Use this skill when

- validating an installation or local environment
- verifying a change before handing work back
- preparing a release candidate or smoke-checking a runtime
- the task touches Docker, env files, generated docs, or runtime posture

## Minimum verification mindset

Prefer the project CLI over direct script calls whenever possible. The CLI keeps Docker and local runtime behavior aligned and is the canonical interface for operational checks.

## Common commands

```bash
./pachybase install
./pachybase status
./pachybase doctor
./pachybase acceptance:check
./pachybase test
```

On Windows:

```powershell
.\pachybase.bat install
.\pachybase.bat status
.\pachybase.bat doctor
.\pachybase.bat acceptance:check
.\pachybase.bat test
```

## When to run what

- Run `status` for a quick health view of runtime, database, auth, and generated docs.
- Run `doctor` when config, runtime posture, or release-sensitive settings may have changed.
- Run `acceptance:check` when the task affects HTTP discovery, MCP integration, or release confidence.
- Run `test` for code changes, especially in auth, CRUD, HTTP, scripts, and generators.
- Run `openapi:build` and `ai:build` when the public contract changes.

## Guardrails

- Do not claim a runtime path works unless the relevant command was actually executed.
- If a task changes docs, routes, CLI commands, or env behavior, update documentation alongside code.
- Prefer reporting exactly which commands were not run when verification is incomplete.

## References

- `docs-site/docs/cli.md`
- `docs-site/docs/testing.md`
- `docs-site/docs/production-deploy.md`
- `docs-site/docs/release-process.md`
