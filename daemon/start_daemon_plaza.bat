@echo off
chcp 65001 >nul
echo ==============================================
echo   CarAlert 广场停车场守护进程
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
echo Starting Plaza Parking Daemon...
echo Log file: daemon.log
echo PID file: daemon.pid
echo.
echo Press Ctrl+C to stop daemon
echo.

php notify_daemon.php

pause