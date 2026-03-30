# PachyBase AI Skills

This directory contains project-specific skills for AI agents that work on PachyBase.

These files are intentionally small and task-oriented. They help an agent load the right context quickly instead of rediscovering the same architecture, commands, and safety checks on every task.

Directory name: `.ai-skills/`

## How to use

1. Start with `pachybase-architecture.md` when the task touches runtime code.
2. Load the most relevant workflow skill for the task at hand.
3. Follow the referenced docs and commands before proposing or applying changes.
4. Prefer combining a small number of focused skills instead of one giant instruction file.

## Included skills

- `pachybase-architecture.md`: request lifecycle, module boundaries, and where code should usually live
- `pachybase-crud-workflow.md`: declarative CRUD changes, schema sync, and related validations
- `pachybase-runtime-checks.md`: install, health, release-readiness, and verification commands
- `pachybase-mcp-agent.md`: AI endpoints, MCP adapter usage, and token scoping for agents

## Authoring rules

- Keep each skill focused on one workflow or responsibility.
- Prefer exact project paths and commands over generic advice.
- Update the relevant skill when a CLI command, route surface, or architectural convention changes.
- Link back to the canonical docs in `docs-site/docs/` when a longer explanation already exists there.
