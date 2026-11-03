<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('daily_activity_shared_work_report')) {
            Schema::create('daily_activity_shared_work_report', function (Blueprint $table) {
                $table->id();
                $table->foreignId('daily_activity_id')->constrained('daily_activities')->cascadeOnDelete();
                $table->foreignId('shared_work_report_id')->constrained('shared_work_reports')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['daily_activity_id', 'shared_work_report_id'], 'activity_shared_report_unique');
            });
        }

        // Migrasikan relasi yang sudah ada di kolom legacy shared_work_report_id ke pivot table
        if (Schema::hasColumn('daily_activities', 'shared_work_report_id')) {
            $existing = DB::table('daily_activities')
                ->whereNotNull('shared_work_report_id')
                ->select('id as daily_activity_id', 'shared_work_report_id')
                ->get();

            foreach ($existing as $row) {
                DB::table('daily_activity_shared_work_report')->updateOrInsert([
                    'daily_activity_id' => $row->daily_activity_id,
                    'shared_work_report_id' => $row->shared_work_report_id,
                ], [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe down: do nothing to prevent dropping anything per rule
    }
};
