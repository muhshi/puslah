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
        Schema::create('daily_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('surat_tugas_id')->nullable()->constrained('surat_tugas')->nullOnDelete();
            $table->date('activity_date')->index();
            $table->time('started_at')->nullable();
            $table->time('ended_at')->nullable();
            $table->text('raw_content');
            $table->text('ai_formal_content')->nullable();
            $table->text('final_content');
            $table->string('status', 32)->default('submitted')->index();
            $table->string('source', 32)->default('web');
            $table->foreignId('laporan_perjalanan_dinas_id')->nullable()->constrained('laporan_perjalanan_dinas')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'activity_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_activities');
    }
};
