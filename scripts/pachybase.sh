#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_PATH="$ROOT_DIR/docker/docker-compose.yml"
SETUP_SCRIPT="$ROOT_DIR/scripts/setup.sh"
PHP_CLI_SCRIPT="$ROOT_DIR/scripts/pachybase.php"
AUTOLOAD_PATH="$ROOT_DIR/vendor/autoload.php"

step_fail() {
  printf '%s\n' "$1" >&2
  exit 1
}

php_available() {
  command -v php >/dev/null 2>&1
}

host_cli_available() {
  php_available && [[ -f "$AUTOLOAD_PATH" ]]
}

print_version() {
  local version_file="$ROOT_DIR/VERSION"
  [[ -f "$version_file" ]] || step_fail "VERSION file was not found."
  cat "$version_file"
}

ensure_env_file() {
  [[ -f "$ROOT_DIR/.env.example" ]] || step_fail ".env.example was not found."

  if [[ -f "$ROOT_DIR/.env" ]]; then
    return 0
  fi

  cp "$ROOT_DIR/.env.example" "$ROOT_DIR/.env"
  printf '%s\n' "Created .env from .env.example."
}

ensure_compose() {
  [[ -f "$COMPOSE_PATH" ]] || step_fail "docker/docker-compose.yml was not found. Run ./pachybase docker:sync first."
}

docker_compose() {
  docker compose -f "$COMPOSE_PATH" "$@"
}

run_host_cli() {
  php "$PHP_CLI_SCRIPT" "$@"
}

run_container_cli() {
  ensure_compose
  docker_compose run --rm php php scripts/pachybase.php "$@"
}

run_container_php_script() {
  ensure_compose
  docker_compose run --rm php php "$@"
}

print_help() {
  cat <<'EOF'
PachyBase CLI

Usage:
  ./pachybase <command> [options]

Main commands:
  install
  start
  stop
  doctor
  status
  env:sync
  env:validate
  app:key
  docker:sync
  docker:up
  docker:down
  docker:logs
  db:setup
  db:migrate
  db:rollback
  db:seed
  db:fresh
  make:module
  make:entity
  make:migration
  make:seed
  make:controller
  make:service
  make:middleware
  make:test
  crud:generate
  auth:install
  openapi:build
  ai:build
  test
EOF
}

if host_cli_available; then
  run_host_cli "$@"
  exit $?
fi

COMMAND="${1:-help}"
shift || true

case "$COMMAND" in
  help|--help|-h)
    print_help
    ;;
  version)
    print_version
    ;;
  env:init|env:sync)
    ensure_env_file
    ;;
  install)
    ensure_env_file
    bash "$SETUP_SCRIPT" install
    run_container_cli auth:install --skip-db
    run_container_php_script scripts/openapi-generate.php
    run_container_php_script scripts/ai-build.php
    ;;
  start|docker:up)
    ensure_compose
    docker_compose up -d
    ;;
  stop|docker:down)
    ensure_compose
    docker_compose down
    ;;
  docker:logs)
    ensure_compose
    docker_compose logs "$@"
    ;;
  docker:sync|docker:install)
    ensure_env_file
    bash "$SETUP_SCRIPT" docker-install
    ;;
  doctor|release:check)
    run_container_php_script scripts/doctor.php "$@"
    ;;
  status)
    run_container_php_script scripts/status.php --inside-docker "$@"
    ;;
  db:setup)
    run_container_php_script scripts/bootstrap-database.php --skip-seeds "$@"
    ;;
  db:migrate)
    run_container_php_script scripts/migrate.php up "$@"
    ;;
  db:rollback)
    run_container_php_script scripts/migrate.php down "$@"
    ;;
  db:seed)
    run_container_php_script scripts/seed.php run "$@"
    ;;
  db:fresh)
    run_container_php_script scripts/db-fresh.php "$@"
    ;;
  entity:list)
    run_container_php_script scripts/inspect-entities.php "$@"
    ;;
  crud:sync)
    run_container_php_script scripts/crud-sync.php "$@"
    ;;
  crud:generate)
    if [[ $# -eq 0 || "$1" == --* ]]; then
      run_container_php_script scripts/crud-sync.php --expose-new "$@"
    else
      run_container_cli "$COMMAND" "$@"
    fi
    ;;
  auth:install)
    if [[ " $* " == *" --skip-db "* ]]; then
      run_container_cli "$COMMAND" "$@"
    else
      run_container_cli auth:install --skip-db
      run_container_php_script scripts/migrate.php up
      run_container_php_script scripts/seed.php run
    fi
    ;;
  openapi:build|openapi:generate)
    run_container_php_script scripts/openapi-generate.php "$@"
    ;;
  ai:build)
    run_container_php_script scripts/ai-build.php "$@"
    ;;
  test)
    ensure_compose
    docker_compose run --rm php vendor/bin/phpunit --testdox "$@"
    ;;
  *)
    run_container_cli "$COMMAND" "$@"
    ;;
esac
