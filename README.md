# CBT TKA Sekolah

Aplikasi Computer-Based Test (CBT) untuk Tes Kemampuan Akademik sekolah, berbasis web mobile-first.

Dibangun dengan **Laravel 13** + **Inertia.js** + **Vue 3** + **Tailwind CSS v4** + **MariaDB**.

---

## Daftar Isi

- [Fitur](#fitur)
- [Prasyarat](#prasyarat)
- [Setup Awal](#setup-awal)
- [Menjalankan di Windows](#menjalankan-di-windows)
- [Scheduler Auto-Submit](#scheduler-auto-submit)
- [Cloudflare Tunnel](#cloudflare-tunnel)
- [Backup Database](#backup-database)
- [Catatan Produksi Windows 10 Pro](#catatan-produksi-windows-10-pro)

---

## Fitur

- **4 tipe soal**: Pilihan Ganda, PG Kompleks, Benar/Salah, Menjodohkan (all-or-nothing)
- **Offline-first**: jawaban disimpan di outbox browser (localStorage) dan dikirim saat koneksi pulih
- **Grace period**: jawaban offline tetap diterima setelah deadline sesuai `offline_grace_minutes`
- **Anti-cheat**: deteksi `visibilitychange` dan `window blur` + aksi konfiguratif (log / warning / auto-submit / lock)
- **Auto-submit**: scheduler tiap menit menandai kedaluwarsa lalu submit otomatis
- **Kartu ujian**: token unik + QR code (SVG), cetak massal 2 kolom per A4
- **Monitoring real-time**: presence siswa, progress, sisa waktu, warning, online/offline
- **Impor soal**: CSV / XLSX dengan preview baris valid & error
- **Audit log**: jejak seluruh aksi admin
- **Dual driver presence**: Redis (bila tersedia) dengan fallback database

---

## Prasyarat

| Komponen | Versi | Keterangan |
|----------|-------|-----------|
| PHP | 8.3+ | ekstensi: pdo_mysql, mbstring, curl, gd, intl, zip, fileinfo, openssl, sodium |
| MariaDB | 10.3+ | atau MySQL 8.0+ |
| Composer | 2.x | |
| Node.js | 22+ | npm 11+ untuk build frontend |

### Opsional

- **Redis** — tidak wajib. Aplikasi berjalan penuh dengan driver presence database.

---

## Setup Awal

```bat
:: 1. Masuk ke folder project
cd D:\cbt-tka

:: 2. Salin .env.example → .env
copy .env.example .env

:: 3. Edit .env (DB_DATABASE, DB_USERNAME, DB_PASSWORD, APP_TIMEZONE, CBT_CARD_SCHOOL_NAME)

:: 4. Install dependensi PHP
composer install --no-dev --optimize-autoloader

:: 5. Generate application key
php artisan key:generate

:: 6. Buat database
::    mysql -u root -e "CREATE DATABASE cbt_tka CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

:: 7. Migrasi + seeder
php artisan migrate:fresh --seed

:: 8. Symlink storage (upload logo, template, dll.)
php artisan storage:link

:: 9. Install & build frontend
npm install
npm run build
```

### Akun Default

| Role | Username | Password |
|------|----------|----------|
| Admin | admin | admin123 |
| Siswa | siswa01 .. siswa05 | siswa123 |

---

## Menjalankan di Windows

### Pengujian (built-in server)

```bat
php artisan serve --host=0.0.0.0 --port=8000
```

> ⚠️ **Jangan pakai `artisan serve` di produksi** untuk 200 siswa. Gunakan nginx + PHP-CGI atau Apache.

### Produksi: Nginx + PHP-FPM (disarankan)

1. Install Nginx for Windows dan PHP 8.3 Non-Thread Safe.
2. Jalankan PHP FastCGI:

   ```bat
   set PHP_FCGI_MAX_REQUESTS=10000
   start /B "php-cgi" "C:\php\php-cgi.exe" -b 127.0.0.1:9000
   ```

3. Konfigurasi Nginx — lihat [docs/nginx.conf.example](docs/nginx.conf.example).
4. Start Nginx: `start nginx`

### Produksi: Apache

Lihat [docs/apache-vhost.conf.example](docs/apache-vhost.conf.example).

---

## Scheduler Auto-Submit

### Windows Task Scheduler (disarankan)

1. Buka Task Scheduler (`taskschd.msc`)
2. Create Task → nama **CBT Auto Submit**
3. **Triggers** → New → `Daily` → **Repeat every 1 minute** → `for a duration of: Indefinitely`
4. **Actions** → Start a program:
   - Program: `C:\php\php.exe`
   - Arguments: `D:\cbt-tka\artisan schedule:run`
   - Start in: `D:\cbt-tka`

### Alternatif (batch loop)

`docs/scheduler.bat` — jalankan sekali sebagai background process.

---

## Cloudflare Tunnel

1. Download `cloudflared` dari https://github.com/cloudflare/cloudflared/releases
2. `cloudflared.exe tunnel login` → pilih domain
3. `cloudflared.exe tunnel create cbt-tka`
4. `cloudflared.exe tunnel route dns cbt-tka cbt.sekolahanda.sch.id`
5. Buat `~/.cloudflared/config.yml` — lihat [docs/cloudflared-config.yml.example](docs/cloudflared-config.yml.example)
6. Instal service: `cloudflared.exe service install`
7. Di `.env`: `CBT_FORCE_HTTPS=true` dan `TRUSTED_PROXIES="*"`

### Amankan Origin (WAJIB)

Port 80/443 origin **harus** dibatasi via Windows Firewall agar hanya bisa diakses
`127.0.0.1` (cloudflared) dan LAN sekolah. Jangan buka port MySQL (3306) atau Redis (6379) ke internet.

---

## Backup Database

Lihat [docs/backup.bat](docs/backup.bat) — jadwalkan harian lewat Task Scheduler.

Restore:

```bat
"C:\Program Files\MariaDB 13.0\bin\mysql.exe" -u root cbt_tka < backup.sql
```

---

## Catatan Produksi Windows 10 Pro

1. **MariaDB/MySQL** — set `innodb_buffer_pool_size` cukup (512M–1G). Bila latency jadi masalah di HDD, `innodb_flush_log_at_trx_commit=2`.
2. **PHP** — `opcache.enable=1`, `opcache.validate_timestamps=0` (setelah deploy), `realpath_cache_size=4096k`.
3. **Disk HDD** — desain write memakai upsert (heartbeat, sync) sehingga tidak ada pembengkakan file. Hindari aplikasi lain menulis ke disk yang sama saat ujian.
4. **Jaringan** — 5–10 Mbps cukup untuk 200 siswa non-video. Siapkan 6–8 access point untuk 200 perangkat.
5. **Anti-cheat** — deteksi `visibilitychange`/`blur` TIDAK mencegah split-screen atau floating window di HP. Untuk ujian high-stakes, tetap dampingi pengawas fisik.
6. **Keamanan** — ganti password default sebelum dipakai. Jangan pernah menyimpan `.env` ke repository publik.

---

## Teknologi

| Bagian | Teknologi |
|--------|-----------|
| Backend | Laravel 13 (PHP 8.3) |
| Frontend | Vue 3 + Inertia.js + Ziggy |
| CSS | Tailwind CSS v4 |
| Database | MariaDB 13.0 |
| Presence | Redis ZSET / Database UPSERT |
| QR Code | BaconQrCode (SVG) |
| Import Soal | Maatwebsite Excel / CSV |

---

## Struktur Folder Utama

```
app/
  Enums/            UserRole, ExamStatus, QuestionType, AttemptStatus, ...
  Models/           User, Exam, Attempt, Answer, Question, ...
  Services/         AnswerSyncService, ScoringService, ExamTimerService,
                    AntiCheatService, PresenceService, CardService,
                    ImportQuestionService, AuditLogService, SettingService
  Http/
    Controllers/    Auth, Student, Admin
    Middleware/     EnsureUserHasRole, HandleInertiaRequests
    Requests/       Auth, Student, Admin
config/cbt.php      Konfigurasi aplikasi
database/migrations/  15 tabel
database/seeders/   admin, kelas, siswa, ujian, soal, template, settings
resources/js/Pages/  Halaman Vue (Inertia)
routes/
  web.php           Rute publik + siswa
  admin.php         Rute admin (prefix /admin)
```

---

Dikembangkan untuk keperluan pendidikan internal sekolah.
