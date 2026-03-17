#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_EXAMPLE_PATH="$ROOT_DIR/.env.example"
ENV_PATH="$ROOT_DIR/.env"
COMPOSE_PATH="$ROOT_DIR/docker/docker-compose.yml"
DOCKERFILE_PATH="$ROOT_DIR/docker/Dockerfile"

step() {
  printf '\n==> %s\n' "$1"
}

fail() {
  printf '%s\n' "$1" >&2
  exit 1
}

assert_command() {
  if ! "$@" >/dev/null 2>&1; then
    fail "$1 is required to run install.sh."
  fi
}

get_config_value() {
  local key="$1"
  local default_value="${2:-}"

  if [[ -n "${CONFIG[$key]:-}" ]]; then
    printf '%s' "${CONFIG[$key]}"
    return 0
  fi

  printf '%s' "$default_value"
}

random_hex() {
  od -An -N16 -tx1 /dev/urandom | tr -d ' \n'
}

declare -A CONFIG

read_env_file() {
  local path="$1"

  [[ -f "$path" ]] || fail ".env file not found at $path."

  while IFS= read -r line || [[ -n "$line" ]]; do
    local trimmed="${line#"${line%%[![:space:]]*}"}"
    trimmed="${trimmed%"${trimmed##*[![:space:]]}"}"

    [[ -z "$trimmed" || "${trimmed:0:1}" == "#" ]] && continue
    [[ "$trimmed" == *"="* ]] || continue

    local key="${trimmed%%=*}"
    local value="${trimmed#*=}"

    key="${key%"${key##*[![:space:]]}"}"
    value="${value#"${value%%[![:space:]]*}"}"
    value="${value%"${value##*[![:space:]]}"}"
    value="${value%\"}"
    value="${value#\"}"
    value="${value%\'}"
    value="${value#\'}"

    [[ -n "$key" ]] && CONFIG["$key"]="$value"
  done <"$path"
}

validate_database_config() {
  DB_DRIVER="$(get_config_value DB_DRIVER | tr '[:upper:]' '[:lower:]')"

  case "$DB_DRIVER" in
    mysql)
      DEFAULT_PORT="3306"
      DEFAULT_HOST="db"
      DB_IMAGE="mysql:8"
      DB_VOLUME_PATH="/var/lib/mysql"
      ;;
    pgsql)
      DEFAULT_PORT="5432"
      DEFAULT_HOST="db"
      DB_IMAGE="postgres:15"
      DB_VOLUME_PATH="/var/lib/postgresql/data"
      ;;
    *)
      fail "Unsupported DB_DRIVER. Use mysql or pgsql."
      ;;
  esac

  DB_HOST="$(get_config_value DB_HOST "$DEFAULT_HOST")"
  DB_PORT="$(get_config_value DB_PORT "$DEFAULT_PORT")"
  DB_DATABASE="$(get_config_value DB_DATABASE)"
  DB_USERNAME="$(get_config_value DB_USERNAME)"
  DB_PASSWORD="$(get_config_value DB_PASSWORD)"

  [[ -n "$DB_DATABASE" ]] || fail "DB_DATABASE is required in the .env file."
  [[ -n "$DB_USERNAME" ]] || fail "DB_USERNAME is required in the .env file."
  [[ -n "$DB_PASSWORD" ]] || fail "DB_PASSWORD is required in the .env file."

  [[ "$DB_HOST" == "$DEFAULT_HOST" ]] || fail "DB_HOST must be \"$DEFAULT_HOST\" when using install.sh."
  [[ "$DB_PORT" == "$DEFAULT_PORT" ]] || fail "DB_PORT must be $DEFAULT_PORT for the $DB_DRIVER Docker container."
}

write_docker_compose_file() {
  local db_environment

  if [[ "$DB_DRIVER" == "mysql" ]]; then
    local root_password
    if [[ "${DB_USERNAME,,}" == "root" ]]; then
      root_password="$DB_PASSWORD"
    else
      root_password="$(random_hex)"
    fi

    db_environment=$(cat <<EOF
      MYSQL_ROOT_PASSWORD: "$root_password"
      MYSQL_DATABASE: "$DB_DATABASE"
EOF
)

    if [[ "${DB_USERNAME,,}" != "root" ]]; then
      db_environment="$db_environment"$'\n'"      MYSQL_USER: \"$DB_USERNAME\""
      db_environment="$db_environment"$'\n'"      MYSQL_PASSWORD: \"$DB_PASSWORD\""
    fi
  else
    db_environment=$(cat <<EOF
      POSTGRES_DB: "$DB_DATABASE"
      POSTGRES_USER: "$DB_USERNAME"
      POSTGRES_PASSWORD: "$DB_PASSWORD"
EOF
)
  fi

  cat >"$COMPOSE_PATH" <<EOF
services:
  web:
    image: nginx:latest
    ports:
      - "8080:80"
    volumes:
      - ../:/var/www/html
      - ./nginx.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - php

  php:
    build:
      context: ..
      dockerfile: docker/Dockerfile
    working_dir: /var/www/html
    volumes:
      - ../:/var/www/html
    depends_on:
      - db

  db:
    image: $DB_IMAGE
    restart: unless-stopped
    environment:
$db_environment
    volumes:
      - db_data:$DB_VOLUME_PATH

volumes:
  db_data:
EOF
}

invoke_docker_compose() {
  docker compose -f "$COMPOSE_PATH" "$@" || fail "Docker Compose command failed: docker compose -f docker/docker-compose.yml $*"
}

bootstrap_database() {
  step "Bootstrapping database schema and seeds"
  invoke_docker_compose exec -T php php scripts/bootstrap-database.php
}

step "Validating required tools"
assert_command docker --version
assert_command docker compose version

[[ -f "$DOCKERFILE_PATH" ]] || fail "Dockerfile not found at $DOCKERFILE_PATH."

if [[ ! -f "$ENV_PATH" ]]; then
  [[ -f "$ENV_EXAMPLE_PATH" ]] || fail ".env.example not found."
  fail "Create .env from .env.example and configure DB_DRIVER, DB_DATABASE, DB_USERNAME, and DB_PASSWORD before running install.sh."
fi

step "Reading project configuration"
read_env_file "$ENV_PATH"
validate_database_config

step "Generating docker/docker-compose.yml"
write_docker_compose_file

step "Building the PHP image with Composer available"
invoke_docker_compose build php

step "Installing Composer dependencies inside the PHP container"
invoke_docker_compose run --rm --no-deps php composer install --no-interaction

step "Starting containers"
invoke_docker_compose up -d

bootstrap_database

printf '\n%s\n' "PachyBase is available at http://localhost:8080"
