# Menggunakan image PHP CLI yang ringan
FROM php:8.2-cli

# Menginstal dependensi sistem dan ekstensi PostgreSQL
RUN apt-get update && apt-get install -y libpq-dev unzip \
    && docker-php-ext-install pdo pdo_pgsql

# Mengambil Composer dari image resmi
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Mengatur direktori kerja di dalam kontainer
WORKDIR /app

# Menyalin seluruh kode backend lokal ke dalam kontainer
COPY . .

# Menginstal dependensi kerangka kerja Laravel
RUN composer install --no-dev --optimize-autoloader

# Memberikan izin penulisan pada folder penyimpan
RUN chmod -R 777 storage bootstrap/cache

# Mendeklarasikan port untuk perutean Back4App
EXPOSE 8000

# Menyalakan peladen API secara eksklusif (tanpa proses migrasi)
CMD php artisan serve --host=0.0.0.0 --port=8000
