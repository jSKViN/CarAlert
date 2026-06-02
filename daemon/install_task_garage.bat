@echo off
chcp 65001 >nul
echo ==============================================
echo   CarAlert 地下车库守护进程 - 开机自启设置
echo ==============================================
echo.

echo Creating Windows Task Scheduler task for Garage...
echo.

:: Delete existing task if exists
schtasks /delete /tn "CarAlert_Daemon_Garage" /f >nul 2>&1

:: Create new task - run on system startup (hidden window)
schtasks /create /tn "CarAlert_Daemon_Garage" /tr "d:\xampp\htdocs\CarAlert\daemon\start_daemon_garage_hidden.vbs" /sc onstart /ru SYSTEM /rl highest /f

if %errorlevel% equ 0 (
    echo.
    echo SUCCESS: Garage task created!
    echo.
    echo The Garage daemon will start automatically on next system boot.
    echo.
    echo You can also start manually:
    echo   - Double-click: d:\xampp\htdocs\CarAlert\daemon\start_daemon_garage.bat
    echo   - Or run from Task Scheduler
    echo.
) else (
    echo.
    echo FAILED: Please run this script as Administrator!
    echo.
)

echo.
pause