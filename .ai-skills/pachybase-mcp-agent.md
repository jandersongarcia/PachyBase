# PachyBase MCP and Agent Integration Skill

## Use this skill when

- connecting an AI agent to a live PachyBase runtime
- deciding between OpenAPI, AI endpoints, and MCP tools
- creating scoped tokens for automation or agent workflows
- building prompts, templates, or integrations for Codex, Claude, or similar tools

## Discovery order

1. Use `/ai/schema` for a compact, machine-oriented map of the exposed backend.
2. Use `/ai/entity/{name}` for one entity contract.
3. Use `/openapi.json` for transport-level schemas and client generation.
4. Use the MCP adapter when the agent can benefit from structured tools instead of raw HTTP calls.

## MCP basics

Start the adapter with the project CLI:

```bash
./pachybase mcp:serve
```

On Windows:

```powershell
.\pachybase.bat mcp:serve
```

By default the adapter uses:

- `APP_URL` from `.env`
- `PACHYBASE_MCP_TOKEN` for protected CRUD operations

## Token guidance

- Create scoped tokens with `auth:token:create`.
- Grant only the scopes required by the workflow.
- Prefer integration-specific tokens over personal broad-access tokens.
- Keep provider secrets in project secrets, not in prompts or generated files.

Example:

```bash
./pachybase auth:token:create "Codex Agent" --scope=crud:read --scope=crud:create
```

## Guardrails

- Use protected CRUD tools with a scoped bearer token.
- Do not assume docs endpoints are public in every deployment.
- When public behavior changes, remember that MCP, AI endpoints, and OpenAPI should remain coherent.
- Prefer structured responses and explicit scopes over vague natural-language side channels.

## References

- `docs-site/docs/ai-endpoints.md`
- `docs-site/docs/mcp.md`
- `docs-site/docs/openapi.md`
- `templates/agents/codex.md`
- `templates/agents/claude.md`
