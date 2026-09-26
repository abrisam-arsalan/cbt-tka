# Pindah ke Server Beda Jaringan via GitHub

Panduan memindahkan **CBT TKA Sekolah** dari PC Windows saat ini ke server lain
(di jaringan berbeda) menggunakan GitHub sebagai jembatan kode.

> Detail instalasi Ubuntu lengkap (Nginx, MariaDB, Cloudflare Tunnel, scheduler)
> ada di [`DEPLOY-UBUNTU.md`](DEPLOY-UBUNTU.md). Dokumen ini fokus ke alur GitHub.

---

## 0. Yang TIDAK ikut lewat GitHub (pindahkan manual!)

| Barang | Kenapa tidak di git | Cara pindah |
|---|---|---|
| `.env` (termasuk `APP_KEY`) | di-gitignore | Copy nilainya manual. **`APP_KEY` wajib sama** bila ingin token kartu ujian lama tetap bisa didekripsi. |
| Gambar soal (`storage/app/public/soal/`) | di-gitignore | Salin foldernya (scp/flashdisk), atau impor ulang ZIP bank soalnya. |
| Database (`database/database.sqlite` / MariaDB) | di-gitignore | `mysqldump` / copy file sqlite, atau `migrate:fresh --seed` untuk mulai bersih. |

---

## 1. Di PC Windows (sumber) — push ke GitHub

```bash
cd cbt-tka
git status                      # pastikan bersih (sudah di-commit semua)
git log origin/main..HEAD --oneline   # commit yang belum terdorong

git push origin main
```

Pastikan repo GitHub bersifat **private** (ada data sekolah di dalamnya).
Buka https://github.com/abrisam-arsalan/cbt-tka dan cek commit terbaru muncul.

## 2. Siapkan akses GitHub di server baru

Pilih salah satu:

- **Deploy Key (recommended, read-only)** — di server:
  ```bash
  ssh-keygen -t ed25519 -C "cbt-server" -f ~/.ssh/id_ed25519_cbt -N ""
  cat ~/.ssh/id_ed25519_cbt.pub
  ```
  Tempel isinya ke GitHub → *Settings → Deploy keys → Add deploy key* (jangan centang write access).
  Lalu buat `~/.ssh/config`:
  ```
  Host github.com-cbt
      HostName github.com
      IdentityFile ~/.ssh/id_ed25519_cbt
      IdentitiesOnly yes
  ```
- **Personal Access Token** — pakai URL `https://<TOKEN>@github.com/abrisam-arsalan/cbt-tka.git` saat clone/pull.

## 3. Di server baru — clone & pasang

```bash
# prasyarat (Ubuntu 24.04) — lihat DEPLOY-UBUNTU.md §3 untuk daftar lengkap
sudo apt update
sudo apt install -y nginx mariadb-server php8.3-fpm php8.3-mysql php8.3-xml \
    php8.3-mbstring php8.3-curl php8.3-zip php8.3-gd git unzip nodejs npm composer

# clone
sudo mkdir -p /var/www && sudo chown $USER:$USER /var/www
git clone git@github.com-cbt:abrisam-arsalan/cbt-tka.git /var/www/cbt-tka
cd /var/www/cbt-tka
```

### 3.1. Buat .env produksi

```bash
cp .env.example .env
```

Edit `.env` — minimal yang HARUS diubah:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ujian.smp5tegal.sch.id   # atau http://IP-LAN-sekolah
APP_KEY=base64:....                      # SALIN dari .env PC lama (lihat §0)
DB_CONNECTION=mysql                      # MariaDB/MySQL server
DB_DATABASE=cbt_tka
DB_USERNAME=cbt
DB_PASSWORD=<password-baru-yang-kuat>
CBT_PRESENCE_DRIVER=database
```

### 3.2. Database

```bash
sudo mysql -e "CREATE DATABASE cbt_tka CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'cbt'@'localhost' IDENTIFIED BY '<password>'; \
               GRANT ALL ON cbt_tka.* TO 'cbt'@'localhost';"

php artisan migrate --force
# data awal (admin + kelas + contoh):
php artisan db:seed --class=DatabaseSeeder --force
# ATAU bawa data lama: restore dump dari PC lama (lihat §5)
```

### 3.3. Install & build

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan storage:link          # WAJIB agar gambar soal & kartu bisa diakses
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

### 3.4. Web server + cron

- Nginx: salin & sesuaikan `docs/nginx-ubuntu.conf` → `sites-available/cbt-tka`,
  `ln -s` ke sites-enabled, `nginx -t && systemctl reload nginx`.
- Cron scheduler (auto-submit soal): `crontab -e`
  `* * * * * cd /var/www/cbt-tka && php artisan schedule:run >> /dev/null 2>&1`
- HTTPS publik tanpa buka port: lihat `docs/cloudflared-config.yml.example`.
- **php.ini server**: pastikan `upload_tmp_dir` dan `upload_max_filesize=8M`,
  `post_max_size=25M` (diperlukan untuk upload gambar soal — di Windows masalah
  ini membuat semua upload gagal).

### 3.5. Uji

```
https://domain-atau-ip/login        -> login siswa (NISN + PIN)
https://domain-atau-ip/admin/login  -> login admin
```

Coba satu ujian sampai selesai, cek gambar soal tampil, cetak kartu.

## 4. Update rutin ke depannya

Di PC Windows: `git add -A && git commit -m "..." && git push origin main`

Di server (atau cukup jalankan skripnya):

```bash
cd /var/www/cbt-tka && ./deploy.sh            # pull + composer + build + cache
./deploy.sh --migrate                         # bila ada migrasi DB baru
```

## 5. Membawa data lama (opsional)

Dari PC Windows (SQLite dev):

```bash
# cara paling simpel: aktifkan MariaDB di PC? Tidak perlu —
# gunakan dump dari server lama, atau export/import via artisan tinker.
```

Dari server lama (MySQL/MariaDB):

```bash
mysqldump -u cbt -p --single-transaction cbt_tka > cbt-backup-$(date +%F).sql
scp cbt-backup-*.sql user@server-baru:/tmp/
# di server baru:
mysql -u cbt -p cbt_tka < /tmp/cbt-backup-tanggal.sql
```

Jangan lupa salin juga `storage/app/public/soal/` (gambar) dan sesuaikan
`APP_KEY` bila backup memakai key berbeda (kalau beda, token kartu lama tidak
terbaca — regenerate token peserta).

## 6. Checklist akhir pindah

- [ ] `git push origin main` dari PC lama (repo GitHub = private)
- [ ] Deploy key / token dibuat & clone berhasil di server baru
- [ ] `.env` produksi: `APP_KEY` disalin, `APP_DEBUG=false`, DB kredensial baru
- [ ] `migrate --force` (+ seed / restore data)
- [ ] `php artisan storage:link`
- [ ] Gambar soal lama tersalin / diimpor ulang
- [ ] Cron scheduler aktif
- [ ] Nginx + HTTPS (cloudflared) aktif, DNS `ujian.smp5tegal.sch.id` mengarah
- [ ] Login siswa `/login` & admin `/admin/login` teruji
- [ ] Satu ujian penuh teruji (join token -> jawab -> kumpulkan -> nilai)
- [ ] Backup rutin disiapkan (`docs/backup.bat` sebagai rujukan)
