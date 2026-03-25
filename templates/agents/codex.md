# PachyBase Codex Template

Use the project bootstrap token and tenant header returned by `project:provision`.

Suggested environment:

```env
PACHYBASE_BASE_URL=http://localhost:8080
PACHYBASE_TOKEN=<project-token>
PACHYBASE_TENANT=<project-slug>
```

Suggested operating rules:

- Read project configuration from `system-settings`.
- Never store provider keys outside project secrets.
- Use `POST /api/platform/jobs` for deferred work.
- Use `POST /api/platform/storage` for binary payloads encoded as base64.
