@echo off
title DBMS Checker - Start
cd /d "%~dp0"

echo Checking that Docker Desktop is running...
docker info >nul 2>&1
if errorlevel 1 (
    echo.
    echo [ERROR] Docker Desktop is not running.
    echo Open Docker Desktop from the Start menu, wait until it says "Engine running",
    echo then double-click start.bat again.
    echo.
    pause
    exit /b 1
)

echo Starting DBMS Checker (the first time can take a few minutes)...
docker compose up -d --build
if errorlevel 1 (
    echo.
    echo [ERROR] Could not start DBMS Checker. See the message above.
    pause
    exit /b 1
)

echo.
echo DBMS Checker is running.
echo   Admin dashboard: http://localhost:8080/
echo   Student portal:  http://localhost:8080/student-portal/
echo.
start "" http://localhost:8080/
pause
