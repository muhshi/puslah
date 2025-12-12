<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('laporan_perjalanan_dinas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_tugas_id')->constrained('surat_tugas')->onDelete('cascade');
            $table->string('nomor_surat_tugas');
            $table->string('tujuan');
            $table->date('tanggal_kunjungan');
            $table->text('uraian_kegiatan');
            $table->string('nama_pejabat')->nullable();
            $table->string('desa_pejabat')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_perjalanan_dinas');
    }
};
