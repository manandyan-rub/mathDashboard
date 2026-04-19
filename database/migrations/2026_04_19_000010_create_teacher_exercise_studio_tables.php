<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('status', 20)->default('draft');
            $table->longText('content_json')->nullable();
            $table->longText('content_html')->nullable();
            $table->string('language', 10)->default('en');
            $table->json('meta')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('exercise_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('exercise_documents')->cascadeOnDelete();
            $table->unsignedInteger('item_order')->default(0);
            $table->string('type', 30)->default('mcq');
            $table->longText('question');
            $table->json('options')->nullable();
            $table->text('answer')->nullable();
            $table->text('explanation')->nullable();
            $table->string('difficulty', 30)->nullable();
            $table->unsignedInteger('points')->default(1);
            $table->string('source', 20)->default('ai');
            $table->timestamps();

            $table->index(['document_id', 'item_order']);
        });

        Schema::create('exercise_generation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('exercise_documents')->nullOnDelete();
            $table->string('status', 20)->default('processing');
            $table->text('prompt');
            $table->longText('request_json')->nullable();
            $table->longText('response_raw')->nullable();
            $table->longText('parsed_json')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['document_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_generation_logs');
        Schema::dropIfExists('exercise_items');
        Schema::dropIfExists('exercise_documents');
    }
};

