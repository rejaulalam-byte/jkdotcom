@echo off
chcp 65001 >nul
title jorurikaj.com - Local Server (CLOSE THIS WINDOW TO STOP)
cd /d "%~dp0"

if not exist "tools\php\php.exe" (
    echo.
    echo  PHP was not found in "tools\php". Ask Claude to set it up again.
    echo.
    pause
    exit /b 1
)

echo.
echo  ==================================================
echo   jorurikaj.com  -  local test server
echo   Address : http://127.0.0.1:8000
echo   To STOP : close this window (or press Ctrl+C)
echo  ==================================================
echo.

if /i not "%~1"=="nobrowser" start "" "http://127.0.0.1:8000/"

"tools\php\php.exe" -c "tools\php\php.ini" -S 127.0.0.1:8000 -t public

echo.
echo  The server stopped. If you see an error above, another program may
echo  already be using port 8000 - close it and try again.
echo.
pause
