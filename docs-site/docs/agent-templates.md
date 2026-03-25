---
title: Agent Templates
---

# Agent Templates

PachyBase ships with minimal starter templates under `templates/agents/`.

## Included templates

- `templates/agents/codex.md`
- `templates/agents/claude.md`
- `templates/agents/mcp-server.json`

## Recommended flow

1. Provision a project and capture the bootstrap token.
2. Store provider credentials through project secrets.
3. Point the agent at the project-scoped base URL and bearer token.
4. Run `jobs:work` if the workflow depends on queued jobs or webhook deliveries.
