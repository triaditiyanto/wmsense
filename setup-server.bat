@echo off
rem ==========================================================================
rem  WMSense - SETUP SERVER (sekali saja, klik kanan > Run as administrator)
rem    setup-server.bat                    -> akses lewat IP server (http://IP-SERVER)
rem    setup-server.bat wms.contoh.com     -> pakai nama domain
rem    setup-server.bat wms.contoh.com https -> domain di belakang HTTPS (Cloudflare Tunnel / proxy SSL)
rem  Yang dikerjakan (detail di setup-server.ps1): pasang Laragon bila belum ada, unduh Redis & Composer,
rem  MySQL + Apache + Redis jadi Windows Service (hidup otomatis saat server restart), user MySQL khusus
rem  aplikasi, .env mode produksi, firewall, backup harian 01:00, lalu migrasi database (start-wms.bat prepare).
rem ==========================================================================
setlocal
title WMSense - setup server
cd /d "%~dp0"

set "DOMAIN=%~1"
set "HTTPSFLAG="
if /i "%~2"=="https" set "HTTPSFLAG=-Https"
if /i "%~1"=="https" ( set "DOMAIN=" & set "HTTPSFLAG=-Https" )

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup-server.ps1" -Domain "%DOMAIN%" %HTTPSFLAG%
exit /b %errorlevel%
