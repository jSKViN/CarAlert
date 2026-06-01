@echo off
chcp 65001 >nul
echo ==============================================
echo   CarAlert 广场停车场守护进程 - 清理脚本
echo ==============================================
echo.

echo Cleaning Plaza daemon runtime files...
echo.

:: Switch to daemon directory
cd /d "%~dp0"

:: Kill running daemon process if exists
if exist daemon.pid (
    set /p pid=<daemon.pid
    echo [INFO] Killing process %pid%...
    taskkill /F /PID %pid% >nul 2>&1
    if %errorlevel% equ 0 (
        echo [OK] Process killed successfully
    ) else (
        echo [WARN] Process not found or already stopped
    )
)

:: Delete PID file
if exist daemon.pid (
    echo [INFO] Deleting PID file...
    del daemon.pid
    echo [OK] PID file deleted
)

:: Delete log file
if exist daemon.log (
    echo [INFO] Deleting log file...
    del daemon.log
    echo [OK] Log file deleted
)

echo.
echo [INFO] Plaza daemon cleanup completed!
echo.
pause