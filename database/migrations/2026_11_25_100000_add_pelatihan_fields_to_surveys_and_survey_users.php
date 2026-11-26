<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->boolean('is_pelatihan')->default(false)->index()->after('is_multiple');
            $table->string('lokasi')->nullable()->after('is_pelatihan');
            $table->string('kota_kegiatan')->nullable()->default('Semarang')->after('lokasi');
            $table->string('nomor_surat_undangan')->nullable()->after('kota_kegiatan');
            $table->date('tanggal_surat_undangan')->nullable()->after('nomor_surat_undangan');
            $table->text('rujukan_surat')->nullable()->after('tanggal_surat_undangan');
            $table->string('link_materi')->nullable()->after('rujukan_surat');

            // DIPA & Biaya Pelatihan
            $table->string('program')->nullable()->after('link_materi');
            $table->string('kegiatan_dipa')->nullable()->after('program');
            $table->string('klasifikasi_ro')->nullable()->after('kegiatan_dipa');
            $table->string('rincian_output')->nullable()->after('klasifikasi_ro');
            $table->string('komponen')->nullable()->after('rincian_output');
            $table->string('mak')->nullable()->default('524113')->after('komponen');
            $table->unsignedBigInteger('biaya_transport')->nullable()->default(100000)->after('mak');
            $table->unsignedBigInteger('biaya_uang_harian')->nullable()->default(130000)->after('biaya_transport');

            // Penanggung Jawab & ATK
            $table->foreignId('penyelenggara_user_id')->nullable()->after('biaya_uang_harian')->constrained('users')->nullOnDelete();
            $table->foreignId('ppk_user_id')->nullable()->after('penyelenggara_user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('bendahara_user_id')->nullable()->after('ppk_user_id')->constrained('users')->nullOnDelete();
            $table->boolean('include_atk')->default(false)->after('bendahara_user_id');
            $table->json('atk_items')->nullable()->after('include_atk');
        });

        Schema::table('survey_users', function (Blueprint $table) {
            $table->string('role_pelatihan', 50)->default('PPL')->after('notes');
            $table->string('kelas', 50)->default('Kelas A')->after('role_pelatihan');
            $table->string('asal')->nullable()->after('kelas');
            $table->string('tujuan')->nullable()->after('asal');
            $table->unsignedBigInteger('transport_override')->nullable()->after('tujuan');
            $table->unsignedBigInteger('uang_harian_override')->nullable()->after('transport_override');
        });
    }

    public function down(): void
    {
        Schema::table('survey_users', function (Blueprint $table) {
            $table->dropColumn([
                'role_pelatihan',
                'kelas',
                'asal',
                'tujuan',
                'transport_override',
                'uang_harian_override',
            ]);
        });

        Schema::table('surveys', function (Blueprint $table) {
            $table->dropForeign(['penyelenggara_user_id']);
            $table->dropForeign(['ppk_user_id']);
            $table->dropForeign(['bendahara_user_id']);
            $table->dropColumn([
                'is_pelatihan',
                'lokasi',
                'kota_kegiatan',
                'nomor_surat_undangan',
                'tanggal_surat_undangan',
                'rujukan_surat',
                'link_materi',
                'program',
                'kegiatan_dipa',
                'klasifikasi_ro',
                'rincian_output',
                'komponen',
                'mak',
                'biaya_transport',
                'biaya_uang_harian',
                'penyelenggara_user_id',
                'ppk_user_id',
                'bendahara_user_id',
                'include_atk',
                'atk_items',
            ]);
        });
    }
};
