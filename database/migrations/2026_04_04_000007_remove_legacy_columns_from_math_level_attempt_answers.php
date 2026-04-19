<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('math_level_attempt_answers', function (Blueprint $table) {
            if (Schema::hasColumn('math_level_attempt_answers', 'question_id')) {
                $table->dropForeign(['question_id']);
                $table->dropColumn('question_id');
            }

            if (Schema::hasColumn('math_level_attempt_answers', 'selected_option_id')) {
                $table->dropForeign(['selected_option_id']);
                $table->dropColumn('selected_option_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('math_level_attempt_answers', function (Blueprint $table) {
            if (!Schema::hasColumn('math_level_attempt_answers', 'question_id')) {
                $table->foreignId('question_id')->nullable()->constrained('math_level_questions')->nullOnDelete();
            }

            if (!Schema::hasColumn('math_level_attempt_answers', 'selected_option_id')) {
                $table->foreignId('selected_option_id')->nullable()->constrained('math_level_question_options')->nullOnDelete();
            }
        });
    }
};
