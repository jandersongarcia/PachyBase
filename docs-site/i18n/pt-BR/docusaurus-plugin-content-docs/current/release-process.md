---
id: release-process
title: Processo de Release
---

# Processo de Release

O PachyBase `1.0.0` inclui um fluxo orientado a release para validar o repositorio antes de entrega-lo a terceiros.

## Sequencia recomendada

1. Revise o arquivo `VERSION` na raiz.
2. Execute `./pachybase doctor`.
3. Execute `./pachybase test`.
4. Gere `build/openapi.json` com `./pachybase openapi:build`.
5. Revise `CHANGELOG.md`, `RELEASE_NOTES.md` e `PUBLISHING_CHECKLIST.md`.

## O que o `doctor` verifica

- presenca do `.env`
- coerencia entre `APP_ENV` e `APP_DEBUG`
- drivers de banco suportados e variaveis obrigatorias
- tratamento de schema no PostgreSQL
- prontidao do segredo JWT
- defaults do usuario bootstrap
- postura de Docker, como imagens pinadas e ausencia de porta publica para o banco

## Referencias de publicacao

- changelog na raiz: `CHANGELOG.md`
- release notes na raiz: `RELEASE_NOTES.md`
- checklist na raiz: `PUBLISHING_CHECKLIST.md`
