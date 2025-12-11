<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('surat_tugas', function (Blueprint $table) {
            $table->integer('nomor_urut')->nullable()->after('nomor_surat');
            $table->string('kode_klasifikasi')->nullable()->after('nomor_urut');
        });
    }

    public function down(): void
    {
        Schema::table('surat_tugas', function (Blueprint $table) {
            $table->dropColumn(['nomor_urut', 'kode_klasifikasi']);
        });
    }
};
