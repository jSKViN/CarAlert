@echo off
chcp 65001 >nul
echo ==============================================
echo   CarAlert 地下车库守护进程 - 清理脚本
echo ==============================================
echo.

echo Cleaning Garage daemon runtime files...
echo.

:: Switch to daemon directory
cd /d "%~dp0"

:: Kill running daemon process if exists
if exist daemon_db2.pid (
    set /p pid=<daemon_db2.pid
    echo [INFO] Killing process %pid%...
    taskkill /F /PID %pid% >nul 2>&1
    if %errorlevel% equ 0 (
        echo [OK] Process killed successfully
    ) else (
        echo [WARN] Process not found or already stopped
    )
)

:: Delete PID file
if exist daemon_db2.pid (
    echo [INFO] Deleting PID file...
    del daemon_db2.pid
    echo [OK] PID file deleted
)

:: Delete log file
if exist daemon_db2.log (
    echo [INFO] Deleting log file...
    del daemon_db2.log
    echo [OK] Log file deleted
)

echo.
echo [INFO] Garage daemon cleanup completed!
echo.
pause