param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]] $Arguments
)

$ErrorActionPreference = "Stop"

$rootPath = Split-Path -Parent $PSScriptRoot
$composePath = Join-Path $rootPath "docker\docker-compose.yml"
$setupScript = Join-Path $rootPath "scripts\setup.ps1"

function Fail {
    param([string] $Message)

    Write-Error $Message
    exit 1
}

function Ensure-Compose {
    if (-not (Test-Path $composePath)) {
        Fail 'docker/docker-compose.yml was not found. Run ".\pachybase.bat docker:install" first.'
    }
}

function Invoke-DockerCompose {
    param([string[]] $ComposeArguments)

    & docker compose -f $composePath @ComposeArguments
    if ($LASTEXITCODE -ne 0) {
        exit $LASTEXITCODE
    }
}

function Invoke-PhpService {
    param([string[]] $CommandArguments)

    Ensure-Compose
    Invoke-DockerCompose -ComposeArguments (@("run", "--rm", "php") + $CommandArguments)
}

function Invoke-EnvInit {
    param([switch] $Force)

    $envExamplePath = Join-Path $rootPath ".env.example"
    $envPath = Join-Path $rootPath ".env"

    if (-not (Test-Path $envExamplePath)) {
        Fail ".env.example was not found."
    }

    if ((Test-Path $envPath) -and -not $Force) {
        Write-Host ".env already exists. Use --force to overwrite it."
        return
    }

    Copy-Item -Force:$Force.IsPresent $envExamplePath $envPath
    Write-Host "Created .env from .env.example."
}

function Show-Help {
    @'
PachyBase CLI

Usage:
  .\pachybase.bat <command> [options]

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
'@ | Write-Host
}

$command = if ($Arguments.Count -gt 0) { $Arguments[0] } else { "help" }
$rest = if ($Arguments.Count -gt 1) { $Arguments[1..($Arguments.Count - 1)] } else { @() }

switch ($command) {
    "help" { Show-Help }
    "--help" { Show-Help }
    "-h" { Show-Help }
    "version" {
        & php (Join-Path $rootPath "scripts\version.php")
        exit $LASTEXITCODE
    }
    "install" {
        if (-not (Test-Path (Join-Path $rootPath ".env"))) {
            Invoke-EnvInit
        }

        & powershell -NoProfile -ExecutionPolicy Bypass -File $setupScript -Mode install
        exit $LASTEXITCODE
    }
    "env:init" {
        Invoke-EnvInit -Force:($rest -contains "--force")
    }
    "doctor" {
        & php (Join-Path $rootPath "scripts\doctor.php") @rest
        exit $LASTEXITCODE
    }
    "release:check" {
        & php (Join-Path $rootPath "scripts\doctor.php") @rest
        exit $LASTEXITCODE
    }
    "docker:install" {
        if (-not (Test-Path (Join-Path $rootPath ".env"))) {
            Fail 'Missing .env. Run ".\pachybase.bat env:init" first.'
        }

        & powershell -NoProfile -ExecutionPolicy Bypass -File $setupScript -Mode "docker-install"
        exit $LASTEXITCODE
    }
    "docker:up" {
        Ensure-Compose
        Invoke-DockerCompose -ComposeArguments @("up", "-d")
    }
    "docker:down" {
        Ensure-Compose
        Invoke-DockerCompose -ComposeArguments @("down")
    }
    "migrate" {
        Invoke-PhpService -CommandArguments (@("php", "scripts/migrate.php", "up") + $rest)
    }
    "migrate:rollback" {
        Invoke-PhpService -CommandArguments (@("php", "scripts/migrate.php", "down") + $rest)
    }
    "seed" {
        Invoke-PhpService -CommandArguments (@("php", "scripts/seed.php", "run") + $rest)
    }
    "entity:list" {
        Invoke-PhpService -CommandArguments (@("php", "scripts/inspect-entities.php") + $rest)
    }
    "crud:sync" {
        Invoke-PhpService -CommandArguments (@("php", "scripts/crud-sync.php") + $rest)
    }
    "crud:generate" {
        Invoke-PhpService -CommandArguments (@("php", "scripts/crud-sync.php") + $rest)
    }
    "openapi:generate" {
        Invoke-PhpService -CommandArguments (@("php", "scripts/openapi-generate.php") + $rest)
    }
    "test" {
        Invoke-PhpService -CommandArguments (@("vendor/bin/phpunit", "--testdox") + $rest)
    }
    default {
        Fail "Unknown command `"$command`". Run .\pachybase.bat help to list the available commands."
    }
}
