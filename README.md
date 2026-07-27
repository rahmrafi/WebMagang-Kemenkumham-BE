# Backend — Laravel API

Backend untuk website pendaftaran magang & penelitian Kemenkuham. Dibangun dengan Laravel 12, menggunakan MariaDB sebagai database dan Laravel Reverb untuk fitur real-time WebSocket.

---

##  Requirements

| Dependency | Versi |
|---|---|
| PHP | 8.2+ |
| Composer | 2.x |
| MariaDB | 10.6+ (atau MySQL 8.0+) |
| PHP Extension | `zip`, `pdo_mysql`, `mbstring`, `fileinfo`, `gd` |

---

## Instalasi

### 1. Install Dependencies PHP

```bash
composer install
```

### 2. Setup Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit file `.env` dan isi konfigurasi berikut:

```env
# Koneksi Database
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1       # atau IP server remote
DB_PORT=3306
DB_DATABASE=magang_kemenkumham_db
DB_USERNAME=root
DB_PASSWORD=your_password

# URL Frontend (untuk CORS & Sanctum)
FRONTEND_URL=http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost:5173

# Cloudflare Turnstile (CAPTCHA publik)
TURNSTILE_SECRET_KEY=your_turnstile_secret

# Laravel Reverb (WebSocket)
REVERB_APP_ID=your_app_id
REVERB_APP_KEY=your_app_key
REVERB_APP_SECRET=your_app_secret
REVERB_HOST=localhost
REVERB_PORT=6001

# Cache (gunakan file untuk dev, redis untuk production)
CACHE_STORE=file

# Log level (gunakan 'warning' untuk dev, 'error' untuk production)
LOG_LEVEL=warning
```

### 3. Jalankan Migration

```bash
php artisan migrate
```

Untuk data awal (admin user default):

```bash
php artisan db:seed
```

Untuk migrasi data member lama ke tabel `submission_members` (jika ada data lama):

```bash
php artisan db:seed --class=MigrateSubmissionMembersSeeder
```

### 4. Storage Link

Buat symlink dari `public/storage` ke `storage/app/public` (diperlukan untuk akses file via HTTP):

```bash
php artisan storage:link
```

---

##  Menjalankan Server

### Web Server (API)

```bash
php artisan serve
```

API akan berjalan di: `http://localhost:8000`

### WebSocket Server (Real-time Chat)

```bash
php artisan reverb:start --port=6001
```

> **Reverb harus dijalankan bersamaan dengan web server** agar fitur diskusi real-time antara admin dan pendaftar berfungsi.

### Keduanya Sekaligus (via Composer script)

```bash
composer run serve-all
```

---

## Struktur Folder Penting

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/         ← Controller publik (submit, cek status)
│   │   ├── Controllers/Api/Admin/   ← Controller admin
│   │   ├── Middleware/              ← Auth, device check, Turnstile
│   │   └── Requests/                ← Form validation (StoreSubmissionRequest)
│   ├── Models/                      ← Eloquent models
│   └── Services/                    ← Business logic (CertificateService, DocumentService)
├── database/
│   ├── migrations/                  ← Skema database
│   └── seeders/                     ← Data awal & seeder migrasi
├── routes/
│   └── api.php                      ← Semua endpoint API (~22 route)
├── storage/
│   ├── app/submissions/             ← File dokumen ZIP yang diupload pendaftar
│   └── app/temp/                    ← File temporary (dihapus otomatis)
└── .env                             ← Konfigurasi environment (JANGAN di-commit ke git!)
```

---

## Endpoint API Utama

| Method | Endpoint | Keterangan |
|---|---|---|
| `POST` | `/api/submit` | Submit pendaftaran baru |
| `GET` | `/api/check-status` | Cek status pendaftaran (email + NIM) |
| `GET` | `/api/periods` | Daftar periode aktif |
| `POST` | `/api/admin/login` | Login admin |
| `GET` | `/api/admin/submissions` | List semua pendaftar (admin) |
| `PATCH` | `/api/admin/submissions/{id}/approve` | Setujui pendaftaran |
| `PATCH` | `/api/admin/submissions/{id}/reject` | Tolak pendaftaran |
| `GET` | `/api/admin/submissions/{id}/generate-template` | Download surat izin (.docx) |
| `POST` | `/api/admin/certificate/generate/{id}` | Generate sertifikat PDF → ZIP |

Dokumentasi API lengkap tersedia di: `http://localhost:8000/docs/api` (via Scramble, dev only)

---

##  Database Schema Utama

| Tabel | Keterangan |
|---|---|
| `submissions` | Data utama pendaftaran (type, status, institusi, dll) |
| `submission_members` | Data anggota tim (nama, NIM, email, is_leader) |
| `submission_messages` | Pesan diskusi antara pendaftar dan admin |
| `internship_periods` | Periode penerimaan magang yang aktif |
| `settings` | Konfigurasi aplikasi (sertifikat, nama pejabat, dll) |
| `users` | Akun admin |

---

## Keamanan

- File `.env` sudah masuk `.gitignore` — **jangan pernah commit ke Git**
- Password di-hash dengan bcrypt (12 rounds)
- Rate limiting: 5 req/menit untuk submit, 10 req/menit untuk login admin
- CAPTCHA Cloudflare Turnstile pada form pendaftaran publik

---

## Perintah Artisan Berguna

```bash
# Cek semua route yang terdaftar
php artisan route:list

# Clear semua cache
php artisan cache:clear && php artisan config:clear && php artisan route:clear

# Lihat status migration
php artisan migrate:status

# Buat user admin baru
php artisan tinker
>>> App\Models\User::create(['name'=>'Admin','email'=>'admin@example.com','password'=>bcrypt('password'),'is_admin'=>true]);
```
