@echo off
chcp 65001 >nul
echo ==============================================
echo      CarAlert Daemon - Auto Start Setup
echo ==============================================
echo.

echo Creating Windows Task Scheduler task...
echo.

:: Delete existing task if exists
schtasks /delete /tn "CarAlert_Daemon" /f >nul 2>&1

:: Create new task - run on system startup (hidden window)
schtasks /create /tn "CarAlert_Daemon" /tr "d:\xampp\htdocs\CarAlert\daemon\start_daemon_hidden.vbs" /sc onstart /ru SYSTEM /rl highest /f

if %errorlevel% equ 0 (
    echo.
    echo SUCCESS: Task created!
    echo.
    echo The daemon will start automatically on next system boot.
    echo.
    echo You can also start manually:
    echo   - Double-click: d:\xampp\htdocs\CarAlert\daemon\start_daemon.bat
    echo   - Or run from Task Scheduler
    echo.
) else (
    echo.
    echo FAILED: Please run this script as Administrator!
    echo.
)

echo.
pause