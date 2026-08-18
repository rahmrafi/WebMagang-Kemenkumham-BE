<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->text('certificate_number_suffixes')->nullable()->after('certificate_generated_at');
        });

        DB::table('settings')->insertOrIgnore([
            [
                'key'        => 'certificate_prefix',
                'value'      => 'W.15-UM.01.01-',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key'        => 'certificate_pejabat',
                'value'      => 'R. Prasetyo Wibowo, S.H., M.H.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key'        => 'certificate_text_magang',
                'value'      => 'Telah menyelesaikan magang/praktek kerja lapangan di Kantor Wilayah Kementerian Hukum Jawa Timur mulai {periode}',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key'        => 'certificate_text_penelitian',
                'value'      => 'Telah melaksanakan penelitian di Kantor Wilayah Kementerian Hukum Jawa Timur mulai {periode}',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn('certificate_number_suffixes');
        });

        DB::table('settings')->whereIn('key', [
            'certificate_prefix',
            'certificate_pejabat',
            'certificate_text_magang',
            'certificate_text_penelitian',
        ])->delete();
    }
};
