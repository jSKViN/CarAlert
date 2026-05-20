@echo off
chcp 65001 >nul
echo ==============================================
echo      CarAlert Vehicle Monitor Daemon
echo ==============================================
echo.

:: Check PHP availability
php -v >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: PHP not found. Please add PHP to system PATH.
    pause
    exit /b 1
)

:: Switch to daemon directory
cd /d "%~dp0"

:: Start daemon
echo Starting daemon...
echo Log file: daemon.log
echo PID file: daemon.pid
echo.
echo Press Ctrl+C to stop daemon
echo.

php notify_daemon.php

pause