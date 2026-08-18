#!/bin/bash
# ==========================================================
#  deploy.sh - Script Deploy Cepat & Otomatis untuk Puslah
#  Penggunaan: 
#    bash deploy.sh          (Deploy cepat tanpa rebuild)
#    bash deploy.sh --build  (Paksa rebuild image Docker)
# ==========================================================

set -e  # Berhenti jika ada error

APP_DIR=~/apps/puslah
CONTAINER_NAME=puslah-franken
WORKER_NAME=puslah-worker
SCHEDULER_NAME=puslah-scheduler
BRANCH=main
FORCE_BUILD=false

# Cek argumen --build atau -b
for arg in "$@"; do
    if [ "$arg" == "--build" ] || [ "$arg" == "-b" ]; then
        FORCE_BUILD=true
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

# 2. Fetch & Pull dari GitHub
echo ""
echo "📥 [1/6] Mengambil kode terbaru dari GitHub..."
git fetch origin "$BRANCH"
git reset --hard "origin/$BRANCH"
echo "   ✅ Kode terbaru berhasil ditarik."

NEW_COMMIT=$(git rev-parse HEAD)

# Deteksi apakah perlu rebuild Docker image
NEED_BUILD=$FORCE_BUILD

if [ "$FORCE_BUILD" = true ]; then
    echo "   ℹ️  Rebuild dipicu secara manual (flag --build)."
elif [ -n "$OLD_COMMIT" ] && [ "$OLD_COMMIT" != "$NEW_COMMIT" ]; then
    CHANGED_FILES=$(git diff --name-only "$OLD_COMMIT" "$NEW_COMMIT")
    if echo "$CHANGED_FILES" | grep -qE "^(Dockerfile|docker-compose\.yml|Caddyfile|docker/)"; then
        echo "   ℹ️  Terdeteksi perubahan pada konfigurasi Docker/Caddy. Rebuild diperlukan."
        NEED_BUILD=true
    fi
fi

# Cek juga jika container belum berjalan
if [ -z "$(docker ps -q -f name=^/${CONTAINER_NAME}$)" ]; then
    echo "   ℹ️  Container belum berjalan. Menjalankan container..."
    NEED_BUILD=true
fi

# 3. Build & Restart Container jika diperlukan
echo ""
if [ "$NEED_BUILD" = true ]; then
    echo "🔨 [2/6] Rebuild Docker image & restart stack..."
    docker compose build
    docker compose down
    docker compose up -d
    echo "   ✅ Image berhasil di-build & container dinyalakan ulang."
else
    echo "⚡ [2/6] Lewati build Docker (tidak ada perubahan file Docker/Caddy)..."
    docker compose up -d
    echo "   ✅ Container aktif."
fi

# 4. Update dependencies jika composer.lock berubah
if [ -n "$OLD_COMMIT" ] && [ "$OLD_COMMIT" != "$NEW_COMMIT" ]; then
    if echo "$CHANGED_FILES" | grep -q "composer.lock"; then
        echo ""
        echo "📦 Terdeteksi perubahan composer.lock, menjalankan composer install..."
        docker exec "$CONTAINER_NAME" composer install --no-dev --optimize-autoloader
        echo "   ✅ Dependencies berhasil diupdate."
    fi
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
echo "   ✅ Cache berhasil di-generate."

# 7. Reload Web Server & Restart Queue Worker & Scheduler
echo ""
echo "👷 [5/6] Reload Server, Worker & Scheduler..."
# Restart frankenphp agar membaca file PHP terbaru di OPcache/Worker
docker restart "$CONTAINER_NAME" >/dev/null 2>&1 || true
docker restart "$WORKER_NAME" >/dev/null 2>&1 || true
docker restart "$SCHEDULER_NAME" >/dev/null 2>&1 || true
echo "   ✅ Web Server, Worker & Scheduler di-reload dengan kode terbaru."

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
