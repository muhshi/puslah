<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('surveys') && ! Schema::hasColumn('surveys', 'penilaian_weights')) {
            Schema::table('surveys', function (Blueprint $table) {
                $table->json('penilaian_weights')->nullable()->after('atk_items');
            });
        }

        if (Schema::hasTable('survey_users')) {
            Schema::table('survey_users', function (Blueprint $table) {
                if (! Schema::hasColumn('survey_users', 'nilai_komponen')) {
                    $table->json('nilai_komponen')->nullable()->after('score');
                }
                if (! Schema::hasColumn('survey_users', 'is_lulus')) {
                    $table->boolean('is_lulus')->default(true)->after('nilai_komponen');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('survey_users')) {
            Schema::table('survey_users', function (Blueprint $table) {
                $cols = array_values(array_filter([
                    Schema::hasColumn('survey_users', 'nilai_komponen') ? 'nilai_komponen' : null,
                    Schema::hasColumn('survey_users', 'is_lulus') ? 'is_lulus' : null,
                ]));
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('surveys') && Schema::hasColumn('surveys', 'penilaian_weights')) {
            Schema::table('surveys', function (Blueprint $table) {
                $table->dropColumn('penilaian_weights');
            });
        }
    }
};
