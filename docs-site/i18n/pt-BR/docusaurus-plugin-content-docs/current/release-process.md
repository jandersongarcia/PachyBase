---
id: release-process
title: Processo de Release
---

# Processo de Release

O PachyBase `1.0.0-rc.1` adiciona um fluxo orientado a release para validar o repositório antes de entregá-lo a terceiros.

## Sequência recomendada

1. Revise o arquivo `VERSION` na raiz.
2. Execute `./pachybase doctor`.
3. Execute `./pachybase test`.
4. Gere `build/openapi.json` com `./pachybase openapi:generate`.
5. Revise `CHANGELOG.md`, `RELEASE_NOTES.md` e `PUBLISHING_CHECKLIST.md`.

## O que o `doctor` verifica

- presença do `.env`
- coerência entre `APP_ENV` e `APP_DEBUG`
- drivers de banco suportados e variáveis obrigatórias
- tratamento de schema no PostgreSQL
- prontidão do segredo JWT
- defaults do usuário bootstrap
- postura de Docker, como imagens pinadas e ausência de porta pública para o banco

## Referências de publicação

- changelog na raiz: `CHANGELOG.md`
- release notes na raiz: `RELEASE_NOTES.md`
- checklist na raiz: `PUBLISHING_CHECKLIST.md`
