@echo off
rem ==========================================================================
rem  Matikan MySQL yang dinyalakan start-wms.bat (shutdown rapi, data aman).
rem  Server aplikasi cukup ditutup jendelanya. Redis (Memurai) dibiarkan (Windows service).
rem ==========================================================================
setlocal
set "MYSQL_DIR=C:\laragon\bin\mysql\mysql-8.4.3-winx64"

netstat -ano | findstr /r /c:":3306 .*LISTENING" >nul
if errorlevel 1 (
    echo MySQL tidak sedang berjalan.
) else (
    echo Mematikan MySQL...
    "%MYSQL_DIR%\bin\mysqladmin.exe" -u root --host=127.0.0.1 --port=3306 shutdown && echo [OK] MySQL dimatikan.
)
pause
