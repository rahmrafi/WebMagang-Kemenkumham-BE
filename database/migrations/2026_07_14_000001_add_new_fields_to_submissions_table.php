<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            // Jenjang pendidikan (SMA, SMK, D3, D4, S1)
            // Digunakan untuk menentukan label NIM/NISN di surat
            $table->string('education_level', 10)->nullable()->after('study_program');

            // Kota/kabupaten asal kampus/sekolah
            // Digunakan untuk mengisi placeholder [5] di template surat
            $table->string('campus_city', 100)->nullable()->after('institution');

            // Tanggal surat permohonan dari kampus/sekolah
            // Digunakan untuk mengisi placeholder [7] di template surat
            $table->date('letter_date')->nullable()->after('letter_number');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['education_level', 'campus_city', 'letter_date']);
        });
    }
};
