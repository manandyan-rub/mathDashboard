<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('status')->default('uploaded');
            $table->longText('extracted_text')->nullable();
            $table->timestamps();
        });

        Schema::create('pdf_quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdf_import_id')->constrained('pdf_imports')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('difficulty')->nullable();
            $table->unsignedInteger('total_questions')->default(0);
            $table->timestamps();
        });

        Schema::create('pdf_quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('pdf_quizzes')->cascadeOnDelete();
            $table->text('question');
            $table->text('explanation')->nullable();
            $table->string('correct_answer')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('pdf_quiz_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('pdf_quiz_questions')->cascadeOnDelete();
            $table->string('option_text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('pdf_quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('pdf_quizzes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('total_questions')->default(0);
            $table->unsignedInteger('correct_answers')->default(0);
            $table->unsignedInteger('wrong_answers')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pdf_quiz_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('pdf_quiz_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('pdf_quiz_questions')->cascadeOnDelete();
            $table->foreignId('selected_option_id')->nullable()->constrained('pdf_quiz_question_options')->nullOnDelete();
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_quiz_attempt_answers');
        Schema::dropIfExists('pdf_quiz_attempts');
        Schema::dropIfExists('pdf_quiz_question_options');
        Schema::dropIfExists('pdf_quiz_questions');
        Schema::dropIfExists('pdf_quizzes');
        Schema::dropIfExists('pdf_imports');
    }
};
