---
title: Agent Templates
---

# Agent Templates

PachyBase ships with minimal starter templates under `templates/agents/` and project-specific AI skills under `.ai-skills/`.

## Included templates

- `templates/agents/codex.md`
- `templates/agents/claude.md`
- `templates/agents/mcp-server.json`

## Included skills

- `.ai-skills/pachybase-architecture.md`
- `.ai-skills/pachybase-crud-workflow.md`
- `.ai-skills/pachybase-runtime-checks.md`
- `.ai-skills/pachybase-mcp-agent.md`

Use the templates to bootstrap an agent configuration and the skills to teach the agent how PachyBase expects work to be done.

## Recommended flow

1. Provision a project and capture the bootstrap token.
2. Store provider credentials through project secrets.
3. Point the agent at the project-scoped base URL and bearer token.
4. Run `jobs:work` if the workflow depends on queued jobs or webhook deliveries.
