<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('math_user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('total_xp')->default(0);
            $table->string('current_rank')->default('Beginner');
            $table->unsignedInteger('streak_days')->default(0);
            $table->unsignedInteger('completed_levels')->default(0);
            $table->unsignedInteger('total_stars')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('math_user_profiles');
    }
};
