<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formula_steps', function (Blueprint $table) {
            $table->index('formula_id', 'formula_steps_formula_id_index');
        });

        Schema::table('formula_steps', function (Blueprint $table) {
            $table->dropUnique(['formula_id']);
        });

        Schema::table('formula_steps', function (Blueprint $table) {
            $table->string('locale', 5)->default('en')->after('formula_id');
            $table->unique(['formula_id', 'locale']);
            $table->index('locale');
        });

        // Existing rows were a locale-less generated cache, so they can contain
        // whichever language was opened first. Regenerate per locale on demand.
        DB::table('formula_steps')->delete();
    }

    public function down(): void
    {
        DB::table('formula_steps')->where('locale', '!=', 'en')->delete();

        Schema::table('formula_steps', function (Blueprint $table) {
            $table->dropUnique(['formula_id', 'locale']);
            $table->dropIndex(['locale']);
            $table->dropColumn('locale');
        });

        Schema::table('formula_steps', function (Blueprint $table) {
            $table->unique('formula_id');
            $table->dropIndex('formula_steps_formula_id_index');
        });
    }
};
