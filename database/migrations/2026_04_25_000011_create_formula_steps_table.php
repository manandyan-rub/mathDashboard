<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('formula_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor('formula')->constrained()->cascadeOnDelete();
            $table->json('steps_json');
            $table->timestamps();
            $table->unique('formula_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('formula_steps');
    }
};

