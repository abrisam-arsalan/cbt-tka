# Panduan Deploy CBT TKA Sekolah ke Ubuntu

Aplikasi **CBT TKA Sekolah** (Laravel 13 + Inertia + Vue 3 + Tailwind + MariaDB) dipindahkan
dari Windows ke server Ubuntu. Panduan ini mengasumsikan Ubuntu **24.04 LTS** (PHP 8.3 bawaan
repo resmi). Untuk Ubuntu 22.04, lihat bagian [Catatan Ubuntu 22.04](#catatan-ubuntu-2204-lts).

> Tujuan beban: ~200 siswa concurrent. Redis opsional (aplikasi punya fallback presence berbasis database).

---

## 1. Arsitektur target

```
Internet ── Cloudflare Tunnel ──┐
LAN sekolah ────────────────────┤──► Nginx :80 ──► php8.3-fpm ──► /var/www/cbt-tka
                                │                                └──► MariaDB (127.0.0.1:3306)
```

- Nginx melayani file statis + meneruskan request PHP ke `php8.3-fpm`.
- MariaDB hanya listen di `127.0.0.1`.
- Scheduler auto-submit dijalankan lewat **cron** setiap menit.
- Cloudflare Tunnel menyediakan HTTPS publik (tidak perlu buka port 80/443 ke internet).

---

## 2. Persiapan di mesin Windows (sebelum pindah)

### 2.1. Catat APP_KEY — WAJIB

Buka `.env` dan salin nilai `APP_KEY`. **APP_KEY dipakai untuk enkripsi `token_cipher`
(token ujian pada kartu).** Jika APP_KEY berubah setelah pindah, token lama tidak bisa
didekripsi dan kartu tidak bisa dicetak ulang.

Ada dua pilihan:

| Pilihan | Tindakan |
|---|---|
| **A. Bawa data + kartu tetap valid** | Salin `APP_KEY` lama ke `.env` di server. |
| **B. Mulai bersih** | Biarkan `php artisan key:generate` membuat key baru, lalu jalankan `migrate:fresh --seed` (token ujian digenerate ulang). |

### 2.2. Transfer kode

**Cara disarankan — Git:**

```bash
# Di mesin Windows (folder project):
git add .
git commit -m "Deploy ke Ubuntu"
git remote add origin <url-repo-pribadi>
git push -u origin main
```

`.env`, `/vendor`, `/node_modules`, `/public/build` sudah di-`.gitignore` (aman).

**Cara alternatif — arsip tar:**

```bash
# Di mesin Windows (folder project), kecualikan folder berat:
tar --exclude=vendor --exclude=node_modules --exclude=.env -czf cbt-tka.tar.gz .
# Kirim ke server:
scp cbt-tka.tar.gz ubuntu@<IP-SERVER>:/tmp/
```

### 2.3. Migrasi data (opsional — bila ingin membawa database)

```bash
# Di Windows, dump database:
"C:\Program Files\MariaDB 13.0\bin\mysqldump.exe" -u root --single-transaction --routines --triggers cbt_tka > cbt_tka.sql

# Transfer:
scp cbt_tka.sql ubuntu@<IP-SERVER>:/tmp/
```

> Perhatian: MariaDB 13 (Windows) → MariaDB 10.11 (Ubuntu) umumnya kompatibel untuk dump SQL
> biasa. Bila ada peringatan saat import, tambahkan opsi dump `--no-tablespaces`.

---

## 3. Setup server Ubuntu (sekali)

Jalankan sebagai user yang punya sudo.

### 3.1. Update sistem

```bash
sudo apt update && sudo apt upgrade -y
```

### 3.2. Install PHP 8.3 + ekstensi

```bash
sudo apt install -y \
  php8.3-cli php8.3-fpm php8.3-common \
  php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-gd php8.3-zip php8.3-intl php8.3-bcmath \
  php8.3-opcache php8.3-readline php8.3-sqlite3 \
  unzip curl git
```

> `sodium`, `openssl`, `fileinfo`, `ctype`, `filter`, `hash`, `session`, `tokenizer`, `pcre`
> sudah menjadi bagian inti PHP 8.3 (tidak perlu paket terpisah).

Verifikasi:

```bash
php8.3 -m | grep -E 'pdo_mysql|mbstring|gd|zip|intl|bcmath|curl|openssl|sodium|fileinfo'
```

### 3.3. Install Composer

```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"
composer --version
```

### 3.4. Install Node.js 22 (NodeSource)

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
node -v && npm -v
```

### 3.5. Install MariaDB

```bash
sudo apt install -y mariadb-server
sudo mysql_secure_installation
```

> Pada Ubuntu, root MariaDB memakai auth `unix_socket` — `sudo mysql` bisa langsung tanpa
> password. Ikuti wizard untuk menetapkan password root bila diinginkan.

Buat database + user aplikasi:

```sql
sudo mysql <<'SQL'
CREATE DATABASE IF NOT EXISTS cbt_tka CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'cbt'@'127.0.0.1' IDENTIFIED BY 'GANTI_PASSWORD_KUAT';
GRANT ALL PRIVILEGES ON cbt_tka.* TO 'cbt'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
```

> Pastikan MariaDB hanya listen di localhost: di `/etc/mysql/mariadb.conf.d/50-server.cnf`
> set `bind-address = 127.0.0.1`.

### 3.6. Install Nginx

```bash
sudo apt install -y nginx
```

---

## 4. Deploy kode

### 4.1. Letakkan aplikasi

```bash
sudo mkdir -p /var/www/cbt-tka
sudo chown $USER:$USER /var/www/cbt-tka

# Cara Git:
git clone <url-repo> /var/www/cbt-tka

# Cara tar:
# sudo tar -xzf /tmp/cbt-tka.tar.gz -C /var/www/cbt-tka
```

### 4.2. Buat `.env`

```bash
cd /var/www/cbt-tka
cp .env.example .env
nano .env
```

Nilai penting:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://cbt.sekolahanda.sch.id   # domain Cloudflare

# Pakai APP_KEY lama bila membawa data (lihat bagian 2.1)
APP_KEY=base64:...DARI_WINDOWS...

APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cbt_tka
DB_USERNAME=cbt
DB_PASSWORD=GANTI_PASSWORD_KUAT

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

# Presence: database (fallback) atau redis
CBT_PRESENCE_DRIVER=database

# Di belakang Cloudflare Tunnel, WAJIB:
CBT_FORCE_HTTPS=true
TRUSTED_PROXIES="*"

CBT_CARD_SCHOOL_NAME="Nama Sekolah Anda"
```

### 4.3. Install dependensi & build

```bash
cd /var/www/cbt-tka
composer install --no-dev --optimize-autoloader
php artisan key:generate          # LEWATI bila pakai APP_KEY lama
npm ci
npm run build
```

### 4.4. Migrasi / import database

**Mulai bersih (disarankan bila tidak membawa data):**

```bash
php artisan migrate:fresh --seed
```

**Import data dari Windows (bila memakai pilihan A):**

```bash
sudo mysql cbt_tka < /tmp/cbt_tka.sql
php artisan migrate           # pastikan skema terbaru
```

### 4.5. Symlink storage + izin file

```bash
php artisan storage:link

sudo chown -R www-data:www-data /var/www/cbt-tka
sudo chmod -R 775 /var/www/cbt-tka/storage /var/www/cbt-tka/bootstrap/cache
sudo chmod -R 664 /var/www/cbt-tka/storage/logs/*.log 2>/dev/null || true
```

### 4.6. Optimasi produksi

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

> Bila Anda memakai `closure` pada route (tidak ada di aplikasi ini), `route:cache` tidak
> boleh dipakai — namun aplikasi ini memakai controller penuh sehingga aman.

---

## 5. Konfigurasi Nginx

Salin `docs/nginx-ubuntu.conf`:

```bash
sudo cp docs/nginx-ubuntu.conf /etc/nginx/sites-available/cbt-tka
sudo nano /etc/nginx/sites-available/cbt-tka    # sesuaikan server_name
sudo ln -s /etc/nginx/sites-available/cbt-tka /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 6. Scheduler auto-submit (cron)

```bash
sudo crontab -e
```

Tambahkan baris:

```cron
* * * * * cd /var/www/cbt-tka && /usr/bin/php8.3 artisan schedule:run >> /dev/null 2>&1
```

Verifikasi command scheduler terdaftar:

```bash
cd /var/www/cbt-tka && php8.3 artisan schedule:list
```

---

## 7. Cloudflare Tunnel (HTTPS publik)

### 7.1. Install cloudflared

```bash
sudo mkdir -p --mode=0755 /usr/share/keyrings
curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg | sudo tee /usr/share/keyrings/cloudflare-main.gpg >/dev/null
echo "deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared any main" | sudo tee /etc/apt/sources.list.d/cloudflared.list
sudo apt update && sudo apt install -y cloudflared
```

### 7.2. Buat tunnel

```bash
cloudflared tunnel login                    # buka URL, pilih domain
cloudflared tunnel create cbt-tka
cloudflared tunnel route dns cbt-tka cbt.sekolahanda.sch.id
```

### 7.3. Konfigurasi

```bash
sudo mkdir -p /etc/cloudflared
sudo nano /etc/cloudflared/config.yml
```

```yaml
tunnel: <TUNNEL_ID>
credentials-file: /root/.cloudflared/<TUNNEL_ID>.json

ingress:
  - hostname: cbt.sekolahanda.sch.id
    service: http://localhost:80
  - service: http_status:404
```

### 7.4. Jalankan sebagai service

```bash
sudo cloudflared service install
sudo systemctl enable --now cloudflared
sudo systemctl status cloudflared
```

> Karena Cloudflare mengirim request dari localhost, `TRUSTED_PROXIES="*"` aman. Pastikan
> Nginx **tidak** bisa diakses langsung dari internet (lihat Keamanan).

---

## 8. Opsional: Redis untuk presence

```bash
sudo apt install -y redis-server
# default listen 127.0.0.1:6379, tanpa password — cukup untuk lokal
sudo systemctl enable --now redis-server
```

Lalu di `.env` ubah:

```ini
CBT_PRESENCE_DRIVER=redis
```

> Tanpa Redis, aplikasi tetap berjalan penuh lewat fallback database (default). Untuk 200
> siswa, fallback database sudah memadai.

---

## 9. Keamanan & verifikasi

```bash
# 1. Firewall (UFW) — hanya buka SSH; port 80/443 ditutup karena via Cloudflare Tunnel
sudo ufw allow OpenSSH
sudo ufw enable

# 2. Pastikan .env tidak bisa diakses web (sudah di-blok nginx + tidak di public/)

# 3. Uji dari browser: buka https://cbt.sekolahanda.sch.id
#    Login admin: admin / admin123  -> GANTI PASSWORD SETELAH LOGIN PERTAMA

# 4. Uji scheduler:
cd /var/www/cbt-tka && php8.3 artisan cbt:auto-submit
```

Checklist akhir:

- [ ] `php8.3 -m` menampilkan semua ekstensi.
- [ ] `php artisan route:list` tanpa error.
- [ ] `php artisan migrate:status` hijau.
- [ ] `php artisan schedule:list` menampilkan `cbt:auto-submit`.
- [ ] Nginx `nginx -t` lolos, halaman login tampil.
- [ ] HTTPS aktif via Cloudflare Tunnel.
- [ ] Password default diganti.

---

## 10. Troubleshooting

| Gejala | Solusi |
|---|---|
| 500 setelah deploy | `tail -f storage/logs/laravel.log`; cek `chown www-data` pada storage. |
| "Could not find driver" (pdo_mysql) | `sudo apt install php8.3-mysql && sudo systemctl restart php8.3-fpm`. |
| Kartu ujian token kosong | APP_KEY berubah — regenerasi token (`migrate:fresh --seed` atau regenerate di menu Peserta). |
| Scheduler tidak jalan | Cek `crontab -l`; pastikan path `php8.3` benar (`which php8.3`). |
| Asset JS/CSS 404 | Jalankan ulang `npm run build`; cek `@vite` manifest di `public/build`. |
| Redirect ke http (bukan https) | Pastikan `CBT_FORCE_HTTPS=true` dan `TRUSTED_PROXIES="*"` di `.env`. |

---

## Catatan Ubuntu 22.04 LTS

Ubuntu 22.04 bawaan PHP 8.1 (terlalu tua untuk Laravel 13). Tambahkan PPA Ondřej:

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
# lalu gunakan nama paket yang sama (php8.3-*)
```

Sisanya identik dengan panduan di atas.

---

Dikembangkan untuk keperluan pendidikan internal sekolah.
