<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('surat_tugas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('nomor_surat')->unique();
            $table->string('jabatan'); // Snapshot jabatan petugas saat itu
            $table->text('keperluan');
            $table->date('tanggal');   // Tanggal surat
            $table->dateTime('waktu_mulai')->nullable();
            $table->dateTime('waktu_selesai')->nullable();

            // Snapshot Pejabat Penandatangan (Kepala)
            $table->string('signer_name');
            $table->string('signer_nip');
            $table->string('signer_title');
            $table->string('signer_city');
            $table->string('signer_signature_path')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_tugas');
    }
};
