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
        Schema::table('daily_activity_attachments', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_activity_attachments', 'stamp_position')) {
                $table->string('stamp_position', 32)->default('bottom-left')->after('is_stamped');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_activity_attachments', function (Blueprint $table) {
            if (Schema::hasColumn('daily_activity_attachments', 'stamp_position')) {
                $table->dropColumn('stamp_position');
            }
        });
    }
};
