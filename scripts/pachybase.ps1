param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]] $Arguments
)

$ErrorActionPreference = "Stop"

$rootPath = Split-Path -Parent $PSScriptRoot
$composePath = Join-Path $rootPath "docker\docker-compose.yml"
$setupScript = Join-Path $rootPath "scripts\setup.ps1"
$phpCliScript = Join-Path $rootPath "scripts\pachybase.php"
$autoloadPath = Join-Path $rootPath "vendor\autoload.php"

function Fail {
    param([string] $Message)

    Write-Error $Message
    exit 1
}

function Test-PhpAvailable {
    return $null -ne (Get-Command php -ErrorAction SilentlyContinue)
}

function Test-HostCliAvailable {
    return (Test-PhpAvailable) -and (Test-Path $autoloadPath)
}

function Show-Version {
    $versionPath = Join-Path $rootPath "VERSION"

    if (-not (Test-Path $versionPath)) {
        Fail "VERSION file was not found."
    }

    Get-Content $versionPath | Write-Host
}

function Ensure-EnvFile {
    $envExamplePath = Join-Path $rootPath ".env.example"
    $envPath = Join-Path $rootPath ".env"

    if (-not (Test-Path $envExamplePath)) {
        Fail ".env.example was not found."
    }

    if (Test-Path $envPath) {
        return
    }

    Copy-Item $envExamplePath $envPath
    Write-Host "Created .env from .env.example."
}

function Ensure-Compose {
    if (-not (Test-Path $composePath)) {
        Fail 'docker/docker-compose.yml was not found. Run ".\pachybase.bat docker:sync" first.'
    }
}

function Invoke-DockerCompose {
    param([string[]] $ComposeArguments)

    Ensure-Compose
    & docker compose -f $composePath @ComposeArguments
    if ($LASTEXITCODE -ne 0) {
        exit $LASTEXITCODE
    }
}

function Invoke-HostCli {
    & php $phpCliScript @Arguments
    exit $LASTEXITCODE
}

function Invoke-ContainerCli {
    param([string[]] $CliArguments)

    Ensure-Compose
    Invoke-DockerCompose -ComposeArguments (@("run", "--rm", "php", "php", "scripts/pachybase.php") + $CliArguments)
}

function Invoke-ContainerPhpScript {
    param([string[]] $ScriptArguments)

    Ensure-Compose
    Invoke-DockerCompose -ComposeArguments (@("run", "--rm", "php", "php") + $ScriptArguments)
}

function Show-Help {
@'
PachyBase CLI

Usage:
  .\pachybase.bat <command> [options]

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
'@ | Write-Host
}

if (Test-HostCliAvailable) {
    Invoke-HostCli
}

$command = if ($Arguments.Count -gt 0) { $Arguments[0] } else { "help" }
$rest = if ($Arguments.Count -gt 1) { $Arguments[1..($Arguments.Count - 1)] } else { @() }

switch ($command) {
    "help" { Show-Help }
    "--help" { Show-Help }
    "-h" { Show-Help }
    "version" { Show-Version }
    "env:init" { Ensure-EnvFile }
    "env:sync" { Ensure-EnvFile }
    "install" {
        Ensure-EnvFile
        & powershell -NoProfile -ExecutionPolicy Bypass -File $setupScript -Mode install
        if ($LASTEXITCODE -ne 0) {
            exit $LASTEXITCODE
        }

        Invoke-ContainerCli -CliArguments @("auth:install", "--skip-db")
        Invoke-ContainerPhpScript -ScriptArguments @("scripts/openapi-generate.php")
        Invoke-ContainerPhpScript -ScriptArguments @("scripts/ai-build.php")
    }
    "start" { Invoke-DockerCompose -ComposeArguments @("up", "-d") }
    "docker:up" { Invoke-DockerCompose -ComposeArguments @("up", "-d") }
    "stop" { Invoke-DockerCompose -ComposeArguments @("down") }
    "docker:down" { Invoke-DockerCompose -ComposeArguments @("down") }
    "docker:logs" { Invoke-DockerCompose -ComposeArguments (@("logs") + $rest) }
    "docker:sync" {
        Ensure-EnvFile
        & powershell -NoProfile -ExecutionPolicy Bypass -File $setupScript -Mode "docker-install"
        exit $LASTEXITCODE
    }
    "docker:install" {
        Ensure-EnvFile
        & powershell -NoProfile -ExecutionPolicy Bypass -File $setupScript -Mode "docker-install"
        exit $LASTEXITCODE
    }
    "doctor" { Invoke-ContainerPhpScript -ScriptArguments (@("scripts/doctor.php") + $rest) }
    "release:check" { Invoke-ContainerPhpScript -ScriptArguments (@("scripts/doctor.php") + $rest) }
    "status" { Invoke-ContainerPhpScript -ScriptArguments (@("scripts/status.php", "--inside-docker") + $rest) }
    "db:setup" { Invoke-ContainerPhpScript -ScriptArguments (@("scripts/bootstrap-database.php", "--skip-seeds") + $rest) }
    "db:migrate" { Invoke-ContainerPhpScript -ScriptArguments (@("scripts/migrate.php", "up") + $rest) }
    "db:rollback" { Invoke-ContainerPhpScript -ScriptArguments (@("scripts/migrate.php", "down") + $rest) }
    "db:seed" { Invoke-ContainerPhpScript -ScriptArguments (@("scripts/seed.php", "run") + $rest) }
    "db:fresh" { Invoke-ContainerPhpScript -ScriptArguments (@("scripts/db-fresh.php") + $rest) }
    "entity:list" { Invoke-ContainerPhpScript -ScriptArguments (@("scripts/inspect-entities.php") + $rest) }
    "crud:sync" { Invoke-ContainerPhpScript -ScriptArguments (@("scripts/crud-sync.php") + $rest) }
    "crud:generate" {
        if ($rest.Count -eq 0 -or $rest[0].StartsWith("--")) {
            Invoke-ContainerPhpScript -ScriptArguments (@("scripts/crud-sync.php", "--expose-new") + $rest)
        } else {
            Invoke-ContainerCli -CliArguments (@($command) + $rest)
        }
    }
    "auth:install" {
        if ($rest -contains "--skip-db") {
            Invoke-ContainerCli -CliArguments (@($command) + $rest)
        } else {
            Invoke-ContainerCli -CliArguments @("auth:install", "--skip-db")
            Invoke-ContainerPhpScript -ScriptArguments @("scripts/migrate.php", "up")
            Invoke-ContainerPhpScript -ScriptArguments @("scripts/seed.php", "run")
        }
    }
    "openapi:build" { Invoke-ContainerPhpScript -ScriptArguments (@("scripts/openapi-generate.php") + $rest) }
    "openapi:generate" { Invoke-ContainerPhpScript -ScriptArguments (@("scripts/openapi-generate.php") + $rest) }
    "ai:build" { Invoke-ContainerPhpScript -ScriptArguments (@("scripts/ai-build.php") + $rest) }
    "test" { Invoke-DockerCompose -ComposeArguments (@("run", "--rm", "php", "vendor/bin/phpunit", "--testdox") + $rest) }
    default {
        Invoke-ContainerCli -CliArguments (@($command) + $rest)
    }
}
