---
id: auth-security
title: Autenticação e autorização
sidebar_position: 7
---

# Autenticação e autorização

A Fase 7 adiciona uma camada híbrida de segurança ao PachyBase sem inflar o core do projeto.

## Credenciais suportadas

O runtime agora aceita dois tipos de credencial bearer:

- API tokens para integrações servidor-servidor e automações
- JWT access tokens para clientes web e mobile

Os refresh tokens são rastreados separadamente em `pb_auth_sessions` e são usados apenas pelos endpoints de auth.

## Superfície de rotas

Rotas públicas de auth:

- `POST /api/auth/login`
- `POST /api/auth/refresh`
- `POST /api/auth/revoke`

Rotas protegidas de auth:

- `GET /api/auth/me`
- `POST /api/auth/tokens`
- `DELETE /api/auth/tokens/{id}`

Rotas protegidas de CRUD:

- todos os endpoints CRUD em `/api/{entity}` agora exigem credencial bearer

## Fluxo de login e refresh

1. `POST /api/auth/login` valida as credenciais do usuário em `pb_users`.
2. Em caso de sucesso, o PachyBase emite:
   - um JWT access token de curta duração
   - um refresh token persistido em `pb_auth_sessions`
3. `POST /api/auth/refresh` rotaciona a sessão de refresh e devolve um novo par de tokens.
4. `POST /api/auth/revoke` pode revogar:
   - um refresh token enviado no payload
   - a sessão JWT autenticada atual
   - o API token autenticado atual

## Modelo de escopos

A autorização é `deny by default` quando uma rota ou ação exige permissão.

As convenções atuais de escopo incluem:

- `crud:read`
- `crud:create`
- `crud:update`
- `crud:delete`
- `entity:{entity}:read`
- `entity:{entity}:create`
- `entity:{entity}:update`
- `entity:{entity}:delete`
- `auth:tokens:create`
- `auth:tokens:revoke`
- `auth:manage`

Escopos curinga são aceitos com grants como:

- `entity:system-settings:*`
- `crud:*`
- `*`

## Usuário bootstrap

O bootstrap local agora semeia um usuário admin padrão para desenvolvimento e smoke tests:

- email: `admin@pachybase.local`
- senha: `pachybase123`

Você pode sobrescrever isso antes de `composer db:bootstrap` com:

- `AUTH_BOOTSTRAP_ADMIN_EMAIL`
- `AUTH_BOOTSTRAP_ADMIN_PASSWORD`
- `AUTH_BOOTSTRAP_ADMIN_NAME`

## Configuração por ambiente

A camada de auth lê estas variáveis de ambiente:

- `AUTH_JWT_SECRET`
- `AUTH_JWT_ISSUER`
- `AUTH_ACCESS_TTL_MINUTES`
- `AUTH_REFRESH_TTL_DAYS`
- `AUTH_BOOTSTRAP_ADMIN_EMAIL`
- `AUTH_BOOTSTRAP_ADMIN_PASSWORD`
- `AUTH_BOOTSTRAP_ADMIN_NAME`

Em desenvolvimento, o PachyBase usa um segredo JWT local quando `AUTH_JWT_SECRET` não está definido. Em produção, esse segredo precisa ser configurado explicitamente.

## Exemplos de uso

Login:

```bash
curl -X POST http://localhost:8080/api/auth/login \
  --data-urlencode email=admin@pachybase.local \
  --data-urlencode password=pachybase123
```

Inspecionar o principal autenticado:

```bash
curl http://localhost:8080/api/auth/me \
  -H "Authorization: Bearer <access-token>"
```

Criar um API token com escopo restrito a uma entidade CRUD:

```bash
curl -X POST http://localhost:8080/api/auth/tokens \
  -H "Authorization: Bearer <access-token>" \
  --data-urlencode name="Deploy Token" \
  --data-urlencode "scopes[0]=entity:system-settings:read"
```

## Mapa de implementação

- `modules/Auth/AuthModule.php`
- `api/Controllers/AuthController.php`
- `auth/AuthService.php`
- `auth/AuthorizationService.php`
- `auth/BearerTokenAuthenticator.php`
- `auth/JwtCodec.php`
- `auth/Middleware/RequireBearerToken.php`
- `auth/UserRepository.php`
- `auth/ApiTokenRepository.php`
- `auth/RefreshTokenRepository.php`

## Validação

A camada de auth está coberta por:

- testes unitários de JWT e autorização por escopo
- testes de integração para login, refresh, emissão de API token e revogação
- testes do kernel HTTP para login, `/api/auth/me`, criação de API token e acesso a CRUD protegido
- suíte completa de regressão do projeto
