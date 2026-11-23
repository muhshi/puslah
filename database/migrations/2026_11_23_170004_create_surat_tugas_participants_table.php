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
        if (!Schema::hasTable('surat_tugas_participants')) {
            Schema::create('surat_tugas_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('surat_tugas_id')->constrained('surat_tugas')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('survey_user_id')->nullable()->constrained('survey_users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['surat_tugas_id', 'user_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surat_tugas_participants');
    }
};
