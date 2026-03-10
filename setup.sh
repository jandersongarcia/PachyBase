#!/bin/bash

echo "Installing PachyBase..."

if [ ! -f composer.json ]; then
  echo "composer.json not found"
  exit 1
fi

composer install

echo "Starting Docker containers..."
docker compose -f docker/docker-compose.yml up -d

echo "PachyBase is running at http://localhost:8080"