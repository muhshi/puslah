FROM dunglas/frankenphp:php8.3

ENV SERVER_NAME=":80"
WORKDIR /app

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
    libicu-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libwebp-dev \
    zip imagemagick libmagickwand-dev ghostscript \
    autoconf build-essential pkg-config \
    webp \
    jpegoptim optipng pngquant gifsicle \
    ; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" intl gd zip pdo_mysql; \
    pecl install imagick; \
    docker-php-ext-enable imagick; \
    rm -rf /var/lib/apt/lists/* /tmp/pear

# Jika compose kamu PAKAI bind-mount ke /app, baris COPY ini opsional
COPY . /app
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY Caddyfile /etc/caddy/Caddyfile

EXPOSE 80 443 443/udp

RUN mkdir -p /app/storage /app/bootstrap/cache \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache
