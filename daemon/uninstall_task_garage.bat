@echo off
chcp 65001 >nul
echo ==============================================
echo   CarAlert 地下车库守护进程 - 开机自启卸载
echo ==============================================
echo.

echo Deleting Windows Task Scheduler task for Garage...
echo.

:: Delete existing task
schtasks /delete /tn "CarAlert_Daemon_Garage" /f

if %errorlevel% equ 0 (
    echo.
    echo SUCCESS: Garage task deleted!
    echo.
    echo The Garage daemon will no longer start automatically on system boot.
    echo.
) else (
    echo.
    echo FAILED: Task not found or permission denied!
    echo Please run this script as Administrator.
    echo.
)

echo.
pause