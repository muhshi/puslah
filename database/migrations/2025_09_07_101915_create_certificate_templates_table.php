<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('blade_view')->nullable();     // atau simpan nama view
            $table->text('html')->nullable();           // alternatif: html mentah
            $table->string('background_path')->nullable();
            $table->string('signer_name')->nullable();
            $table->string('signer_title')->nullable();
            $table->string('signer_image_path')->nullable();
            $table->string('number_format')->default('BPS-DMK/{YYYY}/{MM}/{SEQ6}');
            $table->json('qr_config')->nullable(); // {position:{x,y}, size}
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_templates');
    }
};
