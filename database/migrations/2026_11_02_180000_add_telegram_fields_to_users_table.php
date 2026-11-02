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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'telegram_chat_id')) {
                $table->string('telegram_chat_id', 64)->nullable()->unique()->after('remember_token');
            }
            if (!Schema::hasColumn('users', 'telegram_auth_token')) {
                $table->string('telegram_auth_token', 64)->nullable()->index()->after('telegram_chat_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'telegram_auth_token')) {
                $table->dropColumn('telegram_auth_token');
            }
            if (Schema::hasColumn('users', 'telegram_chat_id')) {
                $table->dropColumn('telegram_chat_id');
            }
        });
    }
};
