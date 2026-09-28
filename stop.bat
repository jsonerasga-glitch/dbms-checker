@echo off
title DBMS Checker - Stop
cd /d "%~dp0"

echo Stopping DBMS Checker...
docker compose down
echo.
echo DBMS Checker has been stopped. Your saved Settings are kept.
pause
