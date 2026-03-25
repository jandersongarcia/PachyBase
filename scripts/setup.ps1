param(
    [ValidateSet("install", "docker-install", "compose-sync")]
    [string] $Mode = "install"
)

$ErrorActionPreference = "Stop"

$rootPath = Split-Path -Parent $PSScriptRoot
$envExamplePath = Join-Path $rootPath ".env.example"
$envPath = Join-Path $rootPath ".env"
$composePath = Join-Path $rootPath "docker\docker-compose.yml"
$dockerfilePath = Join-Path $rootPath "docker\Dockerfile"

function Write-Step {
    param([string] $Message)

    Write-Host ""
    Write-Host "==> $Message"
}

function Fail {
    param([string] $Message)

    Write-Error $Message
    exit 1
}

function Assert-Command {
    param(
        [string] $Command,
        [string[]] $Arguments,
        [string] $Label
    )

    $null = & $Command @Arguments 2>$null
    if ($LASTEXITCODE -ne 0) {
        Fail "$Label is required to run install.bat."
    }
}

function Read-EnvFile {
    param([string] $Path)

    if (-not (Test-Path $Path)) {
        Fail ".env file not found at $Path."
    }

    $values = @{}

    foreach ($line in Get-Content $Path) {
        $trimmed = $line.Trim()

        if ($trimmed -eq "" -or $trimmed.StartsWith("#")) {
            continue
        }

        $delimiterIndex = $trimmed.IndexOf("=")
        if ($delimiterIndex -lt 1) {
            continue
        }

        $key = $trimmed.Substring(0, $delimiterIndex).Trim()
        $value = $trimmed.Substring($delimiterIndex + 1).Trim().Trim('"').Trim("'")

        if ($key -ne "") {
            $values[$key] = $value
        }
    }

    return $values
}

function Get-ConfigValue {
    param(
        [hashtable] $Config,
        [string] $Key,
        [string] $Default = ""
    )

    if ($Config.ContainsKey($Key) -and -not [string]::IsNullOrWhiteSpace([string] $Config[$Key])) {
        return [string] $Config[$Key]
    }

    return $Default
}

function Validate-DatabaseConfig {
    param([hashtable] $Config)

    $driver = (Get-ConfigValue -Config $Config -Key "DB_DRIVER").ToLowerInvariant()

    $supported = @{
        "mysql" = @{
            Port = "3306"
            Host = "db"
            Image = "mysql:8"
            VolumePath = "/var/lib/mysql"
        }
        "pgsql" = @{
            Port = "5432"
            Host = "db"
            Image = "postgres:15"
            VolumePath = "/var/lib/postgresql/data"
        }
    }

    if (-not $supported.ContainsKey($driver)) {
        Fail "Unsupported DB_DRIVER. Use mysql or pgsql."
    }

    $resolved = @{
        DB_DRIVER = $driver
        DB_HOST = Get-ConfigValue -Config $Config -Key "DB_HOST" -Default $supported[$driver].Host
        DB_PORT = Get-ConfigValue -Config $Config -Key "DB_PORT" -Default $supported[$driver].Port
        DB_DATABASE = Get-ConfigValue -Config $Config -Key "DB_DATABASE"
        DB_USERNAME = Get-ConfigValue -Config $Config -Key "DB_USERNAME"
        DB_PASSWORD = Get-ConfigValue -Config $Config -Key "DB_PASSWORD"
        DB_IMAGE = [string] $supported[$driver].Image
        DB_VOLUME_PATH = [string] $supported[$driver].VolumePath
    }

    foreach ($field in @("DB_DATABASE", "DB_USERNAME", "DB_PASSWORD")) {
        if ([string]::IsNullOrWhiteSpace($resolved[$field])) {
            Fail "$field is required in the .env file."
        }
    }

    if ($resolved["DB_HOST"] -ne $supported[$driver].Host) {
        Fail "DB_HOST must be `"$($supported[$driver].Host)`" when using install.bat."
    }

    if ($resolved["DB_PORT"] -ne $supported[$driver].Port) {
        Fail "DB_PORT must be $($supported[$driver].Port) for the $driver Docker container."
    }

    return $resolved
}

function Get-DatabaseEnvironmentLines {
    param([hashtable] $Config)

    if ($Config["DB_DRIVER"] -eq "mysql") {
        $lines = @(
            "      MYSQL_ROOT_PASSWORD: `"$($Config["DB_PASSWORD"])`"",
            "      MYSQL_DATABASE: `"$($Config["DB_DATABASE"])`""
        )

        if ($Config["DB_USERNAME"].ToLowerInvariant() -ne "root") {
            $lines += "      MYSQL_USER: `"$($Config["DB_USERNAME"])`""
            $lines += "      MYSQL_PASSWORD: `"$($Config["DB_PASSWORD"])`""
        }

        return $lines
    }

    return @(
        "      POSTGRES_DB: `"$($Config["DB_DATABASE"])`"",
        "      POSTGRES_USER: `"$($Config["DB_USERNAME"])`"",
        "      POSTGRES_PASSWORD: `"$($Config["DB_PASSWORD"])`""
    )
}

function Get-DatabaseVolumeName {
    param([hashtable] $Config)

    return "db_$($Config["DB_DRIVER"])_data"
}

function Write-DockerComposeFile {
    param([hashtable] $Config)

    $databaseEnvironment = Get-DatabaseEnvironmentLines -Config $Config
    $databaseVolume = Get-DatabaseVolumeName -Config $Config
    $compose = @(
        'services:',
        '  web:',
        '    image: nginx:1.27-alpine',
        '    ports:',
        '      - "8080:80"',
        '    volumes:',
        '      - ../:/var/www/html',
        '      - ./nginx.conf:/etc/nginx/conf.d/default.conf',
        '    depends_on:',
        '      - php',
        '',
        '  php:',
        '    build:',
        '      context: ..',
        '      dockerfile: docker/Dockerfile',
        '    working_dir: /var/www/html',
        '    volumes:',
        '      - ../:/var/www/html',
        '    depends_on:',
        '      - db',
        '',
        '  db:',
        "    image: $($Config["DB_IMAGE"])",
        '    restart: unless-stopped',
        '    ports:',
        "      - `"$($Config["DB_PORT"]):$($Config["DB_PORT"])`"",
        '    environment:'
    )

    $compose += $databaseEnvironment
    $compose += @(
        '    volumes:',
        "      - ${databaseVolume}:$($Config["DB_VOLUME_PATH"])",
        '',
        'volumes:',
        "  ${databaseVolume}:",
        ''
    )

    Set-Content -Path $composePath -Value $compose -Encoding ASCII
}

function Invoke-DockerCompose {
    param([string[]] $Arguments)

    & docker compose -f $composePath @Arguments
    if ($LASTEXITCODE -ne 0) {
        Fail "Docker Compose command failed: docker compose -f docker/docker-compose.yml $($Arguments -join ' ')"
    }
}

function Bootstrap-Database {
    Write-Step "Bootstrapping database schema and seeds"
    Invoke-DockerCompose -Arguments @(
        "exec",
        "-T",
        "php",
        "php",
        "scripts/bootstrap-database.php"
    )
}

Write-Step "Validating required tools"
Assert-Command -Command "docker" -Arguments @("--version") -Label "Docker"
Assert-Command -Command "docker" -Arguments @("compose", "version") -Label "Docker Compose"

if (-not (Test-Path $dockerfilePath)) {
    Fail "Dockerfile not found at $dockerfilePath."
}

if (-not (Test-Path $envPath)) {
    if (-not (Test-Path $envExamplePath)) {
        Fail ".env.example not found."
    }

    Fail "Create .env from .env.example and configure DB_DRIVER, DB_DATABASE, DB_USERNAME, and DB_PASSWORD before running install.bat."
}

Write-Step "Reading project configuration"
$config = Validate-DatabaseConfig -Config (Read-EnvFile -Path $envPath)

Write-Step "Generating docker/docker-compose.yml"
Write-DockerComposeFile -Config $config

if ($Mode -eq "compose-sync") {
    exit 0
}

Write-Step "Building the PHP image with Composer available"
Invoke-DockerCompose -Arguments @("build", "php")

Write-Step "Installing Composer dependencies inside the PHP container"
Invoke-DockerCompose -Arguments @(
    "run",
    "--rm",
    "--no-deps",
    "php",
    "composer",
    "install",
    "--no-interaction"
)

if ($Mode -eq "docker-install") {
    Write-Host ""
    Write-Host "Docker environment prepared successfully."
    exit 0
}

Write-Step "Starting containers"
Invoke-DockerCompose -Arguments @("up", "-d")

Bootstrap-Database

Write-Host ""
Write-Host "PachyBase is available at http://localhost:8080"
