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
        Schema::table('schedules', function (Blueprint $table) {
            if (Schema::hasColumn('schedules', 'is_wfa'))
                $table->dropColumn('is_wfa');
            if (Schema::hasColumn('schedules', 'is_banned'))
                $table->dropColumn('is_banned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->boolean('is_wfa')->default(false);
            $table->boolean('is_banned')->default(false);
        });
    }
};
