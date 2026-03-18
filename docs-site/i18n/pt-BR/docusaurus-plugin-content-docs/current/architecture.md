---
id: architecture
title: Arquitetura
sidebar_position: 2
---

# Arquitetura

O PachyBase agora separa a aplicacao em camadas explicitas em vez de concentrar tudo dentro de `core/`.

## Mapa das camadas

- `public/`: front controller apenas. Ele delega para o bootstrap e para o kernel HTTP.
- `config/`: configuracao baseada em ambiente e amarracao do bootstrap.
- `routes/`: pontos de entrada para registro de rotas.
- `api/`: kernel HTTP e controllers da API.
- `modules/`: composicao de rotas orientada por dominio.
- `services/`: fluxos de negocio reutilizaveis pelos controllers.
- `database/`: conexao, adapters, inspecao de schema e infraestrutura de persistencia.
- `auth/`: servicos e middleware de autenticacao.
- `utils/`: helpers reutilizaveis.
- `core/Http/`: infraestrutura HTTP compartilhada, captura de request, roteamento, respostas da API e tratamento de erro.

## Ciclo da requisicao

1. `public/index.php` carrega o autoload do Composer e chama `PachyBase\Config\Bootstrap`.
2. `config/Bootstrap.php` carrega os valores do `.env` e registra o error handler global.
3. `api/HttpKernel.php` captura a requisicao atual e carrega `routes/api.php`.
4. `routes/api.php` registra modulos como `modules/System/SystemModule.php` e `modules/Crud/CrudModule.php`.
5. Controllers em `api/Controllers/` delegam a logica de negocio para `services/`, incluindo a camada de CRUD automatico.
6. As respostas continuam passando por `core/Http/ApiResponse.php` para preservar o contrato.

## Exemplo atual

O endpoint raiz de status e implementado por esta cadeia:

- `routes/api.php`
- `modules/System/SystemModule.php`
- `api/Controllers/SystemController.php`
- `services/SystemStatusService.php`
- `database/Connection.php`

Os endpoints de CRUD automatico sao implementados por esta cadeia:

- `routes/api.php`
- `modules/Crud/CrudModule.php`
- `api/Controllers/CrudController.php`
- `services/Crud/EntityCrudService.php`
- `database/Metadata/EntityIntrospector.php`
- `database/Query/PdoQueryExecutor.php`

## Validacao

A arquitetura esta coberta por:

- `tests/Api/HttpKernelTest.php` para o fluxo fim a fim de roteamento no kernel.
- `tests/Auth/RequireBearerTokenTest.php` para a camada de autenticacao.
- `tests/Http/*` para request, router, contrato de resposta e tratamento de erros.

