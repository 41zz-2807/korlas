#!/usr/bin/env bash
#
# reset_db.sh — Bersihkan database & data aplikasi untuk penyiapan produksi.
#
# Alur:
#   1. Hapus database SQLite beserta file journal/wal/shm
#   2. Hapus semua bukti upload (storage/app/public/proofs) [kecuali --keep-proofs]
#   3. Buat file database kosong lalu migrasi fresh
#   4. [Opsional] seed data contoh dengan --seed
#   5. Bersihkan semua cache Laravel
#
# Penggunaan:
#   ./scripts/reset_db.sh                          # konfirmasi dulu
#   ./scripts/reset_db.sh --force                  # tanpa konfirmasi
#   ./scripts/reset_db.sh --seed                   # migrasi + seeder
#   ./scripts/reset_db.sh --keep-proofs            # jangan hapus bukti upload
#   DB_DATABASE=/data/database.sqlite ./scripts/reset_db.sh --force
#

set -euo pipefail

# Pindah ke root proyek agar artisan & path relatif benar.
BASE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$BASE_DIR"

if ! command -v php >/dev/null 2>&1; then
    echo "ERROR: php tidak ditemukan di PATH." >&2
    exit 1
fi

# ---- Parse argumen ----
SEED=0
KEEP_PROOFS=0
FORCE=0
for arg in "$@"; do
    case "$arg" in
        --seed) SEED=1 ;;
        --keep-proofs) KEEP_PROOFS=1 ;;
        --force) FORCE=1 ;;
        *)
            echo "Argumen tak dikenal: $arg" >&2
            echo "Gunakan: [--seed] [--keep-proofs] [--force]" >&2
            exit 1
            ;;
    esac
done

# ---- Tentukan lokasi database ----
# Prioritas: env DB_DATABASE -> nilai di .env -> default `database/database.sqlite`
DB_PATH="${DB_DATABASE:-}"
if [[ -z "$DB_PATH" && -f .env ]]; then
    DB_PATH="$(grep -E '^DB_DATABASE=' .env | head -1 | cut -d= -f2- | tr -d '"' || true)"
fi
DB_PATH="${DB_PATH:-database/database.sqlite}"
# Bila dikonfigurasi dengan awalan "sqlite:", buang prefix-nya
DB_PATH="${DB_PATH#sqlite:}"

if [[ -z "$DB_PATH" ]]; then
    echo "ERROR: path database kosong (DB_DATABASE tidak terisi)." >&2
    exit 1
fi

# ---- Konfirmasi destruktif ----
if [[ "$FORCE" != 1 ]]; then
    echo "Reset akan MENGHAPUS:"
    echo "  - database : $DB_PATH"
    if [[ "$KEEP_PROOFS" != 1 ]]; then
        echo "  - bukti upload: storage/app/public/proofs (DIHAPUS)"
    else
        echo "  - bukti upload: dipertahankan (--keep-proofs)"
    fi
    echo
    read -r -p 'Ketik "yes" untuk lanjut: ' answer
    if [[ "$answer" != "yes" ]]; then
        echo "Dibatalkan."
        exit 0
    fi
fi

# ---- Hapus database + bukti upload ----
echo "==> Menghapus database: $DB_PATH"
rm -f "$DB_PATH" "$DB_PATH-journal" "$DB_PATH-wal" "$DB_PATH-shm"

if [[ "$KEEP_PROOFS" != 1 && -d storage/app/public/proofs ]]; then
    echo "==> Menghapus bukti upload: storage/app/public/proofs"
    rm -rf storage/app/public/proofs
fi

# ---- Buat database kosong baru ----
mkdir -p "$(dirname "$DB_PATH")"
touch "$DB_PATH"
echo "==> Database kosong dibuat di: $DB_PATH"

# ---- Migrasi fresh (dan seeder bila diminta) ----
if [[ "$SEED" == 1 ]]; then
    echo "==> Menjalankan migrasi + seeder..."
    php artisan migrate:fresh --seed --force
else
    echo "==> Menjalankan migrasi..."
    php artisan migrate:fresh --force
fi

# ---- Bersihkan cache Laravel ----
echo "==> Membersihkan cache..."
php artisan optimize:clear || true

echo
echo "Selesai. Database siap untuk produksi."
echo "Jangan lupa untuk produksi:"
echo "  - APP_ENV=production & APP_DEBUG=false"
echo "  - Ganti ADMIN_USERNAME / ADMIN_PASSWORD dengan credential kuat"
echo "  - Rotasi TELEGRAM_BOT_TOKEN bila sebelumnya sempat terekspos"