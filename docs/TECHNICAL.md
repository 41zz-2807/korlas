# Dokumentasi Teknis — Korlas

Aplikasi pencatatan keuangan kas kelas berbasis **Laravel 11** dengan database **SQLite**, di-deploy sebagai **container Docker** (Apache + PHP 8.3) di belakang reverse proxy.

Dokumen ini mencakup arsitektur, model data, rute, keamanan, deployment, dan operasional. Semua nilai rahasia (kredensial admin, token bot, alamat server aktual) ditulis sebagai *placeholder* `<...>`.

---

## 1. Ringkasan Arsitektur

```
[Browser] → HTTPS → [Reverse Proxy (proxy_passthrough)] → :7474 → [Container Korlas]
                                                                    │  Apache :80 (PHP 8.3)
                                                                    ├── volume korlas-data   → /data       (SQLite, .env persisten)
                                                                    └── volume korlas-storage → /var/www/html/storage
```

- **Runtime**: Apache + mod_php (`php:8.3-apache`), `public/` sebagai DocumentRoot.
- **Database**: SQLite single-file di `/data/database.sqlite` (volume `korlas-data`), dijamin selalu ada oleh entrypoint.
- **Storage**: file bukti upload disimpan di `storage/app/public/proofs`, disimbolkan ke `/storage` (volume `korlas-storage`).
- **Penjadwalan**: Laravel Scheduler via cron setiap menit (kirim laporan rekap berkala ke bot/chat internal).
- **Stack JS/CSS**: Bootstrap 5.3 + Bootstrap Icons + Chart.js dari CDN, plus `public/css/app.css` (path relatif `/css/app.css`, tidak bergantung `APP_URL`).

---

## 2. Struktur Direktori

```
app/
├── Console/Commands/TelegramReport.php   # laporan rekap berkala (scheduler)
├── Http/Controllers/
│   ├── AdminController.php               # login admin + CRUD transaksi + backup/restore
│   ├── PublicController.php              # halaman publik + unduh bukti
│   └── RekapController.php               # matriks pembayaran + export (PDF/XLSX)
├── Http/Middleware/EnsureAdmin.php       # guard sesi admin
├── Models/ (Announcement, BankAccount, Transaction)
└── Services/TelegramService.php          # wrapper API bot Telegram
config/                                   # konfigurasi (app, services, database, dsb.)
database/
├── migrations/                           # skema tabel
└── seeders/DatabaseSeeder.php            # data contoh (TIDAK dipakai produksi)
docker/
├── apache.conf                           # konfigurasi virtual host Apache
├── entrypoint.sh                         # bootstrap runtime container (build .env, key, migrate, siswa)
├── env.template                          # template environment produksi (placeholder)
└── siswa.txt                             # daftar siswa sumber rekap & kas kelas
scripts/
├── reset_db.sh                           # reset total database + uploads untuk produksi
├── import_backup.php                     # impor data dari SQLite lama (satu kali)
└── backfill_category.php                 # perbaikan kategori data hasil impor (satu kali)
resources/views/                          # Blade (public, admin, rekap, layout)
routes/web.php                            # seluruh rute HTTP aplikasi
tests/Feature/SecurityTest.php            # uji keamanan (rate limit, SQLi, XSS, login)
```

---

## 3. Model Data

| Tabel | Kolom penting | Keterangan |
|---|---|---|
| `transactions` | `transaction_date`, `name`, `payment_method`, `description`, `type` (`income`/`expense`), `amount`, `proof_path`, `category` (`kas`/`komite`/`lain_lain`), `months` (JSON), `recipient` | Catatan arus kas. `amount` decimal(15,2) |
| `bank_accounts` | `bank_name`, `account_number`, `account_holder`, `is_active` | Rekening tujuan publik |
| `announcements` | `content`, `is_active` | Pengumuman di halaman publik |
| `users` / `sessions` / `cache` / `jobs` | — | Standar Laravel (auth tidak dipakai; sesi file) |

**Sumber daftar siswa**: file `storage/app/siswa.txt` (satu nama per baris), dipakai untuk form kas kelas dan matriks rekap. File disediakan container dari `docker/siswa.txt` saat boot.

---

## 4. Rute & Otorisasi

Semua rute di `routes/web.php`. Pola otorisasi: **sesi flag** `admin_authenticated` (bukan Laravel auth/guard), diperiksa middleware `EnsureAdmin`.

| Metode | URI | Akses | Keterangan |
|---|---|---|---|
| GET | `/` | Publik | Dashboard arus kas, filter `from`/`to`, paginasi 10 baris |
| GET | `/rekap` | Publik | Matriks status pembayaran per siswa |
| GET | `/rekap/export` | Publik (throttle) | Export PDF / XLSX |
| GET | `/transactions/{trx}/proof` | Publik (throttle) | Unduh bukti transaksi |
| GET | `/transactions/table` | Publik (throttle) | Partial tabel (AJAX paginasi) |
| GET | `/admin/login` | Publik | Form login |
| POST | `/admin/login` | **throttle** | Login admin, rate-limit ketat |
| POST | `/admin/logout` | Publik | Logout |
| GET/POST/PATCH/DELETE | `/admin/...`, `/transactions/...` | Admin | CRUD transaksi, backup/restore DB, laporan bot |
| GET | `/up` | — | Health check ringan (tanpa DB) |

Rute admin juga dibungkus `throttle:admin` (30/menit/IP). Rute publik sensitif (proof/export/table) dibatasi `throttle:public` (60/menit/IP).

---

## 5. Fitur Utama

### 5.1 Publik
- Ringkasan saldo, grafik harian (Chart.js) per periode tahun ajaran (Juli–Juni).
- Tabel transaksi dengan filter tanggal dan paginasi AJAX.
- Unduh bukti pembayaran (gambar/PDF) langsung dari URL publik.
- Rekap pembayaran: matriks siswa × bulan, export PDF (TCPDF) dan XLSX murni (zip XML).

### 5.2 Admin (sesi)
- Login username+password; kredensial diambil dari env, **tanpa nilai bawaan** (lihat §7).
- Input transaksi:
  - **Pengeluaran**: tanggal, metode, penerima, nominal, deskripsi, bukti.
  - **Pemasukan Kas kelas**: Rp 15.000/bulan per bulan terpilih, otomatis dibuat N baris.
  - **Pemasukan Komite / lain-lain**: nominal bebas.
- Edit/hapus transaksi, hapus massal (checkbox), validasi tipe berkas `jpg/jpeg/png/pdf ≤ 5 MB`.
- **Bukti multi-file**: dikompresi menjadi satu PDF via TCPDF.
- Backup database SQLite (download) & restore (upload, divalidasi + backup pre-restore otomatis), backup zip uploads.
- Kirim laporan rekap ke bot/chat internal.

### 5.3 Rekap
- `paidMatrix`: mapping `SISWA → bulan LUNAS` dari semua transaksi income ber-kategori.
- Export filenama + konten: `htmlspecialchars`/Escape XML untuk nama dan sel (anti-injection lewat konten sel).

---

## 6. Keamanan

| Aspek | Implementasi |
|---|---|
| **Brute-force / Rate limit** | 3 limiter bernama di `AppServiceProvider`: `admin-login` (5/menit per user+IP, 20/menit per IP), `admin` (30/menit), `public` (60/menit). Terpasang via middleware `throttle:*` |
| **Login** | Perbandingan timing-safe `hash_equals`; `session()->regenerate()` setelah sukses (anti session fixation); tanpa kredensial → fail-closed (403/503 di produksi) |
| **Default credential** | Tidak ada default; `ADMIN_USERNAME`/`ADMIN_PASSWORD` wajib diisi env, jika kosong login ditolak |
| **XSS** | Semua output Blade pakai `{{ }}` (auto-escape) & `@json`; tanpa `{!! !!}`. Teruji dengan payload `<script>`/`<img onerror>` |
| **SQLi** | Hanya Query Builder/Eloquent (prepared statement); tidak ada `DB::raw`/`whereRaw`. Teruji payload `' OR 1=1 --` |
| **Upload** | Validasi MIME+ekstensi + limit ukuran; skrip tidak dijalankan (disajikan via `storage:link`/download) |
| **Restore DB** | Ekstensi diwajibkan `.sqlite`, validasi isi SQLite (tabel non-kosong), backup lama otomatis sebelum timpa |
| **Header (proxy passthrough)** | HSTS, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, CSP ketat, `Referrer-Policy` |
| **APP_KEY** | Persisten di `/data/.env`, digenerate hanya bila kosong; export ke process env agar tidak tertimpa nilai kosong dari compose |
| **Mode produksi** | Entrypoint memaksa `APP_ENV=production`, `APP_DEBUG=false` tiap boot |

**Testing**: `tests/Feature/SecurityTest.php` — 5× login salah → redirect, ke-6 → **429**; payload SQLi gagal login; konten user di-escape (XSS); login valid → sesi ter-regenerate. Seluruh suite: `vendor/bin/phpunit`.

---

## 7. Konfigurasi Lingkungan

File `.env` dibuat container dari `docker/env.template` pada boot pertama (disimbolkan `.env → /data/.env`).

Variabel penting (nilai contoh sebagai placeholder):

| Variabel | Keterangan |
|---|---|
| `APP_ENV` | Dipaksa `production` |
| `APP_DEBUG` | Dipaksa `false` |
| `APP_KEY` | Digenerate otomatis bila kosong; persisten di `/data/.env` |
| `ADMIN_USERNAME` / `ADMIN_PASSWORD` | Kredensial admin — **wajib diisi**, tanpa default |
| `TELEGRAM_BOT_TOKEN` / `TELEGRAM_CHAT_ID` | Bot untuk notifikasi login & laporan terjadwal |
| `SCHOOL_NAME` / `SCHOOL_SUBTITLE` | Identitas tampilan |
| `DB_CONNECTION` / `DB_DATABASE` | Dipaksa `sqlite` / `/data/database.sqlite` oleh entrypoint |

> Catatan keamanan: `.env`, `.env.docker`, dan berkas `*.sqlite` di-ignore git. Jangan pernah meng-commit token/credential. Rotasi token bila pernah terekspos.

---

## 8. Deployment (Docker)

### 8.1 Build
`Dockerfile` berbasis `php:8.3-apache`:
- Ekstensi PHP: `sqlite3 pdo_sqlite dom xml mbstring intl bcmath gd zip` + `gettext-base` (envsubst).
- `composer install --no-dev --optimize-autoloader`, `storage:link`, konfigurasi Apache.

### 8.2 Entrypoint (`docker/entrypoint.sh`)  — urutan operasi
1. Kunci env DB: `DB_CONNECTION=sqlite`, `DB_DATABASE=/data/database.sqlite`, buat file SQLite bila kosong.
2. Paksa `APP_ENV=production`, `APP_DEBUG=false`.
3. Buat `/data/.env` dari `docker/env.template` (sekali), symlink `.env → /data/.env`.
4. `APP_KEY`: generate bila kosong; export ke process env bila file berisi (mengalahkan nilai kosong compose).
5. `php artisan migrate --force` (tanpa seed — produksi mulai kosong).
6. Salin `docker/siswa.txt` → `storage/app/siswa.txt` bila belum ada.
7. `chown` storage, siapkan cron scheduler, jalankan Apache.

### 8.3 Compose (`docker-compose.yml`)
- `env_file: .env.docker` (file **manual di server**: `cp docker/env.template .env.docker` lalu isi rahasia).
- Volume: `korlas-data:/data`, `korlas-storage:/var/www/html/storage`.
- Publikasi port host `<PORT>` → 80; proxy passthrough mengarah ke `127.0.0.1:<PORT>`.

**Deploy**:
```sh
git pull
cp docker/env.template .env.docker   # lalu isi ADMIN_PASSWORD, TELEGRAM_*, SCHOOL_*
docker compose up -d --build
```

---

## 9. Operasional

### 9.1 Reset data (kosongkan produksi)
```sh
docker exec -e DB_DATABASE=/data/database.sqlite korlas bash scripts/reset_db.sh --force
```
Menghapus SQLite + journal/WAL/SHM, bukti upload, lalu `migrate:fresh` (kosong) + bersih cache. Opsi: `--keep-proofs`, `--seed`, `--force`. `siswa.txt` tidak terhapus.

### 9.2 Backup data
- DB: download via menu admin *Backup → Database* (file `.sqlite`), atau `docker cp korlas:/data/database.sqlite <tujuan>`.
- Upload: menu *Backup → Uploads* menghasilkan `.zip`.
- Restore: menu admin *Restore* (validasi otomatis; backup lama disimpan).

### 9.3 Migrasi data dari sistem lama
```sh
docker exec korlas php scripts/import_backup.php <backup_lama.sqlite> /data/database.sqlite
docker exec korlas php scripts/backfill_category.php /data/database.sqlite   # perbaiki kategori hasil impor
```

### 9.4 Log & diagnosa
```sh
docker logs korlas --tail 50
docker exec korlas tail -n 40 storage/logs/laravel.log
docker exec korlas php -r '$db=new PDO("sqlite:/data/database.sqlite"); echo $db->query("select count(*) from transactions")->fetchColumn();'
```

---

## 10. Checklist Menuju Produksi

- [ ] Ganti `ADMIN_PASSWORD` (dan `ADMIN_USERNAME`) dengan kredensial kuat — tidak ada nilai default.
- [ ] Isi `TELEGRAM_BOT_TOKEN`/`TELEGRAM_CHAT_ID`; rotasi bila pernah ekspos.
- [ ] Pastikan reverse proxy gigih: HSTS, CSP, `nosniff`, `DENY`, dan upstream ke `<PORT>` yang benar.
- [ ] Data migrasi/import sudah dicek (`scripts/backfill_category.php`).
- [ ] `docker compose up -d --build` → cek `/up` = 200 dan halaman utama tanpa error.
- [ ] Jalankan `vendor/bin/phpunit` (jika ada akses dev) untuk memastikan lulus semua uji keamanan.