<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Insert default data
        DB::table('settings')->insert([
            ['key' => 'pejabat_name', 'value' => 'R. Prasetyo Wibowo', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'pejabat_position', 'value' => 'Kepala Bagian Tata Usaha dan Umum', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
