
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
| dedoc/scramble | Automatic generation of API documentation for Laravel applications. |
| fakerphp/faker | Faker is a PHP library that generates fake data for you. |
| laravel/framework | The Laravel Framework |
| laravel/pail | Easily delve into your Laravel application's log files directly from the command line. |
| laravel/pint | An opinionated code formatter for PHP. |
| laravel/sail | Docker files for running a basic Laravel application. |
| laravel/sanctum | Laravel Sanctum provides a featherweight authentication system for SPAs and simple APIs. |
| laravel/tinker | Powerful REPL for the Laravel framework. |
| mockery/mockery | Mockery is a simple yet flexible PHP mock object framework. |
| nunomaduro/collision | Cli error handling for console/command-line PHP applications. |
| phpoffice/phpword | A pure PHP library for reading and writing word processing documents (OOXML, ODF, RTF, HTML, PDF) |
| phpunit/phpunit | The PHP Unit Testing framework. |

**Database**
| Nama | Deskripsi |
| :-------- | :------- |
| MySQL/MariaDB | Untuk menjalannkan proses CRUD. |
## Installation
Buka terminal lalu clone project dari repository
```bash
git clone "" Backend
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