@echo off
chcp 65001 >nul
echo ==============================================
echo   CarAlert 广场停车场守护进程 - 开机自启设置
echo ==============================================
echo.

echo Creating Windows Task Scheduler task for Plaza...
echo.

:: Delete existing task if exists
schtasks /delete /tn "CarAlert_Daemon_Plaza" /f >nul 2>&1

:: Create new task - run on system startup (hidden window)
schtasks /create /tn "CarAlert_Daemon_Plaza" /tr "d:\xampp\htdocs\CarAlert\daemon\start_daemon_plaza_hidden.vbs" /sc onstart /ru SYSTEM /rl highest /f

if %errorlevel% equ 0 (
    echo.
    echo SUCCESS: Plaza task created!
    echo.
    echo The Plaza daemon will start automatically on next system boot.
    echo.
    echo You can also start manually:
    echo   - Double-click: d:\xampp\htdocs\CarAlert\daemon\start_daemon_plaza.bat
    echo   - Or run from Task Scheduler
    echo.
) else (
    echo.
    echo FAILED: Please run this script as Administrator!
    echo.
)

echo.
pause