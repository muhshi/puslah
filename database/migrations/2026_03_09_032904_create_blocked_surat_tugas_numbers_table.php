<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('blocked_surat_tugas_numbers', function (Blueprint $table) {
            $table->id();
            $table->integer('nomor_urut');
            $table->integer('year');
            $table->text('keterangan')->nullable();
            $table->foreignId('blocked_by')->constrained('users');
            $table->timestamps();

            $table->unique(['nomor_urut', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocked_surat_tugas_numbers');
    }
};
