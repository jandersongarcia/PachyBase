---
id: intro
title: Visão Geral
slug: /
sidebar_position: 1
---

# PachyBase

PachyBase é uma base backend self-hosted em PHP focada em APIs JSON previsíveis, setup local com Docker e contratos legíveis por máquina, seguros para humanos e para IA consumirem.

## O que o projeto faz hoje

- Carrega configurações da aplicação e do banco a partir do `.env`.
- Expõe um endpoint de status por meio de `public/index.php`.
- Usa um contrato JSON centralizado para respostas de sucesso e erro.
- Instala os serviços Docker via Composer com base no banco configurado.

## Princípios atuais

- Respostas previsíveis com um contrato externo fixo.
- Desenvolvimento local simples com Docker.
- Pontos claros de extensão para rotas, módulos e geração de CRUD.
- Documentação disponível em inglês e português do Brasil.

## Mapa da documentação

- [Contrato da API](./api-contract.md)
- [Instalação Docker](./docker-install.md)

## Rodando o site de documentação localmente

```bash
npm install
npm run start
```

Por padrão, a documentação abre em inglês. Use o seletor de idioma na navegação superior para alternar para `pt-BR`.
