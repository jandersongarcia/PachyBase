---
id: release-process
title: Release Process
---

# Release Process

PachyBase `1.0.0-rc.1` adds a release-oriented workflow so the repository can be validated before it is handed to third parties.

## Recommended sequence

1. Review the root `VERSION` file.
2. Run `./pachybase doctor`.
3. Run `./pachybase test`.
4. Generate `build/openapi.json` with `./pachybase openapi:generate`.
5. Review `CHANGELOG.md`, `RELEASE_NOTES.md`, and `PUBLISHING_CHECKLIST.md`.

## What `doctor` checks

- `.env` presence
- `APP_ENV` and `APP_DEBUG` coherence
- supported database drivers and required DB variables
- PostgreSQL schema handling
- JWT secret readiness
- bootstrap admin defaults
- Docker posture such as pinned base images and unpublished database ports

## Publishing references

- Root changelog: `CHANGELOG.md`
- Root release notes: `RELEASE_NOTES.md`
- Root checklist: `PUBLISHING_CHECKLIST.md`
