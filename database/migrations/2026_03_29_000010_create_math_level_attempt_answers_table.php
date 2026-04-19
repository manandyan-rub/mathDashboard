<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('math_level_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('math_level_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('math_level_questions')->cascadeOnDelete();
            $table->foreignId('selected_option_id')->nullable()->constrained('math_level_question_options')->nullOnDelete();
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('math_level_attempt_answers');
    }
};
