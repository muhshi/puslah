<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('survey_users', function (Blueprint $table) {
            if (!Schema::hasColumn('survey_users', 'role')) {
                $table->string('role')->default('Peserta')->after('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('survey_users', function (Blueprint $table) {
            if (Schema::hasColumn('survey_users', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
