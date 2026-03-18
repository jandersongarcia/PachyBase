@echo off
setlocal

set "ROOT_DIR=%~dp0"
set "CLI_SCRIPT=%ROOT_DIR%scripts\pachybase.ps1"

if not exist "%CLI_SCRIPT%" (
    echo CLI script not found: "%CLI_SCRIPT%"
    exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%CLI_SCRIPT%" %*
exit /b %ERRORLEVEL%
