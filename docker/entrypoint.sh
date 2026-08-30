#!/bin/sh
set -e

# Generate app key if not set
php artisan key:generate --force || true

# Run migrations (idempotent)
php artisan migrate --force

# Seed only on first boot
if [ ! -f /data/.seeded ]; then
    php artisan db:seed --force
    touch /data/.seeded
fi

# Ensure runtime-writable permissions
chown -R www-data:www-data /data storage bootstrap/cache

# Set up Laravel scheduler via cron (runs every minute)
export TZ=Asia/Jakarta
mkdir -p /etc/cron.d
printf "SHELL=/bin/bash\nTZ=Asia/Jakarta\nPATH=/usr/local/bin:/usr/bin:/bin\n* * * * * /usr/local/bin/php /var/www/html/artisan schedule:run >> /var/www/html/storage/logs/scheduler.log 2>&1\n" > /etc/cron.d/laravel-scheduler
chmod 0644 /etc/cron.d/laravel-scheduler
crontab /etc/cron.d/laravel-scheduler
cron

exec apache2-foreground
