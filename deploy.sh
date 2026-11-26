#!/bin/bash
# ==========================================================
#  deploy.sh - Script Deploy Cepat & Otomatis untuk Puslah
#  Penggunaan: 
#    bash deploy.sh          (Deploy cepat tanpa rebuild)
#    bash deploy.sh --build  (Paksa rebuild image Docker)
#    bash deploy.sh --backup (Backup database sebelum deploy)
# ==========================================================

set -e  # Berhenti jika ada error

APP_DIR=~/apps/puslah
CONTAINER_NAME=puslah-franken
WORKER_NAME=puslah-worker
SCHEDULER_NAME=puslah-scheduler
BOT_NAME=puslah-telegram-bot
BRANCH=fitur/surat-undangan
FORCE_BUILD=false
DO_BACKUP=false

# Cek argumen --build, -b, atau --backup
for arg in "$@"; do
    if [ "$arg" == "--build" ] || [ "$arg" == "-b" ]; then
        FORCE_BUILD=true
    elif [ "$arg" == "--backup" ]; then
        DO_BACKUP=true
    fi
done

echo ""
echo "=========================================="
echo "  🚀 Memulai Deploy Puslah..."
echo "=========================================="
echo ""

# 1. Masuk ke direktori aplikasi
cd "$APP_DIR"
echo "📂 Direktori: $(pwd)"

# Simpan commit lama sebelum pull untuk deteksi perubahan file
OLD_COMMIT=$(git rev-parse HEAD 2>/dev/null || echo "")

# 1b. Backup database sebelum deploy jika argumen --backup diberikan
if [ "$DO_BACKUP" = true ]; then
    echo ""
    echo "💾 [0/6] Membuat backup database sebelum deploy..."
    mkdir -p storage/app/backups-predeploy
    BACKUP_TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
    echo "${OLD_COMMIT}" > "storage/app/backups-predeploy/predeploy_${BACKUP_TIMESTAMP}.commit"
    BACKUP_FILE="storage/app/backups-predeploy/db_predeploy_${BACKUP_TIMESTAMP}.sql.gz"

    # Baca konfigurasi DB dari .env
    get_env_val() {
        grep -E "^${1}=" .env 2>/dev/null | tail -n 1 | cut -d '=' -f2- | tr -d '\r' | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
    }
    DB_HOST=$(get_env_val "DB_HOST")
    DB_PORT=$(get_env_val "DB_PORT")
    DB_DATABASE=$(get_env_val "DB_DATABASE")
    DB_USERNAME=$(get_env_val "DB_USERNAME")
    DB_PASSWORD=$(get_env_val "DB_PASSWORD")

    BACKUP_OK=false

    # Cara 1: Cek mysqldump di dalam container puslah-franken via Spatie Backup
    if docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$" && docker exec "$CONTAINER_NAME" sh -c "command -v mysqldump >/dev/null 2>&1"; then
        docker exec "$CONTAINER_NAME" php artisan backup:run --only-db --disable-notifications
        BACKUP_OK=true
    fi

    # Cara 2: Dump langsung dari container MySQL/MariaDB di network mysql-stack_mysql_network
    if [ "$BACKUP_OK" = false ] && [ -n "$DB_DATABASE" ]; then
        MYSQL_CONTAINER=""
        if [ -n "$DB_HOST" ] && docker ps --format '{{.Names}}' | grep -q "^${DB_HOST}$"; then
            MYSQL_CONTAINER="$DB_HOST"
        else
            MYSQL_CONTAINER=$(docker ps --filter "network=mysql-stack_mysql_network" --format '{{.Names}}' | grep -iE "mysql|mariadb|db" | head -n 1 || true)
        fi

        if [ -n "$MYSQL_CONTAINER" ]; then
            echo "   ℹ️  Menggunakan container database: ${MYSQL_CONTAINER}"
            if docker exec -e MYSQL_PWD="$DB_PASSWORD" "$MYSQL_CONTAINER" sh -c \
                'if command -v mariadb-dump >/dev/null 2>&1; then exec mariadb-dump "$@"; else exec mysqldump "$@"; fi' \
                _ -u "$DB_USERNAME" --single-transaction --quick --no-tablespaces "$DB_DATABASE" | gzip > "$BACKUP_FILE" && [ -s "$BACKUP_FILE" ]; then
                BACKUP_OK=true
            else
                rm -f "$BACKUP_FILE"
            fi
        fi
    fi

    # Cara 3: Fallback mysqldump dari host Ubuntu
    if [ "$BACKUP_OK" = false ] && [ -n "$DB_DATABASE" ] && command -v mysqldump >/dev/null 2>&1; then
        echo "   ℹ️  Menggunakan mysqldump dari host..."
        if MYSQL_PWD="$DB_PASSWORD" mysqldump -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" --single-transaction --quick --no-tablespaces "$DB_DATABASE" | gzip > "$BACKUP_FILE" && [ -s "$BACKUP_FILE" ]; then
            BACKUP_OK=true
        else
            rm -f "$BACKUP_FILE"
        fi
    fi

    if [ "$BACKUP_OK" = true ]; then
        echo "   ✅ Backup database selesai (file: ${BACKUP_FILE}, commit sebelum deploy: ${OLD_COMMIT:-unknown})."
    else
        echo "   ❌ Gagal membuat backup database (mysqldump tidak ditemukan di container app, container DB, maupun host). Deploy dibatalkan."
        exit 1
    fi
fi

# 2. Fetch & Pull dari GitHub
echo ""
echo "📥 [1/6] Mengambil kode terbaru dari GitHub..."
git fetch origin "$BRANCH"
git reset --hard "origin/$BRANCH"
echo "   ✅ Kode terbaru berhasil ditarik."

NEW_COMMIT=$(git rev-parse HEAD)

# Deteksi apakah perlu rebuild Docker image (pakai hash di .git/ agar tahan gagal ulang)
DOCKER_HASH_FILE=".git/deploy-docker.hash"
VENDOR_HASH_FILE=".git/deploy-vendor.hash"
CURRENT_DOCKER_HASH=$(cat Dockerfile docker-compose.yml Caddyfile 2>/dev/null | sha256sum 2>/dev/null | awk '{print $1}')
SAVED_DOCKER_HASH=$(cat "$DOCKER_HASH_FILE" 2>/dev/null || echo "")

NEED_BUILD=$FORCE_BUILD
CHANGED_FILES=""
if [ -n "$OLD_COMMIT" ] && [ "$OLD_COMMIT" != "$NEW_COMMIT" ]; then
    CHANGED_FILES=$(git diff --name-only "$OLD_COMMIT" "$NEW_COMMIT")
fi

if [ "$FORCE_BUILD" = true ]; then
    echo "   ℹ️  Rebuild dipicu secara manual (flag --build)."
elif [ -z "$SAVED_DOCKER_HASH" ] || [ "$SAVED_DOCKER_HASH" != "$CURRENT_DOCKER_HASH" ]; then
    echo "   ℹ️  Terdeteksi perubahan Dockerfile/Compose/Caddy (atau deploy baru). Rebuild diperlukan."
    NEED_BUILD=true
elif echo "$CHANGED_FILES" | grep -qE "^(Dockerfile|docker-compose\.yml|Caddyfile|docker/)"; then
    echo "   ℹ️  Terdeteksi perubahan pada konfigurasi Docker/Caddy. Rebuild diperlukan."
    NEED_BUILD=true
fi

# Cek apakah image Docker untuk layanan utama sudah ada
# Jika image belum ada sama sekali di sistem host, baru lakukan build
if [ "$NEED_BUILD" = false ]; then
    IMAGE_EXISTS=$(docker compose images -q "$CONTAINER_NAME" 2>/dev/null || docker images -q "$CONTAINER_NAME" 2>/dev/null || echo "")
    if [ -z "$IMAGE_EXISTS" ]; then
        echo "   ℹ️  Image Docker belum tersedia di sistem. Build pertama kali diperlukan."
        NEED_BUILD=true
    fi
fi

# Pastikan versi PHP di container yang sedang berjalan sesuai kebutuhan Dockerfile (>= 8.4)
if [ "$NEED_BUILD" = false ]; then
    if ! docker exec "$CONTAINER_NAME" php -r "exit(version_compare(PHP_VERSION, '8.4.0', '<') ? 1 : 0);" >/dev/null 2>&1; then
        echo "   ℹ️  Versi PHP di container masih < 8.4. Rebuild image PHP 8.4 diperlukan."
        NEED_BUILD=true
    fi
fi

# 3. Build & Restart Container jika diperlukan
echo ""
if [ "$NEED_BUILD" = true ]; then
    echo "🔨 [2/6] Rebuild Docker image & restart stack..."
    docker compose build
    docker compose down
    docker compose up -d --build
    echo "$CURRENT_DOCKER_HASH" > "$DOCKER_HASH_FILE"
    echo "   ✅ Image berhasil di-build & container dinyalakan ulang."
else
    echo "⚡ [2/6] Lewati build Docker (image sudah sesuai & PHP 8.4 aktif)..."
    docker compose up -d --no-build
    echo "   ✅ Container aktif."
fi

# 4. Update dependencies jika composer.lock berubah atau vendor belum sinkron
CURRENT_VENDOR_HASH=$(sha256sum composer.lock 2>/dev/null | awk '{print $1}')
SAVED_VENDOR_HASH=$(cat "$VENDOR_HASH_FILE" 2>/dev/null || echo "")

if [ "$NEED_BUILD" = true ] || [ ! -d "vendor" ] || [ -z "$SAVED_VENDOR_HASH" ] || [ "$SAVED_VENDOR_HASH" != "$CURRENT_VENDOR_HASH" ] || echo "$CHANGED_FILES" | grep -q "composer.lock"; then
    echo ""
    echo "📦 Menyinkronkan dependencies (composer install)..."
    rm -f bootstrap/cache/*.php
    docker exec "$CONTAINER_NAME" composer install --no-dev --optimize-autoloader --no-interaction
    echo "$CURRENT_VENDOR_HASH" > "$VENDOR_HASH_FILE"
    echo "   ✅ Dependencies berhasil diupdate."
fi

# 5. Jalankan migrasi (tanpa --fresh, hanya yang baru)
echo ""
echo "🗄️  [3/6] Menjalankan migrasi database..."
docker exec "$CONTAINER_NAME" php artisan migrate --force
echo "   ✅ Migrasi selesai."

# 6. Optimasi Laravel (cache config, route, view)
echo ""
echo "⚡ [4/6] Optimasi Laravel..."
docker exec "$CONTAINER_NAME" php artisan optimize:clear
docker exec "$CONTAINER_NAME" php artisan config:cache
docker exec "$CONTAINER_NAME" php artisan route:cache
docker exec "$CONTAINER_NAME" php artisan view:cache
docker exec "$CONTAINER_NAME" php artisan event:cache
docker exec "$CONTAINER_NAME" php artisan icons:cache
docker exec "$CONTAINER_NAME" php artisan filament:optimize
echo "   ✅ Cache berhasil di-generate."

# 7. Reload Web Server & Restart Queue Worker & Scheduler
echo ""
echo "👷 [5/6] Reload Server, Worker & Scheduler..."
# Restart frankenphp agar membaca file PHP terbaru di OPcache/Worker
docker restart "$CONTAINER_NAME" >/dev/null 2>&1 || true
docker restart "$WORKER_NAME" >/dev/null 2>&1 || true
docker restart "$SCHEDULER_NAME" >/dev/null 2>&1 || true
docker restart "$BOT_NAME" >/dev/null 2>&1 || true
echo "   ✅ Web Server, Worker, Scheduler & Bot Telegram di-reload dengan kode terbaru."

# 8. Bersihkan image Docker lama hanya jika baru saja build
echo ""
if [ "$NEED_BUILD" = true ]; then
    echo "🧹 [6/6] Membersihkan image Docker lama yang tidak terpakai..."
    docker image prune -f
    echo "   ✅ Image lama dibersihkan."
else
    echo "🧹 [6/6] Lewati pembersihan image."
fi

# 9. Verifikasi
echo ""
echo "=========================================="
echo "  ✅ Deploy Selesai!"
echo "=========================================="
echo ""
echo "📊 Status Container:"
docker ps --filter "name=puslah" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
echo ""
echo "🧠 PHP Memory Limit:"
docker exec "$CONTAINER_NAME" php -r "echo ini_get('memory_limit') . PHP_EOL;"
echo ""
echo "⏱️  Max Execution Time:"
docker exec "$CONTAINER_NAME" php -r "echo ini_get('max_execution_time') . PHP_EOL;"
echo ""
