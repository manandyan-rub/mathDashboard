<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('math_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('path_id')->constrained('math_paths')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('topic')->nullable();
            $table->string('difficulty')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->unsignedInteger('passing_score')->default(70);
            $table->unsignedInteger('xp_reward')->default(50);
            $table->unsignedInteger('time_limit')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['path_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('math_levels');
    }
};
