<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_daily_recaps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('work_date');
            $table->boolean('is_workday')->default(true);
            $table->unsignedSmallInteger('present')->default(0);
            $table->unsignedSmallInteger('late')->default(0);
            $table->unsignedSmallInteger('under_7h')->default(0);
            $table->unsignedSmallInteger('no_checkout')->default(0);
            $table->unsignedSmallInteger('leave')->default(0);
            $table->unsignedSmallInteger('alpa')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
            $table->index(['user_id', 'work_date'], 'idx_recap_user_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_daily_recaps');
    }
};
