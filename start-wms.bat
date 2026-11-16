@echo off
rem ==========================================================================
rem  WMSense - SEKALI KLIK: siapkan semua kebutuhan lalu jalankan server.
rem    start-wms.bat        -> hanya dari komputer ini : http://127.0.0.1:8000
rem    start-wms.bat hp     -> bisa dibuka dari HP (Wi-Fi sama): http://IP-KOMPUTER:8000
rem    start-wms.bat prepare -> hanya siapkan (langkah 1-5) lalu selesai; dipakai setup-server.bat
rem  Langkah otomatis:
rem    1. cek PHP (Laragon), MySQL dinyalakan bila belum jalan, Redis (Memurai) dicek
rem    2. vendor/ belum ada  -> composer install (unduh Laravel & library; butuh internet, sekali saja)
rem    3. .env belum ada     -> salin dari .env.example + buat APP_KEY
rem    4. database belum ada -> import backup TERBARU di database\backup\*.sql (dibuat backup-wms.bat),
rem                             kalau tidak ada backup -> migrate + data demo. Sudah ada -> migrate (update skema)
rem    5. database pusat portal (wms_central) disiapkan: php artisan portal:install; semua site disamakan (portal:upgrade)
rem    6. php artisan serve. TUTUP jendela ini = aplikasi berhenti. Matikan MySQL: stop-wms.bat
rem  Lokasi Laragon berbeda? ubah PHP / MYSQL_DIR / COMPOSER di bawah.
rem ==========================================================================
setlocal EnableDelayedExpansion
title WMSense - server aplikasi
cd /d "%~dp0"

set "PHP=C:\laragon\bin\php\php-8.3.36-Win32-vs16-x64\php.exe"
set "MYSQL_DIR=C:\laragon\bin\mysql\mysql-8.4.3-winx64"
set "COMPOSER=C:\laragon\bin\composer\composer.phar"
set "PORT=8000"
set "HOST=127.0.0.1"
if /i "%~1"=="hp" set "HOST=0.0.0.0"

if not exist "%PHP%" (
    for /d %%d in ("C:\laragon\bin\php\php-8*") do if exist "%%d\php.exe" set "PHP=%%d\php.exe"
)
if not exist "%PHP%" (
    echo [X] PHP tidak ditemukan. Pasang Laragon ^(https://laragon.org^) atau ubah variabel PHP di file ini.
    pause & exit /b 1
)
if not exist "%MYSQL_DIR%\bin\mysqld.exe" (
    for /d %%d in ("C:\laragon\bin\mysql\mysql-8*") do if exist "%%d\bin\mysqld.exe" set "MYSQL_DIR=%%d"
)
if not exist "%MYSQL_DIR%\bin\mysqld.exe" (
    echo [X] MySQL tidak ditemukan di %MYSQL_DIR%. Ubah variabel MYSQL_DIR di file ini.
    pause & exit /b 1
)
echo [OK] PHP   : %PHP%
echo [OK] MySQL : %MYSQL_DIR%

rem ---------------------------------------------------------------- 1. MySQL
netstat -ano | findstr /r /c:":3306 .*LISTENING" >nul
if errorlevel 1 (
    echo [..] Menyalakan MySQL...
    start "WMSense - MySQL (jangan ditutup)" /min "%MYSQL_DIR%\bin\mysqld.exe" --defaults-file="%MYSQL_DIR%\my.ini"
    for /l %%i in (1,1,30) do (
        netstat -ano | findstr /r /c:":3306 .*LISTENING" >nul && goto mysql_ok
        timeout /t 1 /nobreak >nul
    )
    echo [X] MySQL tidak mau menyala. Cek jendela "WMSense - MySQL".
    pause & exit /b 1
)
:mysql_ok
echo [OK] MySQL siap ^(port 3306^).

sc query memurai | findstr /c:"RUNNING" >nul
if errorlevel 1 sc query Redis | findstr /c:"RUNNING" >nul
if errorlevel 1 (
    echo [!] Redis/Memurai belum jalan, mencoba menyalakan...
    net start Redis >nul 2>&1
    net start memurai >nul 2>&1
    sc query memurai | findstr /c:"RUNNING" >nul || sc query Redis | findstr /c:"RUNNING" >nul || echo [!] Gagal. Jalankan file ini "Run as administrator" atau nyalakan service Redis/Memurai di services.msc ^(sesi login butuh Redis^).
) else (
    echo [OK] Redis berjalan.
)

rem ---------------------------------------------------------------- 2. composer install (library Laravel)
if not exist "vendor\autoload.php" (
    echo [..] Folder vendor belum ada: mengunduh Laravel ^& library ^(composer install^). Butuh internet, bisa beberapa menit...
    if exist "%COMPOSER%" (
        "%PHP%" "%COMPOSER%" install --no-dev --no-interaction --prefer-dist --optimize-autoloader
    ) else (
        where composer >nul 2>&1 && (call composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader) || (
            echo [X] composer tidak ditemukan. Pasang Composer ^(https://getcomposer.org^) atau isi variabel COMPOSER di file ini.
            pause & exit /b 1
        )
    )
    if not exist "vendor\autoload.php" ( echo [X] composer install gagal. & pause & exit /b 1 )
) else (
    echo [OK] Library ^(vendor^) sudah ada.
)

rem ---------------------------------------------------------------- 3. .env + APP_KEY
if not exist ".env" (
    echo [..] Membuat .env dari .env.example...
    copy /y ".env.example" ".env" >nul
)
findstr /r /c:"^APP_KEY=base64:" ".env" >nul || (
    echo [..] Membuat APP_KEY...
    "%PHP%" artisan key:generate --force
)
rem nama database & login MySQL dari .env
set "DBNAME=wms"
for /f "tokens=1,* delims==" %%a in ('findstr /r /c:"^DB_DATABASE=" ".env"') do set "DBNAME=%%b"
set "DBUSER=root"
for /f "tokens=1,* delims==" %%a in ('findstr /r /c:"^DB_USERNAME=" ".env"') do set "DBUSER=%%b"
set "DBPASS="
for /f "tokens=1,* delims==" %%a in ('findstr /r /c:"^DB_PASSWORD=" ".env"') do set "DBPASS=%%b"
set "MYSQLCLI=%MYSQL_DIR%\bin\mysql.exe -u %DBUSER% --host=127.0.0.1 --port=3306"
if defined DBPASS set "MYSQLCLI=%MYSQLCLI% -p%DBPASS%"

rem ---------------------------------------------------------------- 4. database
set "HASDB=0"
for /f %%n in ('%MYSQLCLI% -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='%DBNAME%' AND table_name='users'" 2^>nul') do set "HASDB=%%n"
if "%HASDB%"=="1" (
    echo [OK] Database %DBNAME% sudah ada. Menjalankan migrasi skema terbaru...
    "%PHP%" artisan migrate --force
) else (
    echo [..] Database %DBNAME% belum ada.
    set "LATEST="
    for /f "delims=" %%f in ('dir /b /o-d "database\backup\*.sql" 2^>nul') do if not defined LATEST set "LATEST=%%f"
    if defined LATEST (
        echo [..] Import backup terbaru: database\backup\!LATEST! ...
        %MYSQLCLI% -e "CREATE DATABASE IF NOT EXISTS `%DBNAME%` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        %MYSQLCLI% %DBNAME% < "database\backup\!LATEST!"
        if errorlevel 1 ( echo [X] Import gagal. & pause & exit /b 1 )
        "%PHP%" artisan migrate --force
    ) else (
        echo [..] Tidak ada backup: membuat database baru + data demo ^(migrate --seed^)...
        %MYSQLCLI% -e "CREATE DATABASE IF NOT EXISTS `%DBNAME%` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        "%PHP%" artisan migrate --force --seed
    )
)

rem ---------------------------------------------------------------- 5. portal SaaS (database pusat + site demo)
"%PHP%" artisan portal:install
"%PHP%" artisan portal:upgrade
"%PHP%" artisan view:clear >nul 2>&1
if /i "%~1"=="prepare" (
    echo [OK] Persiapan selesai ^(mode prepare, server tidak dijalankan^).
    exit /b 0
)

rem ---------------------------------------------------------------- 6. aplikasi
netstat -ano | findstr /r /c:":%PORT% .*LISTENING" >nul
if not errorlevel 1 (
    echo [!] Port %PORT% sudah dipakai - mungkin WMSense sudah jalan di jendela lain.
    start "" "http://127.0.0.1:%PORT%/portal"
    pause & exit /b 0
)

echo.
echo ==========================================================
echo   WMSense   : http://127.0.0.1:%PORT%          ^(login: user@kodesite^)
echo   Portal    : http://127.0.0.1:%PORT%/portal   ^(daftar site, monitoring^)
if /i "%HOST%"=="0.0.0.0" (
    echo   Dari HP ^(Wi-Fi sama^): http://IP-KOMPUTER:%PORT%
    for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4"') do echo      kemungkinan IP komputer:%%a
)
echo   Backup DB : backup-wms.bat      Matikan MySQL: stop-wms.bat
echo   Tutup jendela ini untuk menghentikan aplikasi.
echo ==========================================================
echo.
if not defined WMS_NO_BROWSER start "" /b powershell -NoProfile -WindowStyle Hidden -Command "Start-Sleep 2; Start-Process 'http://127.0.0.1:%PORT%/portal'"

"%PHP%" artisan serve --host=%HOST% --port=%PORT%
pause
