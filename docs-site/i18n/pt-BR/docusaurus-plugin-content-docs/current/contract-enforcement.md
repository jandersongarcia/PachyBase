---
id: contract-enforcement
title: Blindagem do Contrato
sidebar_position: 4
---

# Blindagem do Contrato

A Fase 1 so termina quando o contrato da API nao esta apenas documentado, mas tambem protegido pelo codigo e pela suite de testes.

## Regras de blindagem

- Toda resposta HTTP de sucesso deve sair por `core/Http/ApiResponse.php`.
- Toda falha deve ser normalizada por `core/Http/ErrorHandler.php`.
- Controllers, services, modules, rotas, bootstrap e middleware de autenticacao nao podem chamar `echo`, `print`, `exit`, `header()`, `http_response_code()` ou `json_encode()` diretamente.
- Endpoints novos devem preservar o envelope publico com `success`, `data`, `meta` e `error`.
- `meta.request_id`, `meta.timestamp`, `meta.method` e `meta.path` sao obrigatorios em toda resposta.

## Cobertura de testes

O contrato esta protegido por:

- `tests/Http/ApiResponseTest.php`
- `tests/Http/ErrorHandlerTest.php`
- `tests/Api/HttpKernelTest.php`
- `tests/Architecture/ApiContractEnforcementTest.php`

## Comandos de validacao

```bash
docker compose -f docker/docker-compose.yml exec php composer dump-autoload
docker compose -f docker/docker-compose.yml exec php vendor/bin/phpunit --testdox
```

## Smoke tests recomendados

```bash
curl http://localhost:8080/
curl http://localhost:8080/missing
curl -X POST http://localhost:8080/
```
