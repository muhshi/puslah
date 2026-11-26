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
        if (!Schema::hasTable('kop_surats')) {
            Schema::create('kop_surats', function (Blueprint $table) {
                $table->id();
                $table->string('name'); // misal: "Kop BerAKHLAK (Default)", "Kop Sensus Ekonomi 2026"
                $table->string('logo_kiri_path')->default('images/kop/logo_bps_header.png');
                $table->string('logo_kanan_path')->nullable(); // misal: 'images/kop/logo_berakhlak.png' atau 'images/kop/logo_se26.png'
                $table->text('instansi_teks')->nullable(); // Alamat / Kontak kantor
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kop_surats');
    }
};
