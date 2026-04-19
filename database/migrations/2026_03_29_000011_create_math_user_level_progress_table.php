<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('math_user_level_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->constrained('math_levels')->cascadeOnDelete();
            $table->boolean('unlocked')->default(false);
            $table->boolean('completed')->default(false);
            $table->unsignedInteger('best_score')->default(0);
            $table->unsignedInteger('best_stars')->default(0);
            $table->unsignedInteger('total_attempts')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'level_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('math_user_level_progress');
    }
};
