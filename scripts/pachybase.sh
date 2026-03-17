#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_PATH="$ROOT_DIR/docker/docker-compose.yml"
SETUP_SCRIPT="$ROOT_DIR/scripts/setup.sh"

step_fail() {
  printf '%s\n' "$1" >&2
  exit 1
}

ensure_env() {
  if [[ -f "$ROOT_DIR/.env" ]]; then
    return 0
  fi

  env_init
}

ensure_compose() {
  [[ -f "$COMPOSE_PATH" ]] || step_fail "docker/docker-compose.yml was not found. Run ./pachybase docker:install first."
}

docker_compose() {
  docker compose -f "$COMPOSE_PATH" "$@"
}

run_php_service() {
  ensure_compose
  docker_compose run --rm php "$@"
}

env_init() {
  local force="${1:-}"

  [[ -f "$ROOT_DIR/.env.example" ]] || step_fail ".env.example was not found."

  if [[ -f "$ROOT_DIR/.env" && "$force" != "--force" ]]; then
    printf '%s\n' ".env already exists. Use --force to overwrite it."
    return 0
  fi

  cp "$ROOT_DIR/.env.example" "$ROOT_DIR/.env"
  printf '%s\n' "Created .env from .env.example."
}

print_help() {
  cat <<'EOF'
PachyBase CLI

Usage:
  ./pachybase <command> [options]

Commands:
  version
  install
  env:init
  doctor
  release:check
  docker:install
  docker:up
  docker:down
  migrate
  migrate:rollback
  seed
  entity:list
  crud:sync
  crud:generate
  openapi:generate
  test
EOF
}

COMMAND="${1:-help}"
shift || true

case "$COMMAND" in
  help|--help|-h)
    print_help
    ;;
  version)
    php "$ROOT_DIR/scripts/version.php"
    ;;
  install)
    ensure_env
    bash "$SETUP_SCRIPT" install
    ;;
  env:init)
    env_init "${1:-}"
    ;;
  doctor|release:check)
    php "$ROOT_DIR/scripts/doctor.php" "$@"
    ;;
  docker:install)
    [[ -f "$ROOT_DIR/.env" ]] || step_fail "Missing .env. Run ./pachybase env:init first."
    bash "$SETUP_SCRIPT" docker-install
    ;;
  docker:up)
    ensure_compose
    docker_compose up -d
    ;;
  docker:down)
    ensure_compose
    docker_compose down
    ;;
  migrate)
    run_php_service php scripts/migrate.php up "$@"
    ;;
  migrate:rollback)
    run_php_service php scripts/migrate.php down "$@"
    ;;
  seed)
    run_php_service php scripts/seed.php run "$@"
    ;;
  entity:list)
    run_php_service php scripts/inspect-entities.php "$@"
    ;;
  crud:sync|crud:generate)
    run_php_service php scripts/crud-sync.php "$@"
    ;;
  openapi:generate)
    run_php_service php scripts/openapi-generate.php "$@"
    ;;
  test)
    run_php_service vendor/bin/phpunit --testdox "$@"
    ;;
  *)
    step_fail "Unknown command \"$COMMAND\". Run ./pachybase help to list the available commands."
    ;;
esac
