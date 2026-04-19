<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('math_level_prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')->constrained('math_levels')->cascadeOnDelete();
            $table->text('prompt');
            $table->unsignedInteger('question_count')->default(10);
            $table->unsignedInteger('options_count')->default(4);
            $table->string('locale', 10)->nullable();
            $table->timestamps();

            $table->unique('level_id');
        });

        Schema::create('math_level_attempt_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('math_level_attempts')->cascadeOnDelete();
            $table->text('question');
            $table->text('explanation')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->unsignedInteger('points')->default(1);
            $table->timestamps();
        });

        Schema::create('math_level_attempt_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('math_level_attempt_questions')->cascadeOnDelete();
            $table->string('option_text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::table('math_level_attempt_answers', function (Blueprint $table) {
            $table->foreignId('attempt_question_id')
                ->nullable()
                ->constrained('math_level_attempt_questions')
                ->cascadeOnDelete();
            $table->foreignId('attempt_option_id')
                ->nullable()
                ->constrained('math_level_attempt_question_options')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('math_level_attempt_answers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attempt_option_id');
            $table->dropConstrainedForeignId('attempt_question_id');
        });

        Schema::dropIfExists('math_level_attempt_question_options');
        Schema::dropIfExists('math_level_attempt_questions');
        Schema::dropIfExists('math_level_prompts');
    }
};
