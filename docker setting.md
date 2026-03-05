services:
  puslah-franken:
    container_name: puslah-franken
    restart: unless-stopped
    environment:
      - PUID=1000
      - PGID=1000
      - SERVER_NAME=:80
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - "8089:80"
    volumes:
      - .:/app
      - ./Caddyfile:/etc/caddy/Caddyfile:ro
      - .:/var/www/html
    networks:
      - default
      - mysql-stack_mysql_network

networks:
  default: {}
  mysql-stack_mysql_network:
    external: true


# Image dasar FrankenPHP (PHP 8.3 + Caddy)
FROM dunglas/frankenphp:php8.3

ENV SERVER_NAME=":80"
# Lokasi proyek di dalam container
WORKDIR /app

# Sistem deps & ekstensi PHP yang umum dipakai Laravel/Filament
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) intl gd zip pdo_mysql \
    && docker-php-ext-enable intl gd zip pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY . /app
# Composer (buat install deps dari dalam container)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy konfigurasi Caddy/FrankenPHP
COPY Caddyfile /etc/caddy/Caddyfile

EXPOSE 80
EXPOSE 443
EXPOSE 443/udp

# (Opsional tapi berguna) set permission direktori Laravel
# Catatan: karena kamu bind-mount ., permission tetap mengikuti host.
# Perintah ini tetap aman dijalankan.
RUN mkdir -p /app/storage /app/bootstrap/cache \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache


{
  frankenphp
  # yang benar:
  order cgi-php before file_server
}

puslah.bpsdemak.com {
    root * /app/public
    encode zstd br gzip

    # Pastikan /livewire/* tidak ditangani sebagai static file
    @livewire path /livewire/*
    handle @livewire {
        rewrite * /index.php?{query}
    }

    php_server
    file_server
}

