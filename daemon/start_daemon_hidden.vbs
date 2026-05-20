Set WshShell = CreateObject("WScript.Shell")
WshShell.Run "d:\xampp\htdocs\CarAlert\daemon\start_daemon.bat", 0, False
Set WshShell = Nothing