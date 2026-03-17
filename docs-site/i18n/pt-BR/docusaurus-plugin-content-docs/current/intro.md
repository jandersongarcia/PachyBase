---
id: intro
title: Visao Geral
slug: /
sidebar_position: 1
---

# PachyBase

PachyBase e uma base backend self-hosted em PHP focada em APIs JSON previsiveis, setup local com Docker e contratos legiveis por maquina, seguros para humanos e para IA consumirem.

## O que o projeto faz hoje

- Carrega configuracoes da aplicacao e do banco a partir do `.env`.
- Expoe um endpoint de status por meio de um kernel HTTP modular.
- Usa um contrato JSON centralizado para respostas de sucesso e erro.
- Instala os servicos Docker via Composer com base no banco configurado.

## Principios atuais

- Respostas previsiveis com um contrato externo fixo.
- Desenvolvimento local simples com Docker.
- Pontos claros de extensao para rotas, modulos e geracao de CRUD.
- Documentacao que pode ser consumida em ingles e portugues do Brasil.

## Mapa da documentacao

- [Instalacao](./install.md)
- [Arquitetura](./architecture.md)
- [Contrato da API](./api-contract.md)
- [Camada de Banco](./database-layer.md)
- [Blindagem do Contrato](./contract-enforcement.md)
- [Bibliotecas](./libraries.md)
- [Instalacao Docker](./docker-install.md)

## Rodando o site de documentacao localmente

```bash
npm install
npm run start
```

Por padrao, a documentacao abre em ingles. Use o seletor de idioma na navegacao superior para alternar para `pt-BR`.

