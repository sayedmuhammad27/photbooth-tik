#!/bin/sh
set -e

# Port dinamis dari Render (default 80 bila dijalankan lokal)
PORT="${PORT:-80}"
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf 2>/dev/null || true
sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:${PORT}>/g" /etc/apache2/sites-available/000-default.conf 2>/dev/null || true

# Pastikan folder penyimpanan dan cache tersedia
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/app/public/templates \
         /var/www/html/storage/app/public/results \
         /var/www/html/bootstrap/cache

# Jika menggunakan database SQLite, pastikan file sqlite dibuat
if [ "$DB_CONNECTION" = "sqlite" ] || [ -z "$DB_CONNECTION" ]; then
    mkdir -p /var/www/html/database
    if [ ! -f /var/www/html/database/database.sqlite ]; then
        touch /var/www/html/database/database.sqlite
    fi
fi

# Berikan hak akses ke www-data
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# Buat APP_KEY jika belum diset di environment Render
if [ -z "$APP_KEY" ]; then
    echo "APP_KEY belum diset, membuat kunci baru..."
    php artisan key:generate --force
fi

# Jalankan migrasi dan seeder database secara otomatis
echo "Menjalankan migrasi database..."
php artisan migrate --force --isolated || php artisan migrate --force

echo "Menjalankan seeder awal (admin & template default)..."
php artisan db:seed --force || true

# Tautkan storage link
php artisan storage:link || true

# Optimasi cache konfigurasi dan rute di production
echo "Mengoptimasi cache Laravel..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "Aplikasi siap! Menjalankan Apache di port ${PORT}..."
exec apache2-foreground

