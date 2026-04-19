<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('math_level_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')->constrained('math_levels')->cascadeOnDelete();
            $table->text('question');
            $table->string('type')->default('multiple_choice');
            $table->text('explanation')->nullable();
            $table->string('correct_answer')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->unsignedInteger('points')->default(1);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('math_level_questions');
    }
};
