@echo off
chcp 65001 >nul
echo ==============================================
echo     CarAlert Daemon - Auto Start Uninstall
echo ==============================================
echo.

echo Deleting Windows Task Scheduler task...
echo.

:: Delete existing task
schtasks /delete /tn "CarAlert_Daemon" /f

if %errorlevel% equ 0 (
    echo.
    echo SUCCESS: Task deleted!
    echo.
    echo The daemon will no longer start automatically on system boot.
    echo.
) else (
    echo.
    echo FAILED: Task not found or permission denied!
    echo Please run this script as Administrator.
    echo.
)

echo.
pause