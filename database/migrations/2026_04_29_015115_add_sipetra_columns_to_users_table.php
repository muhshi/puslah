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
            $table->string('sipetra_id')->after('id')->nullable()->unique();
            $table->text('sipetra_token')->after('password')->nullable();
            $table->text('sipetra_refresh_token')->after('sipetra_token')->nullable();
            $table->string('nip')->after('email')->nullable();
            $table->string('jabatan')->after('nip')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['sipetra_id', 'sipetra_token', 'sipetra_refresh_token', 'nip', 'jabatan']);
        });
    }
};
