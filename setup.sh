#!/bin/bash

echo "Installing PachyBase..."

if [ ! -f .env ]; then
  echo ".env file not found"
  exit 1
fi

if [ ! -f composer.json ]; then
  echo "composer.json not found"
  exit 1
fi

composer install

# Read variables from .env
DB_DRIVER=$(grep '^DB_DRIVER=' .env | cut -d '=' -f2)
DB_DATABASE=$(grep '^DB_DATABASE=' .env | cut -d '=' -f2)
DB_USERNAME=$(grep '^DB_USERNAME=' .env | cut -d '=' -f2)
DB_PASSWORD=$(grep '^DB_PASSWORD=' .env | cut -d '=' -f2)

if [ "$DB_DRIVER" = "mysql" ]; then
  DB_IMAGE="mysql:8"
  DB_PORT="3306"
  DB_ENV="MYSQL_ROOT_PASSWORD=$DB_PASSWORD\n      MYSQL_DATABASE=$DB_DATABASE"
elif [ "$DB_DRIVER" = "pgsql" ]; then
  DB_IMAGE="postgres:15"
  DB_PORT="5432"
  DB_ENV="POSTGRES_PASSWORD=$DB_PASSWORD\n      POSTGRES_DB=$DB_DATABASE"
else
  echo "Unsupported DB_DRIVER: $DB_DRIVER"
  exit 1
fi

# Generate docker-compose.yml
cat > docker/docker-compose.yml <<EOF
version: "3.9"

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
    image: php:8.2-fpm
    volumes:
      - ../:/var/www/html

  db:
    image: $DB_IMAGE
    environment:
      $DB_ENV
    ports:
      - "$DB_PORT:$DB_PORT"
EOF

echo "Starting Docker containers..."
docker compose -f docker/docker-compose.yml up -d

echo "PachyBase is running at http://localhost:8080"