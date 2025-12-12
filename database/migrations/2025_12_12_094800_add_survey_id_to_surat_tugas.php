<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('surat_tugas', function (Blueprint $table) {
            $table->foreignId('survey_id')->nullable()->after('user_id')->constrained()->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('surat_tugas', function (Blueprint $table) {
            $table->dropForeign(['survey_id']);
            $table->dropColumn('survey_id');
        });
    }
};
