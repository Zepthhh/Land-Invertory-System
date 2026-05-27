@echo off
echo ===================================================
echo   DENR CENRO Land Inventory System - Local Server
echo ===================================================
echo.
echo Starting local development server...
echo Point your browser to http://localhost:8000
echo.
echo Press Ctrl+C in this command window to stop the server.
echo.
start "" "http://localhost:8000"
php -S localhost:8000
