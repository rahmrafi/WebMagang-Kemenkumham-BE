
# Website Magang Kemenkumham (Backend)
Project Team-Based yang dikerjakan selama waktu magang di Kementrian Hukum wilayah Jawa Timur. Ini merupakan bagian backend untuk mengatur permintaaan (*request*) dari frontend, menjalankan logika bisnis (*business logic*), dan komunikasi antara database (*mysql*) untuk melakukan proses CRUD (**hanya dilakukan oleh admin**).

## Features
- Authentication
- Public submission flow
- From validation
- Quota & period management
- Admin management
- File storage & security
- Bot protection & rate limiting
- Model & schema migration

## Tech Stack
**Laravel Framework**
| Nama | Deskripsi |
| :-------- | :------- |
| dedoc/scramble | Pembuatan dokumentasi API secara otomatis untuk aplikasi Laravel. |
| fakerphp/faker | Faker adalah pustaka PHP yang menghasilkan data palsu. |
| laravel/framework | Kerangka Kerja Laravel |
| laravel/pail | Telusuri berkas log aplikasi Laravel Anda dengan mudah langsung dari baris perintah. |
| laravel/pint | Pembentuk kode yang memiliki pendekatan tegas untuk PHP. |
| laravel/sail | Berkas Docker untuk menjalankan aplikasi Laravel dasar. |
| laravel/sanctum | Laravel Sanctum menyediakan sistem otentikasi yang sangat ringan untuk SPA dan API sederhana. |
| laravel/tinker | REPL yang kuat untuk kerangka kerja Laravel. |
| mockery/mockery | Mockery adalah kerangka kerja objek tiruan PHP yang sederhana namun fleksibel. |
| nunomaduro/collision | Penanganan kesalahan CLI untuk aplikasi PHP konsol/baris perintah. |
| phpoffice/phpword | Pustaka PHP murni untuk membaca dan menulis dokumen pengolah kata (OOXML, ODF, RTF, HTML, PDF) |
| phpunit/phpunit | Kerangka kerja Pengujian Unit PHP. |

**Database**
| Nama | Deskripsi |
| :-------- | :------- |
| MySQL/MariaDB | Untuk menjalannkan proses CRUD. |

## Installation
Buka terminal lalu clone project dari repository
```bash
git clone https://github.com/rahmrafi/WebMagang-Kemenkumham-BE.git Backend
```
Masuk ke direktori backend
```bash
cd Backend
```
Install dependency dengan composer
```bash
composer install
```
Migrasi database beserta seedernya
```bash
php artisan migrate --seed
```
Jalankan server php
```bash
php artisan serve
```

## Get frontend part
Antarmuka bisa mengunakan repository berikut ini [frontend-kemenkum.](https://github.com/amad-IO/frontend-kemenkum)