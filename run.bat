@echo off
title Teacher Leave System - Desktop & Mobile Server
cls
echo =========================================================
echo       Teacher Leave and On-Duty Management System
echo =========================================================
echo.
echo [1/3] Starting MySQL Service...
start /B C:\xampp\mysql\bin\mysqld.exe 2>nul
timeout /t 2 > nul

echo.
echo [2/3] Local and Mobile Network URLs:
echo ---------------------------------------------------------
echo   * Open on this PC (Desktop):
echo     http://localhost:8888/
echo.
echo   * Open on ANY Mobile Phone (Connected to same Wi-Fi):
echo     http://10.205.147.141:8888/
echo ---------------------------------------------------------
echo.
echo [3/3] Launching Web Application...
start http://localhost:8888/

echo =========================================================
echo Server is running! Keep this window open.
echo Press Ctrl + C to stop the server anytime.
echo =========================================================
echo.
php -S 0.0.0.0:8888
