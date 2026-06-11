#!/bin/bash
# ==========================================================
#  deploy.sh - Script Deploy Otomatis untuk Puslah
#  Penggunaan: bash deploy.sh
# ==========================================================

set -e  # Berhenti jika ada error

APP_DIR=~/apps/puslah
CONTAINER_NAME=puslah-franken
WORKER_NAME=puslah-worker
BRANCH=main

echo ""
echo "=========================================="
echo "  🚀 Memulai Deploy Puslah..."
echo "=========================================="
echo ""

# 1. Masuk ke direktori aplikasi
cd "$APP_DIR"
echo "📂 Direktori: $(pwd)"

# 2. Fetch & Pull dari GitHub
echo ""
echo "📥 [1/8] Mengambil kode terbaru dari GitHub..."
git fetch origin "$BRANCH"
git reset --hard "origin/$BRANCH"
echo "   ✅ Kode terbaru berhasil ditarik."

# 3. Build ulang Docker image
echo ""
echo "🔨 [2/8] Rebuild Docker image..."
docker compose build
echo "   ✅ Image berhasil di-build."

# 4. Restart semua container (web + worker)
echo ""
echo "🔄 [3/8] Restart container (web + worker)..."
docker compose down
docker compose up -d
echo "   ✅ Container web dan worker berhasil dinyalakan."

# 5. Jalankan migrasi (tanpa --fresh, hanya yang baru)
echo ""
echo "🗄️  [4/8] Menjalankan migrasi database..."
docker exec "$CONTAINER_NAME" php artisan migrate --force
echo "   ✅ Migrasi selesai."

# 6. Optimasi Laravel (cache config, route, view)
echo ""
echo "⚡ [5/8] Optimasi Laravel..."
docker exec "$CONTAINER_NAME" php artisan config:cache
docker exec "$CONTAINER_NAME" php artisan route:cache
docker exec "$CONTAINER_NAME" php artisan view:cache
docker exec "$CONTAINER_NAME" php artisan event:cache
echo "   ✅ Cache berhasil di-generate."

# 7. Restart queue worker agar memuat kode baru
echo ""
echo "👷 [6/8] Restart Queue Worker..."
docker restart "$WORKER_NAME"
echo "   ✅ Worker di-restart dengan kode terbaru."

# 8. Bersihkan image Docker yang tidak terpakai
echo ""
echo "🧹 [7/8] Membersihkan image Docker lama yang tidak terpakai..."
docker image prune -f
echo "   ✅ Image lama dibersihkan."

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
