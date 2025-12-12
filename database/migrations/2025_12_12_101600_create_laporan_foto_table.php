<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('laporan_foto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_perjalanan_dinas_id')->constrained('laporan_perjalanan_dinas')->onDelete('cascade');
            $table->string('file_path');
            $table->text('keterangan')->nullable();
            $table->integer('urutan')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_foto');
    }
};
