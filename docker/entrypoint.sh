#!/bin/sh
set -e

APP_DIR=/var/www/html
ENV_FILE=/data/.env
ENV_TEMPLATE=$APP_DIR/docker/env.template

cd "$APP_DIR"

# Kunci konfigurasi basis data: SQLite SELALU di volume /data. Env proc
# (env_file compose / .env.docker lama) bisa memuat DB_DATABASE yang salah
# dan menimpa .env (dotenv immutable); di-export di sini agar deterministik.
export DB_CONNECTION=sqlite
export DB_DATABASE=/data/database.sqlite
mkdir -p /data
[ -f /data/database.sqlite ] || touch /data/database.sqlite

# Paksa mode produksi pada setiap boot, terlepas dari APP_ENV/APP_DEBUG
# di .env.docker lama (yang umumnya berisi local/debug=true).
export APP_ENV=production
export APP_DEBUG=false

# 1) Siapkan `.env` persisten di volume /data (hanya sekali), lalu pautkan ke project.
#    Template memakai ${VAR}; nilai diisi dari env container (docker-compose env_file).
if [ ! -f "$ENV_FILE" ]; then
    if [ -f "$ENV_TEMPLATE" ]; then
        envsubst < "$ENV_TEMPLATE" > "$ENV_FILE"
    else
        : > "$ENV_FILE"
    fi
    # Paksa mode produksi, jangan sampai ter-override file .env.docker lama (APP_ENV=local)
    sed -i 's/^APP_ENV=.*/APP_ENV=production/' "$ENV_FILE"
    sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/' "$ENV_FILE"
    chown www-data:www-data "$ENV_FILE"
fi

ln -sfn "$ENV_FILE" "$APP_DIR/.env"
chown -h www-data:www-data "$APP_DIR/.env"

# 2) Generate APP_KEY hanya bila belum terisi (stabil antar-restart, tidak
#    memutus sesi users di setiap deploy)
if ! grep -qE '^APP_KEY=.+' "$ENV_FILE"; then
    php artisan key:generate --force
fi

# 3) Pastikan APP_KEY tersedia sebagai env proses. `.env.docker lama` sering
#    memuat `APP_KEY=` kosong yang di-inject compose (dotenv immutable =
#    nilai kosong memblokir .env), sehingga app crash "No application
#    encryption key". Export dari file agar Apache & cron selalu mendapat key.
FILE_KEY="$(sed -nE 's/^APP_KEY=(.*)$/\1/p' "$ENV_FILE" | tail -1)"
if [ -n "$FILE_KEY" ] && [ -z "${APP_KEY:-}" ]; then
    export APP_KEY="$FILE_KEY"
fi

# 4) Run migrations (idempotent)
php artisan migrate --force

# 5) Seed only on first boot
if [ ! -f /data/.seeded ]; then
    php artisan db:seed --force
    touch /data/.seeded
fi

# 6) Ensure runtime-writable permissions
chown -R www-data:www-data /data storage bootstrap/cache

# 7) Set up Laravel scheduler via cron (runs every minute)
export TZ=Asia/Jakarta
mkdir -p /etc/cron.d
printf "SHELL=/bin/bash\nTZ=Asia/Jakarta\nPATH=/usr/local/bin:/usr/bin:/bin\n* * * * * /usr/local/bin/php /var/www/html/artisan schedule:run >> /var/www/html/storage/logs/scheduler.log 2>&1\n" > /etc/cron.d/laravel-scheduler
chmod 0644 /etc/cron.d/laravel-scheduler
crontab /etc/cron.d/laravel-scheduler
cron

exec apache2-foreground