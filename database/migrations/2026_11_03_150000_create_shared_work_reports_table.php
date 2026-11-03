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
        Schema::create('shared_work_reports', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('activity_ids');
            $table->foreignId('surat_tugas_id')->nullable()->constrained('surat_tugas')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shared_work_reports');
    }
};
