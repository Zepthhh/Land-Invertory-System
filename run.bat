@echo off
title DENR CENRO Land Inventory System
color 0A

echo.
echo  =====================================================
echo    DENR CENRO Land Inventory System - Local Server
echo  =====================================================
echo.

:: Check if PHP is available
where php >nul 2>&1
if %errorlevel% neq 0 (
    color 0C
    echo  [ERROR] PHP is not found in your system PATH.
    echo.
    echo  Please make sure PHP is installed. If you have XAMPP,
    echo  add C:\xampp\php to your system environment variables.
    echo.
    pause
    exit /b 1
)

:: Check if port 8000 is already in use
netstat -ano | findstr ":8000 " >nul 2>&1
if %errorlevel% equ 0 (
    echo  [INFO] Port 8000 is already in use. Trying port 8080...
    set PORT=8080
) else (
    set PORT=8000
)

echo  PHP Version:
php --version | findstr /i "PHP"
echo.
echo  Starting server at: http://localhost:%PORT%
echo  Project folder:     %~dp0
echo.
echo  Press Ctrl+C to stop the server.
echo  =====================================================
echo.

:: Wait a moment then open browser
timeout /t 1 /nobreak >nul
start "" "http://localhost:%PORT%"

:: Start PHP built-in server
php -S localhost:%PORT% -t "%~dp0"
