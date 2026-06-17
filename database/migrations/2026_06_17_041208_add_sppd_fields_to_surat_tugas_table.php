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
        Schema::table('surat_tugas', function (Blueprint $table) {
            $table->boolean('is_sppd')->default(false);
            $table->string('nomor_sppd')->nullable();
            $table->integer('nomor_urut_sppd')->nullable();
            $table->string('kode_klasifikasi_sppd')->nullable()->default('KP.650');
            $table->string('tingkat_perjalanan_dinas')->nullable();
            $table->string('alat_angkutan')->nullable();
            $table->string('mak')->nullable();
            $table->string('ppk_name')->nullable();
            $table->string('ppk_nip')->nullable();
            $table->string('ppk_title')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surat_tugas', function (Blueprint $table) {
            $table->dropColumn([
                'is_sppd',
                'nomor_sppd',
                'nomor_urut_sppd',
                'kode_klasifikasi_sppd',
                'tingkat_perjalanan_dinas',
                'alat_angkutan',
                'mak',
                'ppk_name',
                'ppk_nip',
                'ppk_title',
            ]);
        });
    }
};
