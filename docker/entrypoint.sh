#!/bin/sh
set -e

APP_DIR=/var/www/html
ENV_FILE=/data/.env
ENV_TEMPLATE=$APP_DIR/docker/env.template

cd "$APP_DIR"

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

# 3) Run migrations (idempotent)
php artisan migrate --force

# 4) Seed only on first boot
if [ ! -f /data/.seeded ]; then
    php artisan db:seed --force
    touch /data/.seeded
fi

# 5) Ensure runtime-writable permissions
chown -R www-data:www-data /data storage bootstrap/cache

# 6) Set up Laravel scheduler via cron (runs every minute)
export TZ=Asia/Jakarta
mkdir -p /etc/cron.d
printf "SHELL=/bin/bash\nTZ=Asia/Jakarta\nPATH=/usr/local/bin:/usr/bin:/bin\n* * * * * /usr/local/bin/php /var/www/html/artisan schedule:run >> /var/www/html/storage/logs/scheduler.log 2>&1\n" > /etc/cron.d/laravel-scheduler
chmod 0644 /etc/cron.d/laravel-scheduler
crontab /etc/cron.d/laravel-scheduler
cron

exec apache2-foreground