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
        Schema::table('survey_users', function (Blueprint $table) {
            if (!Schema::hasColumn('survey_users', 'survey_class_id')) {
                $table->foreignId('survey_class_id')
                    ->nullable()
                    ->after('role')
                    ->constrained('survey_classes')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('survey_users', function (Blueprint $table) {
            if (Schema::hasColumn('survey_users', 'survey_class_id')) {
                $table->dropForeign(['survey_class_id']);
                $table->dropColumn('survey_class_id');
            }
        });
    }
};
