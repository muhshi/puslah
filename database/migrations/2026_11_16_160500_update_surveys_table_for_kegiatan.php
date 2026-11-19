<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            if (!Schema::hasColumn('surveys', 'tujuan')) {
                if (Schema::hasColumn('surveys', 'description')) {
                    $table->renameColumn('description', 'tujuan');
                } else {
                    $table->text('tujuan')->nullable()->after('name');
                }
            }
            if (!Schema::hasColumn('surveys', 'jenis_kegiatan')) {
                $table->string('jenis_kegiatan')->default('pelatihan')->after('name');
            }
            if (!Schema::hasColumn('surveys', 'metode')) {
                $table->string('metode')->default('luring')->after('jenis_kegiatan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            if (Schema::hasColumn('surveys', 'metode')) {
                $table->dropColumn('metode');
            }
            if (Schema::hasColumn('surveys', 'jenis_kegiatan')) {
                $table->dropColumn('jenis_kegiatan');
            }
            if (Schema::hasColumn('surveys', 'tujuan')) {
                $table->renameColumn('tujuan', 'description');
            }
        });
    }
};
