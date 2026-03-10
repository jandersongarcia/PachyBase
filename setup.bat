@echo off
echo Installing PachyBase...

if not exist .env (
  echo .env file not found
  exit /b 1
)

if not exist composer.json (
  echo composer.json not found
  exit /b 1
)

composer install

REM Read variables from .env
for /f "tokens=2 delims==" %%a in ('findstr "^DB_DRIVER=" .env') do set DB_DRIVER=%%a
for /f "tokens=2 delims==" %%a in ('findstr "^DB_DATABASE=" .env') do set DB_DATABASE=%%a
for /f "tokens=2 delims==" %%a in ('findstr "^DB_USERNAME=" .env') do set DB_USERNAME=%%a
for /f "tokens=2 delims==" %%a in ('findstr "^DB_PASSWORD=" .env') do set DB_PASSWORD=%%a

if "%DB_DRIVER%"=="mysql" (
  set DB_IMAGE=mysql:8
  set DB_PORT=3306
  set ENV1=MYSQL_ROOT_PASSWORD: %DB_PASSWORD%
  set ENV2=MYSQL_DATABASE: %DB_DATABASE%
) else (
  if "%DB_DRIVER%"=="pgsql" (
    set DB_IMAGE=postgres:15
    set DB_PORT=5432
    set ENV1=POSTGRES_PASSWORD: %DB_PASSWORD%
    set ENV2=POSTGRES_DB: %DB_DATABASE%
  ) else (
    echo Unsupported DB_DRIVER: %DB_DRIVER%
    exit /b 1
  )
)

REM Generate docker-compose.yml
(
echo version: "3.9"
echo.
echo services:
echo   web:
echo     image: nginx:latest
echo     ports:
echo       - "8080:80"
echo     volumes:
echo       - ../:/var/www/html
echo       - ./nginx.conf:/etc/nginx/conf.d/default.conf
echo     depends_on:
echo       - php
echo.
echo   php:
echo     image: php:8.2-fpm
echo     volumes:
echo       - ../:/var/www/html
echo.
echo   db:
echo     image: %DB_IMAGE%
echo     environment:
echo       %ENV1%
echo       %ENV2%
echo     ports:
echo       - "%DB_PORT%:%DB_PORT%"
) > docker\docker-compose.yml

echo Starting Docker containers...
docker compose -f docker\docker-compose.yml up -d

echo PachyBase is running at http://localhost:8080