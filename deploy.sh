#!/usr/bin/env bash
# ============================================================
# Skrip deploy CBT TKA Sekolah (Ubuntu)
# Idempoten: aman dijalankan berulang kali.
#
# Pemakaian:
#   sudo chmod +x deploy.sh
#   ./deploy.sh            # deploy tanpa migrate
#   ./deploy.sh --migrate  # deploy + jalankan migrate --force
# ============================================================

set -euo pipefail

APP_DIR="/var/www/cbt-tka"
PHP="php8.3"
WWW_USER="www-data"
WWW_GROUP="www-data"
RUN_MIGRATE=false

for arg in "$@"; do
    case "$arg" in
        --migrate) RUN_MIGRATE=true ;;
        *) echo "Argumen tidak dikenal: $arg"; exit 1 ;;
    esac
done

cd "$APP_DIR"

echo "==> Pull kode terbaru (git) =="
git pull origin main

echo "==> Install dependensi PHP =="
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Install dependensi frontend =="
npm ci

echo "==> Build asset =="
npm run build

if [ "$RUN_MIGRATE" = true ]; then
    echo "==> Migrasi database =="
    "$PHP" artisan migrate --force
fi

echo "==> Cache konfigurasi produksi =="
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache

echo "==> Perbaiki izin file =="
sudo chown -R "$WWW_USER":"$WWW_GROUP" "$APP_DIR"
sudo chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

echo "==> Reload PHP-FPM =="
sudo systemctl reload php8.3-fpm

echo "==> Deploy selesai =="
