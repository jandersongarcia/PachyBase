# PachyBase

Current stable release: `1.0.0`

## 1. Visao geral do projeto

PachyBase e uma base open-source e self-hosted para construir APIs JSON previsiveis em PHP. O projeto combina runtime HTTP, autenticacao, CRUD declarativo, contratos legiveis por humanos e agentes, e uma camada operacional para provisionamento e gestao de projetos.

Problemas que o PachyBase resolve no estado atual do repositorio:

- padroniza respostas HTTP em um envelope JSON fixo
- expoe CRUD automatico a partir de configuracao declarativa
- publica documentacao de contrato por OpenAPI e endpoints AI-friendly
- oferece autenticacao com JWT, refresh token e API token
- centraliza setup local e operacional via CLI e scripts
- adiciona capacidades de plataforma para backups, restore, secrets, jobs, storage e webhooks
- suporta isolamento multi-tenant e quotas por tenant
- inclui validacao de release com smoke checks, benchmark local, stress test, PHPUnit e PHPStan

Principais modulos e superficies:

- `Platform`: provisionamento de projetos, backups, restore, secrets, jobs assincronos, storage e webhooks
- `Tenancy`: resolucao de tenant, quotas por tenant e isolamento por contexto
- `Rate Limiting`: politica e enforcement por backend de arquivo ou banco
- `Auth`: login, refresh, revogacao, `me`, emissao e revogacao de API tokens
- `CRUD`: exposicao automatica de entidades definidas em `config/CrudEntities.php`
- `OpenAPI`: geracao e publicacao do documento OpenAPI 3.0.3
- `AI Schema`: endpoints `/ai/schema`, `/ai/entities` e `/ai/entity/{name}`
- `MCP`: adapter stdio para agentes em `scripts/mcp-serve.php`
- `Observability`: metricas basicas por request e headers como `Server-Timing`
- `Audit`: trilha de auditoria para fluxos sensiveis quando habilitada

Superficie HTTP principal atual:

- `GET /`
- `GET /health`
- `GET /openapi.json`
- `GET /ai/schema`
- `GET /ai/entities`
- `GET /ai/entity/{name}`
- `POST /api/auth/login`
- `POST /api/auth/refresh`
- `POST /api/auth/revoke`
- `GET /api/auth/me`
- `POST /api/auth/tokens`
- `DELETE /api/auth/tokens/{id}`
- `GET /api/{entity}`
- `GET /api/{entity}/{id}`
- `POST /api/{entity}`
- `PUT /api/{entity}/{id}`
- `PATCH /api/{entity}/{id}`
- `DELETE /api/{entity}/{id}`
- `GET /api/platform/projects`
- `POST /api/platform/projects`
- `POST /api/platform/projects/{project}/backups`
- `POST /api/platform/projects/{project}/restore`
- `PUT /api/platform/projects/{project}/secrets/{key}`
- `GET /api/platform/operations/overview`
- `POST /api/platform/jobs`
- `POST /api/platform/jobs/run`
- `POST /api/platform/webhooks`
- `POST /api/platform/webhooks/{id}/test`
- `POST /api/platform/storage`
- `GET /api/platform/storage/{id}/download`

## 2. Setup do ambiente

Pre-requisitos para desenvolvimento:

- PHP 8.2 ou superior
- Composer
- MySQL ou PostgreSQL
- Docker e Docker Compose se voce optar pelo fluxo Docker-first

Observacoes importantes:

- o projeto suporta oficialmente `mysql` e `pgsql`
- os comandos `vendor/bin/phpunit` e `vendor/bin/phpstan` exigem `php` disponivel no `PATH`
- no Windows, se `php` nao estiver no `PATH`, os wrappers em `vendor/bin` nao executam corretamente
- no Windows com XAMPP, um caminho tipico e `C:\xampp\php`; adicione esse diretorio ao `PATH` antes de usar os comandos de qualidade

Instalacao de dependencias:

```bash
composer install
```

Se voce ainda nao tiver um `composer` global disponivel:

```bash
php composer.phar install
```

Configuracao inicial do ambiente:

```bash
cp .env.example .env
```

No Windows:

```powershell
Copy-Item .env.example .env
```

Valores principais esperados em `.env`:

```env
APP_NAME=PachyBase
APP_ENV=development
APP_DEBUG=true
APP_RUNTIME=docker
APP_HOST=127.0.0.1
APP_PORT=8080
APP_URL=http://localhost:8080

DB_DRIVER=mysql
DB_HOST=db
DB_PORT=3306
DB_HOST_PORT=3307
DB_DATABASE=pachybase
DB_USERNAME=pachybase
DB_PASSWORD=change_this_password
```

Setup Docker-first suportado pelo projeto:

```bash
./pachybase install
```

No Windows:

```powershell
.\pachybase.bat install
```

Apos a instalacao padrao:

- API base URL: `http://localhost:8080`
- OpenAPI: `http://localhost:8080/openapi.json`
- AI schema: `http://localhost:8080/ai/schema`
- login local de desenvolvimento: `admin@pachybase.local` / `pachybase123`
- tela simples de login local: `http://localhost:8080/login.html`

Notas operacionais relevantes:

- quando `APP_RUNTIME=docker`, o app usa `DB_HOST=db` internamente no container
- ferramentas no host devem usar `DB_HOST_PORT` quando esse valor estiver definido
- o comportamento declarativo do CRUD fica em `config/CrudEntities.php`
- a configuracao minima do PHPStan fica em `phpstan.neon.dist`

## 3. Comandos principais

Comandos obrigatorios de qualidade:

```bash
vendor/bin/phpunit --testdox
vendor/bin/phpstan analyse --no-progress
```

Scripts do Composer disponiveis no estado atual:

```bash
composer test
composer analyse
composer check
```

O que cada script faz:

- `composer test`: executa `phpunit --testdox`
- `composer analyse`: executa `phpstan analyse --no-progress`
- `composer check`: executa `test` e depois `analyse`

CLI principal do projeto:

- `./pachybase install`
- `./pachybase start`
- `./pachybase stop`
- `./pachybase doctor`
- `./pachybase http:smoke`
- `./pachybase benchmark:local`
- `./pachybase stress:test`
- `./pachybase acceptance:check`
- `./pachybase status`
- `./pachybase test`

Comandos de banco e build expostos em `composer.json`:

- `composer migrate`
- `composer migrations:status`
- `composer migrations:rollback`
- `composer db:seed`
- `composer db:seed:status`
- `composer db:bootstrap`
- `composer db:fresh`
- `composer crud:sync`
- `composer openapi:generate`
- `composer ai:build`

Exemplos praticos:

```bash
vendor/bin/phpunit --testdox
vendor/bin/phpstan analyse --no-progress
composer check
./pachybase doctor
./pachybase http:smoke
```

## 4. Fluxo de desenvolvimento

O projeto esta orientado por testes. Mudancas novas devem comecar por cobertura automatizada ou por ampliacao explicita do contrato de testes existente.

Arquivo de referencia para evolucao de cobertura:

- `tests.md`

Como `tests.md` deve ser usado no estado atual:

- como inventario da cobertura automatizada existente
- como backlog priorizado de lacunas de teste
- como contrato para a proxima rodada de implementacao de testes

Ciclo obrigatorio de trabalho:

1. implementar a alteracao
2. adicionar ou atualizar testes compativeis com a mudanca
3. rodar `vendor/bin/phpunit --testdox`
4. corrigir qualquer falha de teste
5. rodar `vendor/bin/phpstan analyse --no-progress`
6. corrigir qualquer erro de analise estatica
7. so considerar a tarefa concluida quando ambos retornarem `exit code 0`

Para mudancas maiores:

- consulte `tests.md` antes de decidir a area de cobertura
- priorize os testes marcados como lacunas de maior risco
- preserve a estrutura atual de integracao e suporte em `tests/Support`

## 5. Estrutura do projeto

Diretorios principais do runtime:

- `api/`: kernel HTTP e controllers da API
- `auth/`: autenticacao, autorizacao, repositorios de token e middleware
- `config/`: carga de configuracao, bootstrap e entidades CRUD declarativas
- `core/`: infraestrutura comum de CLI, HTTP e release metadata
- `database/`: conexao, adapters, schema, metadata, migrations e seeds
- `modules/`: registro modular das superficies `System`, `Auth`, `CRUD`, `OpenAPI`, `AI` e `Platform`
- `services/`: regras de negocio e servicos operacionais
- `utils/`: utilitarios como `Json`, `Crypto` e parsing simples
- `routes/`: registro central de rotas HTTP
- `public/`: front controller, router do servidor embutido e `login.html`
- `scripts/`: comandos operacionais e fluxos de automacao
- `sdk/`: SDK JavaScript atual do projeto

Diretorios de teste e apoio:

- `tests/Api`: testes de kernel e fluxos HTTP principais
- `tests/Auth`: testes de autenticacao e autorizacao
- `tests/Database`: adapters, query layer, schema, migrations e seeds
- `tests/Http`: infraestrutura HTTP
- `tests/Services`: testes de servicos por dominio
- `tests/Support`: suporte compartilhado para integracao e utilidades de teste
- `tests.md`: backlog e contrato de cobertura

Outros diretorios relevantes:

- `assets/`: artefatos auxiliares como baselines
- `build/`: saida de build, backups, storage e temporarios de ferramentas
- `docker/`: artefatos do setup Docker
- `docs-site/`: site de documentacao
- `.ai-skills/`: arquivos de apoio para agentes

Mapeamento rapido de responsabilidades em `services/`:

- `services/Platform/`: projetos, backups, secrets, jobs, webhooks, storage e overview operacional
- `services/Tenancy/`: resolucao de tenant, quotas e regras de contexto
- `services/OpenApi/`: construcao do documento OpenAPI
- `services/Ai/`: construcao do schema AI
- `services/Mcp/`: adapter HTTP para o backend MCP
- `services/Observability/`: contexto e metricas de request
- `services/Audit/`: auditoria

## 6. Regras de qualidade

Regras operacionais obrigatorias:

- nao aceitar codigo novo sem teste quando a mudanca altera comportamento
- nao aceitar testes quebrados
- nao aceitar analise estatica quebrada
- nao mesclar trabalho com `phpunit` falhando
- nao mesclar trabalho com `phpstan` falhando

Checks obrigatorios para considerar uma alteracao concluida:

```bash
vendor/bin/phpunit --testdox
vendor/bin/phpstan analyse --no-progress
```

Criterio pratico de aprovacao:

- a suite de testes precisa terminar com `exit code 0`
- a analise estatica precisa terminar com `exit code 0`
- o `README.md` e o `tests.md` precisam continuar coerentes com o estado real do repositorio quando houver mudanca estrutural

## Referencias adicionais

Documentacao publicada:

- English: <https://jandersongarcia.github.io/PachyBase/>
- Portuguese: <https://jandersongarcia.github.io/PachyBase/pt-BR/>

Arquivos operacionais do repositorio:

- `ROADMAP.md`
- `CHANGELOG.md`
- `RELEASE_NOTES.md`
- `PUBLISHING_CHECKLIST.md`
- `ENVIRONMENT_VALIDATION.md`
