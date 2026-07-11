<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submission_messages', function (Blueprint $table) {
            $table->timestamp('admin_read_at')->nullable()->after('message');
            $table->index(['submission_id', 'sender_type', 'admin_read_at']);
        });
    }

    public function down(): void
    {
        Schema::table('submission_messages', function (Blueprint $table) {
            $table->dropIndex(['submission_id', 'sender_type', 'admin_read_at']);
            $table->dropColumn('admin_read_at');
        });
    }
};
