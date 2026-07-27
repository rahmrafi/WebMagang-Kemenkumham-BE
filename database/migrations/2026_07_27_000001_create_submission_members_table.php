<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')
                ->constrained('submissions')
                ->cascadeOnDelete();
            $table->string('nama', 150);
            $table->string('nim', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('is_leader')->default(false);
            $table->unsignedTinyInteger('urutan')->default(1); // posisi member (1-10)
            $table->timestamps();

            $table->index(['submission_id']);
            $table->index(['nim']);
            $table->index(['email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_members');
    }
};
