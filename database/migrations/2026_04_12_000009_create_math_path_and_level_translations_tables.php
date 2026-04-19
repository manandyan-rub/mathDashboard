<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('math_path_translations')) {
            Schema::create('math_path_translations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('math_path_id')->constrained('math_paths')->cascadeOnDelete();
                $table->string('locale', 10);
                $table->string('title');
                $table->text('description')->nullable();
                $table->timestamps();

                $table->unique(['math_path_id', 'locale']);
                $table->index('locale');
            });
        }

        if (!Schema::hasTable('math_level_translations')) {
            Schema::create('math_level_translations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('math_level_id')->constrained('math_levels')->cascadeOnDelete();
                $table->string('locale', 10);
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('topic')->nullable();
                $table->timestamps();

                $table->unique(['math_level_id', 'locale']);
                $table->index('locale');
            });
        }

        $now = now();

        $pathRows = DB::table('math_paths')
            ->select(['id', 'title', 'description'])
            ->get()
            ->map(fn ($path) => [
                'math_path_id' => $path->id,
                'locale' => 'hy',
                'title' => $path->title,
                'description' => $path->description,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

        if (!empty($pathRows)) {
            DB::table('math_path_translations')->upsert(
                $pathRows,
                ['math_path_id', 'locale'],
                ['title', 'description', 'updated_at']
            );
        }

        $levelRows = DB::table('math_levels')
            ->select(['id', 'title', 'description', 'topic'])
            ->get()
            ->map(fn ($level) => [
                'math_level_id' => $level->id,
                'locale' => 'hy',
                'title' => $level->title,
                'description' => $level->description,
                'topic' => $level->topic,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

        if (!empty($levelRows)) {
            DB::table('math_level_translations')->upsert(
                $levelRows,
                ['math_level_id', 'locale'],
                ['title', 'description', 'topic', 'updated_at']
            );
        }

        if (Schema::hasTable('math_level_prompts')) {
            DB::table('math_level_prompts')
                ->whereNull('locale')
                ->update(['locale' => 'hy']);

            if (!$this->hasIndex('math_level_prompts', 'math_level_prompts_level_id_locale_unique')) {
                Schema::table('math_level_prompts', function (Blueprint $table) {
                    $table->unique(['level_id', 'locale']);
                });
            }

            if ($this->hasIndex('math_level_prompts', 'math_level_prompts_level_id_unique')) {
                Schema::table('math_level_prompts', function (Blueprint $table) {
                    $table->dropUnique('math_level_prompts_level_id_unique');
                });
            }

            if (!$this->hasIndex('math_level_prompts', 'math_level_prompts_locale_index')) {
                Schema::table('math_level_prompts', function (Blueprint $table) {
                    $table->index('locale');
                });
            }
        }

        if (Schema::hasColumn('math_paths', 'title') || Schema::hasColumn('math_paths', 'description')) {
            Schema::table('math_paths', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('math_paths', 'title')) {
                    $columns[] = 'title';
                }
                if (Schema::hasColumn('math_paths', 'description')) {
                    $columns[] = 'description';
                }
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (
            Schema::hasColumn('math_levels', 'title') ||
            Schema::hasColumn('math_levels', 'description') ||
            Schema::hasColumn('math_levels', 'topic')
        ) {
            Schema::table('math_levels', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('math_levels', 'title')) {
                    $columns[] = 'title';
                }
                if (Schema::hasColumn('math_levels', 'description')) {
                    $columns[] = 'description';
                }
                if (Schema::hasColumn('math_levels', 'topic')) {
                    $columns[] = 'topic';
                }
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('math_paths', 'title')) {
            Schema::table('math_paths', function (Blueprint $table) {
                $table->string('title')->nullable()->after('id');
            });
        }

        if (!Schema::hasColumn('math_paths', 'description')) {
            Schema::table('math_paths', function (Blueprint $table) {
                $table->text('description')->nullable()->after('slug');
            });
        }

        if (!Schema::hasColumn('math_levels', 'title')) {
            Schema::table('math_levels', function (Blueprint $table) {
                $table->string('title')->nullable()->after('path_id');
            });
        }

        if (!Schema::hasColumn('math_levels', 'description')) {
            Schema::table('math_levels', function (Blueprint $table) {
                $table->text('description')->nullable()->after('slug');
            });
        }

        if (!Schema::hasColumn('math_levels', 'topic')) {
            Schema::table('math_levels', function (Blueprint $table) {
                $table->string('topic')->nullable()->after('description');
            });
        }

        $pathTranslations = DB::table('math_path_translations')
            ->orderByRaw("case when locale = 'hy' then 0 when locale = 'en' then 1 else 2 end")
            ->get()
            ->groupBy('math_path_id');

        foreach ($pathTranslations as $pathId => $translations) {
            $translation = $translations->first();
            DB::table('math_paths')
                ->where('id', $pathId)
                ->update([
                    'title' => $translation->title,
                    'description' => $translation->description,
                ]);
        }

        $levelTranslations = DB::table('math_level_translations')
            ->orderByRaw("case when locale = 'hy' then 0 when locale = 'en' then 1 else 2 end")
            ->get()
            ->groupBy('math_level_id');

        foreach ($levelTranslations as $levelId => $translations) {
            $translation = $translations->first();
            DB::table('math_levels')
                ->where('id', $levelId)
                ->update([
                    'title' => $translation->title,
                    'description' => $translation->description,
                    'topic' => $translation->topic,
                ]);
        }

        if (Schema::hasTable('math_level_prompts')) {
            DB::table('math_level_prompts')
                ->where('locale', '!=', 'hy')
                ->delete();

            if (!$this->hasIndex('math_level_prompts', 'math_level_prompts_level_id_unique')) {
                Schema::table('math_level_prompts', function (Blueprint $table) {
                    $table->unique('level_id');
                });
            }

            if ($this->hasIndex('math_level_prompts', 'math_level_prompts_level_id_locale_unique')) {
                Schema::table('math_level_prompts', function (Blueprint $table) {
                    $table->dropUnique('math_level_prompts_level_id_locale_unique');
                });
            }

            if ($this->hasIndex('math_level_prompts', 'math_level_prompts_locale_index')) {
                Schema::table('math_level_prompts', function (Blueprint $table) {
                    $table->dropIndex('math_level_prompts_locale_index');
                });
            }
        }

        if (Schema::hasTable('math_level_translations')) {
            Schema::drop('math_level_translations');
        }

        if (Schema::hasTable('math_path_translations')) {
            Schema::drop('math_path_translations');
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return !empty(DB::select(
                "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
                [$indexName]
            ));
        }

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }
};
