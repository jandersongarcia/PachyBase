@echo off
setlocal

set "ROOT_DIR=%~dp0"
set "SETUP_SCRIPT=%ROOT_DIR%scripts\setup.ps1"

if not exist "%SETUP_SCRIPT%" (
    echo Setup script not found: "%SETUP_SCRIPT%"
    exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%SETUP_SCRIPT%"
set "EXIT_CODE=%ERRORLEVEL%"

if not "%EXIT_CODE%"=="0" (
    echo.
    echo Installation failed with exit code %EXIT_CODE%.
    exit /b %EXIT_CODE%
)

echo.
echo PachyBase installation finished successfully.
exit /b 0
