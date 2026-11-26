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
        if (!Schema::hasTable('surat_undangan_schedules')) {
            Schema::create('surat_undangan_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('surat_undangan_id')->constrained('surat_undangans')->cascadeOnDelete();
                $table->string('hari')->nullable(); // misal: "Selasa"
                $table->date('tanggal')->nullable();
                $table->string('waktu_mulai', 10)->nullable(); // "07.30"
                $table->string('waktu_selesai', 10)->nullable(); // "08.00"
                $table->integer('durasi_menit')->nullable(); // 30
                $table->text('materi')->nullable();
                $table->integer('jp')->nullable();
                $table->string('metode')->nullable(); // Pleno, Kelas, Asinkronus, Sinkronus
                $table->string('keterangan')->nullable();
                $table->integer('urutan')->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surat_undangan_schedules');
    }
};
