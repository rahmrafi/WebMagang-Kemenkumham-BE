<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->timestamp('document_downloaded_at')->nullable()->after('status');
            $table->timestamp('discussion_started_at')->nullable()->after('document_downloaded_at');
            $table->string('permit_file_path', 255)->nullable()->after('discussion_started_at');
            $table->string('permit_file_name', 255)->nullable()->after('permit_file_path');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn([
                'document_downloaded_at',
                'discussion_started_at',
                'permit_file_path',
                'permit_file_name',
            ]);
        });
    }
};
