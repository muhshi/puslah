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
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete()
                ->unique(); // 1–1 dengan users

            $table->string('avatar_path')->nullable();

            $table->string('full_name');              // Wajib
            $table->string('nickname')->nullable();   // Opsional

            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();

            $table->string('gender', 1)->nullable();  // 'L' / 'P' (atau biarin kosong)

            $table->text('address')->nullable();

            $table->string('phone', 30)->nullable()->index();

            $table->string('employment_status', 16)
                ->default('aktif')                    // aktif / nonaktif
                ->index();

            $table->timestamps();

            // index tambahan kalau perlu cari cepat
            $table->index(['full_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
