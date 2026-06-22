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

:: Get Local IP Address for network sharing
for /f "tokens=2 delims=:" %%A in ('ipconfig ^| findstr /i "IPv4 Address"') do (
    set LOCAL_IP=%%A
)
:: Remove leading spaces from IP
set LOCAL_IP=%LOCAL_IP: =%

echo  PHP Version:
php --version | findstr /i "PHP"
echo.
echo  Starting server at: http://localhost:%PORT%
if defined LOCAL_IP (
    echo  Network URL:        http://%LOCAL_IP%:%PORT%
)
echo  Project folder:     %~dp0
echo.
echo  Press Ctrl+C to stop the server.
echo  =====================================================
echo.

:: Wait a moment then open browser on the host machine
timeout /t 1 /nobreak >nul
start "" "http://localhost:%PORT%"

:: Start PHP built-in server on 0.0.0.0 to allow access from other devices on Wi-Fi
php -S 0.0.0.0:%PORT% -t "%~dp0"
