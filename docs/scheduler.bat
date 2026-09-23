@echo off
rem ============================================================
rem Alternatif scheduler untuk CBT TKA Sekolah (tanpa Task Scheduler)
rem Jalankan sekali; script akan loop tiap 60 detik.
rem ============================================================

cd /d D:\cbt-tka

:loop
C:\php\php.exe artisan schedule:run
timeout /t 60 /nobreak >nul
goto loop
