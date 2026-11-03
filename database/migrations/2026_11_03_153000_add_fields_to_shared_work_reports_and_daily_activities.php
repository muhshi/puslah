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
        Schema::table('shared_work_reports', function (Blueprint $table) {
            $table->string('tag_slug', 64)->nullable()->after('title')->index();
            $table->date('period_start')->nullable()->after('description');
            $table->date('period_end')->nullable()->after('period_start');
        });

        Schema::table('daily_activities', function (Blueprint $table) {
            $table->foreignId('shared_work_report_id')
                ->nullable()
                ->after('surat_tugas_id')
                ->constrained('shared_work_reports')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_activities', function (Blueprint $table) {
            $table->dropForeign(['shared_work_report_id']);
            $table->dropColumn('shared_work_report_id');
        });

        Schema::table('shared_work_reports', function (Blueprint $table) {
            $table->dropColumn(['tag_slug', 'period_start', 'period_end']);
        });
    }
};
