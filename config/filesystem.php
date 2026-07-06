<?php

/*
|--------------------------------------------------------------------------
| Tambahkan blok disk berikut ke dalam array "disks" pada file
| config/filesystems.php bawaan project Laravel Anda.
|--------------------------------------------------------------------------
|
| Disk ini menyimpan file ZIP permohonan di luar direktori "public",
| sehingga tidak bisa diakses langsung lewat URL publik dan tidak bisa
| dieksekusi sebagai script. Akses file HANYA lewat endpoint
| GET /api/admin/submissions/{id}/download yang dilindungi auth.
|
*/

'submissions' => [
    'driver' => 'local',
    'root' => storage_path('app/submissions'),
    'visibility' => 'private',
    'throw' => false,
],

/*
|--------------------------------------------------------------------------
| Catatan keamanan tambahan (lakukan manual di server):
|--------------------------------------------------------------------------
| 1. Pastikan storage/app/submissions TIDAK berada di dalam public/.
| 2. Set permission folder read/write hanya untuk user web server (mis. 750).
| 3. Nonaktifkan eksekusi PHP di folder ini lewat konfigurasi web server:
|
|    # Nginx
|    location ^~ /storage/submissions/ {
|        deny all;
|    }
|
|    # Apache (.htaccess di dalam folder submissions)
|    <FilesMatch "\.(php|phtml|php3|php4|php5|phar)$">
|        Require all denied
|    </FilesMatch>
*/
