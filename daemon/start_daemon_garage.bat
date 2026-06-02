@echo off
chcp 65001 >nul
echo ==============================================
echo   CarAlert 地下车库守护进程
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
echo Starting Garage Parking Daemon...
echo Log file: daemon_db2.log
echo PID file: daemon_db2.pid
echo.
echo Press Ctrl+C to stop daemon
echo.

php notify_daemon.php -2

pause
