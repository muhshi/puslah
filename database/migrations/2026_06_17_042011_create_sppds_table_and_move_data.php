<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create new table
        Schema::create('sppds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_tugas_id')->constrained('surat_tugas')->cascadeOnDelete();
            $table->string('nomor_sppd')->nullable();
            $table->integer('nomor_urut_sppd')->nullable();
            $table->string('kode_klasifikasi_sppd')->nullable()->default('KP.650');
            $table->string('tingkat_perjalanan_dinas')->nullable();
            $table->string('alat_angkutan')->nullable();
            $table->string('mak')->nullable();
            $table->string('ppk_name')->nullable();
            $table->string('ppk_nip')->nullable();
            $table->string('ppk_title')->nullable();
            $table->timestamps();
        });

        // 2. Move existing SPPD data from surat_tugas to sppds
        DB::table('surat_tugas')
            ->where('is_sppd', true)
            ->orderBy('id')
            ->chunk(100, function ($suratTugasRecords) {
                $sppds = [];
                foreach ($suratTugasRecords as $st) {
                    $sppds[] = [
                        'surat_tugas_id' => $st->id,
                        'nomor_sppd' => $st->nomor_sppd,
                        'nomor_urut_sppd' => $st->nomor_urut_sppd,
                        'kode_klasifikasi_sppd' => $st->kode_klasifikasi_sppd,
                        'tingkat_perjalanan_dinas' => $st->tingkat_perjalanan_dinas,
                        'alat_angkutan' => $st->alat_angkutan,
                        'mak' => $st->mak,
                        'ppk_name' => $st->ppk_name,
                        'ppk_nip' => $st->ppk_nip,
                        'ppk_title' => $st->ppk_title,
                        'created_at' => $st->created_at,
                        'updated_at' => $st->updated_at,
                    ];
                }
                if (!empty($sppds)) {
                    DB::table('sppds')->insert($sppds);
                }
            });

        // 3. Drop columns from surat_tugas
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Add back columns to surat_tugas
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

        // 2. Move data back
        DB::table('sppds')
            ->orderBy('id')
            ->chunk(100, function ($sppds) {
                foreach ($sppds as $sppd) {
                    DB::table('surat_tugas')
                        ->where('id', $sppd->surat_tugas_id)
                        ->update([
                            'is_sppd' => true,
                            'nomor_sppd' => $sppd->nomor_sppd,
                            'nomor_urut_sppd' => $sppd->nomor_urut_sppd,
                            'kode_klasifikasi_sppd' => $sppd->kode_klasifikasi_sppd,
                            'tingkat_perjalanan_dinas' => $sppd->tingkat_perjalanan_dinas,
                            'alat_angkutan' => $sppd->alat_angkutan,
                            'mak' => $sppd->mak,
                            'ppk_name' => $sppd->ppk_name,
                            'ppk_nip' => $sppd->ppk_nip,
                            'ppk_title' => $sppd->ppk_title,
                        ]);
                }
            });

        // 3. Drop sppds table
        Schema::dropIfExists('sppds');
    }
};
