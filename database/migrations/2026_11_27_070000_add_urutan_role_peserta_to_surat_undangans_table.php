<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('surat_undangans') && ! Schema::hasColumn('surat_undangans', 'urutan_role_peserta')) {
            Schema::table('surat_undangans', function (Blueprint $table) {
                $table->json('urutan_role_peserta')->nullable()->after('kolom_lampiran_peserta');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('surat_undangans') && Schema::hasColumn('surat_undangans', 'urutan_role_peserta')) {
            Schema::table('surat_undangans', function (Blueprint $table) {
                $table->dropColumn('urutan_role_peserta');
            });
        }
    }
};
