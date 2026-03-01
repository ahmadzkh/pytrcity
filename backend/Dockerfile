# Menggunakan image PHP CLI super ringan
FROM php:8.2-cli

# Instal dependensi sistem dan ekstensi PostgreSQL
RUN apt-get update && apt-get install -y libpq-dev unzip \
    && docker-php-ext-install pdo pdo_pgsql

# Ambil Composer dari image resmi
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Atur direktori kerja di dalam kontainer
WORKDIR /app

# Salin seluruh kode backend lokal ke dalam kontainer
COPY . .

# Instal dependensi kerangka kerja Laravel
RUN composer install --no-dev --optimize-autoloader

# Izinkan penulisan pada folder storage
RUN chmod -R 777 storage bootstrap/cache

# Eksekusi migrasi otomatis & jalankan peladen (menggunakan port dinamis dari Render)
CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
