<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercise_generation_logs', function (Blueprint $table) {
            $table->string('source', 30)->default('runtime')->after('document_id');
            $table->index(['source', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('exercise_generation_logs', function (Blueprint $table) {
            $table->dropIndex(['source', 'status']);
            $table->dropColumn('source');
        });
    }
};
