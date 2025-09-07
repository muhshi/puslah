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
        Schema::create('survey_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('registered_at')->useCurrent();
            $table->enum('status', ['registered', 'approved'])->default('registered');
            $table->decimal('score', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['survey_id', 'user_id']); // 1 user 1 entry per survey
            $table->index(['survey_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('survey_users');
    }
};
