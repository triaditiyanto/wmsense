@echo off
rem ==========================================================================
rem  Backup SEMUA database WMSense (database utama .env, wms_central, dan semua database site wms_*)
rem  ke database\backup\wms-TANGGAL-JAM.sql. File terbaru di folder itu otomatis di-import oleh
rem  start-wms.bat di komputer/server baru (bila database belum ada).
rem ==========================================================================
setlocal EnableDelayedExpansion
cd /d "%~dp0"
set "MYSQL_DIR=C:\laragon\bin\mysql\mysql-8.4.3-winx64"
if not exist "%MYSQL_DIR%\bin\mysqldump.exe" (
    for /d %%d in ("C:\laragon\bin\mysql\mysql-8*") do if exist "%%d\bin\mysqldump.exe" set "MYSQL_DIR=%%d"
)
set "DBUSER=root"
for /f "tokens=1,* delims==" %%a in ('findstr /r /c:"^DB_USERNAME=" ".env"') do set "DBUSER=%%b"
set "DBPASS="
for /f "tokens=1,* delims==" %%a in ('findstr /r /c:"^DB_PASSWORD=" ".env"') do set "DBPASS=%%b"
set "AUTH=-u %DBUSER% --host=127.0.0.1 --port=3306"
if defined DBPASS set "AUTH=%AUTH% -p%DBPASS%"

if not exist "database\backup" mkdir "database\backup"
for /f "tokens=2 delims==" %%a in ('wmic os get localdatetime /value 2^>nul') do set "DT=%%a"
if not defined DT set "DT=%date:~-4%%date:~3,2%%date:~0,2%%time:~0,2%%time:~3,2%"
set "STAMP=%DT:~0,8%-%DT:~8,4%"
set "FILE=database\backup\wms-%STAMP%.sql"

set "DBS="
for /f "delims=" %%d in ('%MYSQL_DIR%\bin\mysql.exe %AUTH% -N -e "SHOW DATABASES LIKE 'wms%%'"') do set "DBS=!DBS! %%d"
if not defined DBS ( echo [X] Tidak ada database wms*. & pause & exit /b 1 )
echo [..] Backup database:%DBS%
"%MYSQL_DIR%\bin\mysqldump.exe" %AUTH% --single-transaction --routines --triggers --databases %DBS% > "%FILE%"
if errorlevel 1 ( echo [X] Backup gagal. & del "%FILE%" 2>nul & pause & exit /b 1 )
for %%f in ("%FILE%") do echo [OK] Tersimpan: %FILE% ^(%%~zf byte^)
pause
