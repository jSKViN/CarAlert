@echo off
chcp 65001 >nul
echo ==============================================
echo   CarAlert 广场停车场守护进程 - 开机自启卸载
echo ==============================================
echo.

echo Deleting Windows Task Scheduler task for Plaza...
echo.

:: Delete existing task
schtasks /delete /tn "CarAlert_Daemon_Plaza" /f

if %errorlevel% equ 0 (
    echo.
    echo SUCCESS: Plaza task deleted!
    echo.
    echo The Plaza daemon will no longer start automatically on system boot.
    echo.
) else (
    echo.
    echo FAILED: Task not found or permission denied!
    echo Please run this script as Administrator.
    echo.
)

echo.
pause