<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kop_surats', function (Blueprint $table) {
            $table->string('template_docx_path')->nullable()->after('name');
            $table->text('instansi_teks')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('kop_surats', function (Blueprint $table) {
            $table->dropColumn('template_docx_path');
        });
    }
};
