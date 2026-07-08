<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            for ($i = 4; $i <= 10; $i++) {
                $table->string('member_' . $i, 100)->nullable()->after('member_' . ($i - 1));
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            for ($i = 4; $i <= 10; $i++) {
                $table->dropColumn('member_' . $i);
            }
        });
    }
};
