---
id: contributing
title: Contribuicoes
---

# Contribuicoes

O PachyBase aceita contribuicoes de codigo, documentacao, testes e feedback de produto. A regra principal e simples: toda mudanca deve deixar o produto mais confiavel e mais facil de usar.

## Fluxo local

```bash
cp .env.example .env
./pachybase install
./pachybase test
```

No Windows, troque `./pachybase` por `.\pachybase.bat`.

## Expectativas de contribuicao

- Manter o contrato publico da API estavel, salvo quando a mudanca estiver documentada
- Adicionar ou atualizar testes quando houver mudanca de comportamento
- Atualizar docs ao incluir rotas, configuracoes, comandos de CLI ou passos operacionais
- Manter docs em ingles e `pt-BR` sincronizados para paginas voltadas ao usuario
- Preferir configuracao declarativa a logica ad hoc em controllers ao estender o CRUD

## Checklist de pull request

- A feature ou correcao esta explicada com clareza
- Os testes foram executados, ou existe uma justificativa explicita quando nao foram
- A documentacao foi atualizada quando necessario
- Novas configuracoes ou variaveis de ambiente foram documentadas
- Mudancas de API aparecem no OpenAPI e nos docs para IA quando aplicavel

## Bons pontos de entrada

- clarificacoes e exemplos de documentacao
- novos presets de entidades CRUD
- melhorias de cobertura de teste
- qualidade da documentacao OpenAPI e dos endpoints para IA
- ergonomia da CLI
