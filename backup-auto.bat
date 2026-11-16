@echo off
rem Dipanggil Task Scheduler ("WMSense Backup", tiap hari 01:00): backup semua database wms* lalu simpan 14 file terakhir.
cd /d "%~dp0"
call backup-wms.bat < nul
powershell -NoProfile -Command "Get-ChildItem 'database\backup\wms-*.sql' | Sort-Object LastWriteTime -Descending | Select-Object -Skip 14 | Remove-Item -Force"
