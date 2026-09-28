@echo off
title DBMS Checker - Allow phones and other PCs
cd /d "%~dp0"

rem Adding a firewall rule needs Administrator rights; ask for them if missing.
net session >nul 2>&1
if errorlevel 1 (
    echo Asking for Administrator permission...
    powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

echo Allowing other devices on this network to open DBMS Checker (port 8080)...
netsh advfirewall firewall delete rule name="DBMS Checker (port 8080)" >nul 2>&1
netsh advfirewall firewall add rule name="DBMS Checker (port 8080)" dir=in action=allow protocol=TCP localport=8080 profile=private,domain,public
if errorlevel 1 (
    echo.
    echo [ERROR] Could not add the firewall rule.
    pause
    exit /b 1
)

echo.
echo Done. On your phone or another PC, open one of these addresses:
for /f "usebackq delims=" %%i in (`powershell -NoProfile -Command "Get-NetIPConfiguration | Where-Object { $_.IPv4DefaultGateway } | ForEach-Object { $_.IPv4Address.IPAddress }"`) do (
    echo   http://%%i:8080/
    echo   http://%%i:8080/student-portal/
)
echo.
echo The phone must be on the same Wi-Fi/network as this computer.
pause
