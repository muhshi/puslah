<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('surat_tugas', function (Blueprint $table) {
            $table->text('dasar_surat')->nullable()->after('keperluan');
            $table->string('tempat_tugas')->nullable()->after('dasar_surat');
        });
    }

    public function down(): void
    {
        Schema::table('surat_tugas', function (Blueprint $table) {
            $table->dropColumn(['dasar_surat', 'tempat_tugas']);
        });
    }
};
