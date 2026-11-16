# ==========================================================================
#  WMSense - setup SERVER (dijalankan lewat setup-server.bat, Run as administrator)
#  Menyiapkan: Laragon (PHP/MySQL/Apache), Redis (diunduh), Composer, user MySQL khusus aplikasi,
#  .env mode produksi, Apache + MySQL + Redis sebagai Windows Service (jalan otomatis saat boot),
#  firewall port 80/443, backup otomatis harian. Aman dijalankan berulang.
#  Hanya karakter ASCII di file ini (PowerShell 5.1).
# ==========================================================================
param(
    [string]$Domain = '',     # mis. wms.perusahaan.com (kosong = semua alamat / IP server)
    [switch]$Https            # aplikasi diakses lewat HTTPS (Cloudflare Tunnel / proxy SSL): cookie diamankan
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$laragon = 'C:\laragon'
$report = New-Object System.Collections.ArrayList

function Say($msg, $color = 'White') { Write-Host $msg -ForegroundColor $color }
function Ok($msg) { Say "[OK] $msg" 'Green' }
function Info($msg) { Say "[..] $msg" 'Cyan' }
function Warn($msg) { Say "[!]  $msg" 'Yellow'; [void]$report.Add($msg) }
function Fail($msg) { Say "[X]  $msg" 'Red'; Read-Host 'Tekan Enter untuk keluar' | Out-Null; exit 1 }

function New-Secret([int]$len = 24) {
    $chars = [char[]]'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'
    $rng = New-Object System.Security.Cryptography.RNGCryptoServiceProvider
    $bytes = New-Object byte[] $len
    $rng.GetBytes($bytes)
    -join ($bytes | ForEach-Object { $chars[$_ % $chars.Length] })
}

function Get-EnvValue([string]$text, [string]$key) {
    $m = [regex]::Match($text, "(?m)^$key=(.*)$")
    if ($m.Success) { return $m.Groups[1].Value.Trim().Trim('"') }
    return $null
}
function Set-EnvValue([string]$path, [string]$key, [string]$value) {
    $text = [IO.File]::ReadAllText($path)
    if ($text -match "(?m)^$key=") {
        $text = [regex]::Replace($text, "(?m)^$key=.*$", [System.Text.RegularExpressions.MatchEvaluator] { param($m) "$key=$value" })
    } else {
        if ($text.Length -gt 0 -and -not $text.EndsWith("`n")) { $text += "`r`n" }
        $text += "$key=$value`r`n"
    }
    [IO.File]::WriteAllText($path, $text, (New-Object System.Text.UTF8Encoding($false)))
}
function Test-Port([int]$port) {
    [bool](Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue)
}
function Get-Download([string]$url, [string]$out) {
    Info "Mengunduh $url"
    Invoke-WebRequest -Uri $url -OutFile $out -UseBasicParsing
}

# ---------------------------------------------------------------- 0. hak admin
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) { Fail 'Jalankan setup-server.bat dengan klik kanan > Run as administrator.' }
Say ''
Say '=== WMSense - setup server ===' 'Cyan'

# ---------------------------------------------------------------- 1. Laragon
if (-not (Test-Path "$laragon\bin\php")) {
    Info 'Laragon belum terpasang. Mengunduh & memasang (silent)...'
    $inst = Join-Path $env:TEMP 'laragon-wamp.exe'
    Get-Download 'https://github.com/leokhoa/laragon/releases/download/6.0.0/laragon-wamp.exe' $inst
    Start-Process -FilePath $inst -ArgumentList '/VERYSILENT', '/SUPPRESSMSGBOXES', '/NORESTART', '/DIR=C:\laragon' -Wait
    if (-not (Test-Path "$laragon\bin\php")) { Fail 'Pemasangan Laragon belum berhasil. Pasang manual dari https://laragon.org (versi Full) lalu jalankan ulang.' }
}
$php = Get-ChildItem "$laragon\bin\php" -Directory -Filter 'php-8*' | Sort-Object Name -Descending | ForEach-Object { Join-Path $_.FullName 'php.exe' } | Where-Object { Test-Path $_ } | Select-Object -First 1
$mysqlDir = Get-ChildItem "$laragon\bin\mysql" -Directory -Filter 'mysql-8*' | Sort-Object Name -Descending | Select-Object -First 1 -ExpandProperty FullName
$apacheDir = Get-ChildItem "$laragon\bin\apache" -Directory | Sort-Object Name -Descending | Select-Object -First 1 -ExpandProperty FullName
if (-not $php) { Fail 'PHP 8 tidak ditemukan di C:\laragon\bin\php.' }
if (-not $mysqlDir -or -not (Test-Path "$mysqlDir\bin\mysqld.exe")) { Fail 'MySQL 8 tidak ditemukan di C:\laragon\bin\mysql.' }
if (-not $apacheDir -or -not (Test-Path "$apacheDir\bin\httpd.exe")) { Fail 'Apache tidak ditemukan di C:\laragon\bin\apache.' }
$mysql = "$mysqlDir\bin\mysql.exe"
$httpd = "$apacheDir\bin\httpd.exe"
Ok "PHP    : $php"
Ok "MySQL  : $mysqlDir"
Ok "Apache : $apacheDir"
if (-not (Test-Path "$mysqlDir\my.ini")) { Warn "my.ini MySQL tidak ada di $mysqlDir. Buka Laragon sekali (Start All) agar file dibuat, lalu jalankan ulang." }

# ---------------------------------------------------------------- 2. Redis (sesi login & cache)
$redisSvc = @('Redis', 'memurai') | Where-Object { Get-Service -Name $_ -ErrorAction SilentlyContinue } | Select-Object -First 1
if (-not $redisSvc) {
    $redisDir = Get-ChildItem "$laragon\bin\redis" -Directory -ErrorAction SilentlyContinue | Where-Object { Test-Path (Join-Path $_.FullName 'redis-server.exe') } | Select-Object -First 1 -ExpandProperty FullName
    if (-not $redisDir) {
        $zip = Join-Path $env:TEMP 'redis-win.zip'
        Get-Download 'https://github.com/tporadowski/redis/releases/download/v5.0.14.1/Redis-x64-5.0.14.1.zip' $zip
        $redisDir = "$laragon\bin\redis\redis-x64-5.0.14.1"
        New-Item -ItemType Directory -Force $redisDir | Out-Null
        Expand-Archive -Path $zip -DestinationPath $redisDir -Force
    }
    Info 'Memasang Redis sebagai Windows Service (hanya 127.0.0.1)...'
    Push-Location $redisDir
    & .\redis-server.exe --service-install redis.windows-service.conf --loglevel notice --service-name Redis | Out-Null
    Pop-Location
    $redisSvc = 'Redis'
}
Set-Service -Name $redisSvc -StartupType Automatic
if ((Get-Service $redisSvc).Status -ne 'Running') { Start-Service $redisSvc }
Ok "Redis service '$redisSvc' berjalan (otomatis saat boot)."

# ---------------------------------------------------------------- 3. Composer
if (-not (Test-Path "$laragon\bin\composer\composer.phar")) {
    New-Item -ItemType Directory -Force "$laragon\bin\composer" | Out-Null
    Get-Download 'https://getcomposer.org/composer-stable.phar' "$laragon\bin\composer\composer.phar"
}
Ok 'Composer siap.'

# ---------------------------------------------------------------- 4. MySQL sebagai service
if (-not (Get-Service -Name 'WMSMySQL' -ErrorAction SilentlyContinue)) {
    Info 'Memasang MySQL sebagai Windows Service (WMSMySQL)...'
    & "$mysqlDir\bin\mysqld.exe" --install WMSMySQL "--defaults-file=$mysqlDir\my.ini" | Out-Null
}
Set-Service -Name 'WMSMySQL' -StartupType Automatic
if (Test-Port 3306) {
    if ((Get-Service 'WMSMySQL').Status -ne 'Running') {
        Ok 'MySQL sudah berjalan (dinyalakan di luar service, mis. Laragon / start-wms.bat). Service aktif setelah restart.'
    }
} else {
    Start-Service 'WMSMySQL'
    for ($i = 0; $i -lt 30 -and -not (Test-Port 3306); $i++) { Start-Sleep 1 }
}
if (-not (Test-Port 3306)) { Fail 'MySQL tidak mau menyala. Cek log di C:\laragon\data\mysql-8\*.err' }
Ok 'MySQL berjalan di port 3306.'

# ---------------------------------------------------------------- 5. .env produksi + user MySQL khusus aplikasi
$envFile = Join-Path $root '.env'
if (-not (Test-Path $envFile)) {
    Copy-Item (Join-Path $root '.env.example') $envFile
    Info '.env dibuat dari .env.example'
}
$envText = [IO.File]::ReadAllText($envFile)
$dbUser = Get-EnvValue $envText 'DB_USERNAME'
$dbPass = Get-EnvValue $envText 'DB_PASSWORD'
if (-not $dbUser) { $dbUser = 'root' }

if ($dbUser -eq 'root' -and -not $dbPass) {
    $rootOk = (& $mysql -u root --host=127.0.0.1 --port=3306 -N -e 'SELECT 1') -eq '1'
    if ($rootOk) {
        $appUser = 'wms_app'
        $appPass = New-Secret 24
        $sql = @'
CREATE USER IF NOT EXISTS '{U}'@'localhost' IDENTIFIED BY '{P}';
CREATE USER IF NOT EXISTS '{U}'@'127.0.0.1' IDENTIFIED BY '{P}';
ALTER USER '{U}'@'localhost' IDENTIFIED BY '{P}';
ALTER USER '{U}'@'127.0.0.1' IDENTIFIED BY '{P}';
GRANT ALL PRIVILEGES ON `wms%`.* TO '{U}'@'localhost';
GRANT ALL PRIVILEGES ON `wms%`.* TO '{U}'@'127.0.0.1';
FLUSH PRIVILEGES;
'@.Replace('{U}', $appUser).Replace('{P}', $appPass)
        $tmp = Join-Path $env:TEMP 'wms-user.sql'
        [IO.File]::WriteAllText($tmp, $sql)
        Get-Content $tmp -Raw | & $mysql -u root --host=127.0.0.1 --port=3306
        Remove-Item $tmp -Force
        if ($LASTEXITCODE -ne 0) { Fail 'Gagal membuat user MySQL aplikasi.' }
        Set-EnvValue $envFile 'DB_USERNAME' $appUser
        Set-EnvValue $envFile 'DB_PASSWORD' $appPass
        Ok "User MySQL khusus aplikasi dibuat: $appUser (password tersimpan di .env). Akses hanya ke database wms*."
    } else {
        Warn 'MySQL root sudah berpassword. Isi DB_USERNAME / DB_PASSWORD di .env secara manual (user harus punya hak ALL pada database wms*).'
    }
} else {
    Ok "DB_USERNAME di .env sudah '$dbUser' - tidak diubah."
}

$publicHost = $Domain
if (-not $publicHost) {
    $publicHost = (Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' } | Select-Object -First 1 -ExpandProperty IPAddress)
    if (-not $publicHost) { $publicHost = 'localhost' }
}
$scheme = 'http'
if ($Https) { $scheme = 'https' }
Set-EnvValue $envFile 'APP_ENV' 'production'
Set-EnvValue $envFile 'APP_DEBUG' 'false'
Set-EnvValue $envFile 'APP_URL' "${scheme}://$publicHost"
if ($Https) { Set-EnvValue $envFile 'SESSION_SECURE_COOKIE' 'true' }

$envText = [IO.File]::ReadAllText($envFile)
$portalPass = Get-EnvValue $envText 'PORTAL_PASSWORD'
$newPortalPass = $null
if (-not $portalPass -or $portalPass -eq 'wmsense') {
    $newPortalPass = New-Secret 14
    Set-EnvValue $envFile 'PORTAL_PASSWORD' $newPortalPass
}
Ok ".env produksi: APP_ENV=production, APP_DEBUG=false, APP_URL=${scheme}://$publicHost"

# ---------------------------------------------------------------- 6. library, database, migrasi (start-wms.bat prepare)
Info 'Menyiapkan library, database & migrasi (bisa beberapa menit bila vendor/ belum ada)...'
Push-Location $root
$env:WMS_NO_BROWSER = '1'
cmd /c "call `"$root\start-wms.bat`" prepare < nul"
$prep = $LASTEXITCODE
Pop-Location
if ($prep -ne 0) { Fail 'Persiapan aplikasi gagal (lihat pesan di atas).' }
& $php (Join-Path $root 'artisan') optimize:clear | Out-Null
Ok 'Aplikasi & database siap.'

# ---------------------------------------------------------------- 7. Apache (virtual host + service)
$vhostDir = "$laragon\etc\apache2\sites-enabled"
New-Item -ItemType Directory -Force $vhostDir | Out-Null
$docroot = ($root -replace '\\', '/') + '/public'
$serverName = 'localhost'
if ($Domain) { $serverName = $Domain }
# nama file diawali "0-" supaya dimuat PERTAMA = menjadi host default (bisa dibuka lewat IP / domain apa pun)
$vhost = @"
# Dibuat oleh setup-server.bat - jangan diubah manual
<VirtualHost *:80>
    ServerName $serverName
    DocumentRoot "$docroot"
    <Directory "$docroot">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
"@
[IO.File]::WriteAllText("$vhostDir\0-wms-server.conf", $vhost)
$test = cmd /c "`"$httpd`" -t 2>&1"
if ($LASTEXITCODE -ne 0) { Fail "Konfigurasi Apache bermasalah:`n$test" }
if (-not (Get-Service -Name 'WMSApache' -ErrorAction SilentlyContinue)) {
    & $httpd -k install -n WMSApache | Out-Null
}
Set-Service -Name 'WMSApache' -StartupType Automatic
if ((Get-Service 'WMSApache').Status -eq 'Running') {
    Restart-Service 'WMSApache'
} elseif (Test-Port 80) {
    Warn 'Port 80 dipakai program lain (mis. Laragon yang sedang jalan). Tutup Laragon / Apache lain lalu jalankan: net start WMSApache'
} else {
    Start-Service 'WMSApache'
}
if ((Get-Service 'WMSApache').Status -eq 'Running') { Ok 'Apache service WMSApache berjalan (otomatis saat boot).' }

# ---------------------------------------------------------------- 8. firewall
Get-NetFirewallRule -DisplayName 'WMSense Web' -ErrorAction SilentlyContinue | Remove-NetFirewallRule
$ports = @(80)
if ($Https) { $ports = @(80, 443) }
New-NetFirewallRule -DisplayName 'WMSense Web' -Direction Inbound -Protocol TCP -LocalPort $ports -Action Allow -Profile Any | Out-Null
Ok ("Firewall: port " + ($ports -join ', ') + ' dibuka. MySQL 3306 & Redis 6379 TIDAK dibuka.')

# ---------------------------------------------------------------- 9. backup otomatis harian
$taskName = 'WMSense Backup'
Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue | Unregister-ScheduledTask -Confirm:$false
$act = New-ScheduledTaskAction -Execute 'cmd.exe' -Argument "/c `"$root\backup-auto.bat`"" -WorkingDirectory $root
$trg = New-ScheduledTaskTrigger -Daily -At '01:00'
$prn = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
Register-ScheduledTask -TaskName $taskName -Action $act -Trigger $trg -Principal $prn | Out-Null
Ok "Backup otomatis tiap hari 01:00 -> database\backup (disimpan 14 file terakhir). Salin foldernya ke tempat lain secara berkala."

# ---------------------------------------------------------------- 10. cek akhir
Say ''
try {
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1/portal' -UseBasicParsing -TimeoutSec 20
    Ok "Cek http://127.0.0.1/portal -> HTTP $($r.StatusCode)"
} catch {
    Warn "Cek http://127.0.0.1/portal gagal: $($_.Exception.Message)"
}

Say ''
Say '================ SELESAI ================' 'Green'
Say "Aplikasi : ${scheme}://$publicHost"
Say "Portal   : ${scheme}://$publicHost/portal"
if ($newPortalPass) { Say "Password portal (monitor) : $newPortalPass   <- catat! (tersimpan di .env: PORTAL_PASSWORD)" 'Yellow' }
Say 'Login demo: superadmin@demo (password bawaan data demo - SEGERA GANTI)' 'Yellow'
Say ''
Say 'Catatan:'
Say ' - Jangan menyalakan Laragon (GUI) di server ini: port 80 sudah dipakai service WMSApache.'
Say ' - Akses dari internet: port-forward 80/443 di router, atau Cloudflare Tunnel ke http://localhost:80 (lalu jalankan ulang dengan -Https).'
Say ' - Update aplikasi: salin file baru, lalu jalankan start-wms.bat prepare (migrate semua site otomatis).'
if ($report.Count -gt 0) {
    Say ''
    Say 'PERHATIAN:' 'Yellow'
    $report | ForEach-Object { Say " - $_" 'Yellow' }
}
Say ''
Read-Host 'Tekan Enter untuk menutup' | Out-Null
