@echo off
rem ============================================================
rem Backup harian database CBT TKA Sekolah
rem Jadwalkan via Task Scheduler setiap pukul 02:00
rem ============================================================

set MYSQL_BIN=C:\Program Files\MariaDB 13.0\bin
set BACKUP_DIR=D:\backup\cbt_tka
set DB_USER=root
set DB_NAME=cbt_tka

rem Tanggal format YYYYMMDD
set DATE=%DATE:~-4%%DATE:~4,2%%DATE:~7,2%

if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

"%MYSQL_BIN%\mysqldump.exe" -u %DB_USER% --single-transaction --routines --triggers %DB_NAME% > "%BACKUP_DIR%\cbt_tka_%DATE%.sql"

rem Hapus backup lebih dari 30 hari
forfiles /p "%BACKUP_DIR%" /s /m *.sql /d -30 /c "cmd /c del @file" 2>nul
