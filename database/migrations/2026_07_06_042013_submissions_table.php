<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['magang', 'penelitian']);
            $table->foreignId('period_id')
                ->nullable()
                ->constrained('internship_periods')
                ->nullOnDelete();
            $table->string('institution', 150);
            $table->string('study_program', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('member_1', 100);
            $table->string('member_2', 100)->nullable();
            $table->string('member_3', 100)->nullable();
            $table->string('letter_number', 100);
            $table->string('document_path', 255);
            $table->string('phone_number', 20);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
