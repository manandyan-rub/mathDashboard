<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { cmsTranslate as __, useLanguageVersion } from "@/lib/i18n";
import axios from "axios";
import { computed, onMounted, ref, watch } from "vue";
import { route } from "ziggy-js";
import { Lock, Sparkles, Star } from "lucide-vue-next";

type LevelItem = {
  id: number;
  title: string;
  topic?: string | null;
  difficulty?: string | null;
  order: number;
  passing_score: number;
  xp_reward: number;
  unlocked: boolean;
  completed: boolean;
  best_score: number;
  best_stars: number;
};

type PathItem = {
  id: number;
  title: string;
  description?: string | null;
  levels: LevelItem[];
};

const profile = ref({
  total_xp: 0,
  current_rank: "Beginner",
  completed_levels: 0,
  total_stars: 0,
});
const paths = ref<PathItem[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const starting = ref<number | null>(null);
const difficultyFilter = ref<"all" | "beginner" | "medium" | "hard">("all");
const languageVersion = useLanguageVersion();

const difficultyBucket = (value: string | null | undefined) => {
  const normalized = (value ?? "").toLowerCase();
  if (normalized.includes("beginner") || normalized.includes("easy")) return "beginner";
  if (normalized.includes("hard")) return "hard";
  return "medium";
};

const progressPercent = (level: LevelItem) => {
  const best = Number(level.best_score || 0);
  if (best < 0) return 0;
  if (best > 100) return 100;
  return best;
};

const filteredPaths = computed(() => {
  if (difficultyFilter.value === "all") return paths.value;
  return paths.value
    .map((path) => ({
      ...path,
      levels: path.levels.filter(
        (level) => difficultyBucket(level.difficulty) === difficultyFilter.value,
      ),
    }))
    .filter((path) => path.levels.length > 0);
});

async function loadLevels() {
  loading.value = true;
  error.value = null;
  try {
    const response = await axios.get("/api/math-levels");
    profile.value = response.data?.profile ?? profile.value;
    paths.value = response.data?.paths ?? [];
  } catch (err: any) {
    error.value =
      err?.response?.data?.message ||
      err?.message ||
      __("Failed to load levels.");
  } finally {
    loading.value = false;
  }
}

async function startLevel(levelId: number) {
  starting.value = levelId;
  error.value = null;
  try {
    const response = await axios.post("/api/math-levels/start", {
      level_id: levelId,
    });
    if (response.data?.busy) {
      error.value = response.data?.message || __("Generator is busy. Try again shortly.");
      return;
    }
    const attemptId = response.data?.attempt_id;
    if (attemptId) {
      window.location.href = route("math-levels.session", attemptId);
      return;
    }
    error.value = __("Failed to start level.");
  } catch (err: any) {
    error.value =
      err?.response?.data?.message ||
      err?.message ||
      __("Failed to start level.");
  } finally {
    starting.value = null;
  }
}

onMounted(loadLevels);

watch(languageVersion, () => {
  loadLevels();
});
</script>

<template>
  <Head :title="__('Math Levels')" />

  <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
    <div
      class="rounded-2xl border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800/60 dark:bg-[#0B0F19]/60"
    >
      <div class="flex items-center gap-3">
        <div
          class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-600 text-white"
        >
          <Sparkles :size="20" />
        </div>
        <div>
          <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">
            {{ __("Math Levels") }}
          </h1>
          <p class="text-sm text-slate-500 dark:text-slate-300">
            {{ __("Choose your level, earn stars, and collect XP.") }}
          </p>
        </div>
      </div>

      <div class="mt-4 grid gap-3 sm:grid-cols-4">
        <div class="rounded-xl bg-slate-50 p-4 text-center dark:bg-slate-900/60">
          <p class="text-xs uppercase tracking-wider text-slate-400">XP</p>
          <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">
            {{ profile.total_xp }}
          </p>
        </div>
        <div class="rounded-xl bg-slate-50 p-4 text-center dark:bg-slate-900/60">
          <p class="text-xs uppercase tracking-wider text-slate-400">{{ __("Rank") }}</p>
          <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">
            {{ __(profile.current_rank) }}
          </p>
        </div>
        <div class="rounded-xl bg-slate-50 p-4 text-center dark:bg-slate-900/60">
          <p class="text-xs uppercase tracking-wider text-slate-400">{{ __("Stars") }}</p>
          <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">
            {{ profile.total_stars }}
          </p>
        </div>
        <div class="rounded-xl bg-slate-50 p-4 text-center dark:bg-slate-900/60">
          <p class="text-xs uppercase tracking-wider text-slate-400">
            {{ __("Completed") }}
          </p>
          <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">
            {{ profile.completed_levels }}
          </p>
        </div>
      </div>
    </div>

    <div
      v-if="loading"
      class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-400 dark:border-slate-800 dark:text-slate-400"
    >
      {{ __("Loading...") }}
    </div>

    <div
      v-else-if="error"
      class="rounded-2xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-500"
    >
      {{ error }}
    </div>

    <div v-else class="space-y-6">
      <div
        class="rounded-2xl border border-slate-200/70 bg-white p-4 shadow-sm dark:border-slate-800/60 dark:bg-[#0B0F19]/60"
      >
        <label class="text-xs font-semibold uppercase text-slate-400">
          {{ __("Difficulty") }}
        </label>
        <div class="mt-2 flex flex-wrap gap-2">
          <button
            type="button"
            class="rounded-full border px-3 py-1 text-xs font-semibold transition"
            :class="
              difficultyFilter === 'all'
                ? 'border-indigo-400 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/20 dark:text-indigo-200'
                : 'border-slate-200 text-slate-600 hover:border-indigo-200 dark:border-slate-700 dark:text-slate-300'
            "
            @click="difficultyFilter = 'all'"
          >
            {{ __("All") }}
          </button>
          <button
            type="button"
            class="rounded-full border px-3 py-1 text-xs font-semibold transition"
            :class="
              difficultyFilter === 'beginner'
                ? 'border-indigo-400 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/20 dark:text-indigo-200'
                : 'border-slate-200 text-slate-600 hover:border-indigo-200 dark:border-slate-700 dark:text-slate-300'
            "
            @click="difficultyFilter = 'beginner'"
          >
            {{ __("Beginner") }}
          </button>
          <button
            type="button"
            class="rounded-full border px-3 py-1 text-xs font-semibold transition"
            :class="
              difficultyFilter === 'medium'
                ? 'border-indigo-400 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/20 dark:text-indigo-200'
                : 'border-slate-200 text-slate-600 hover:border-indigo-200 dark:border-slate-700 dark:text-slate-300'
            "
            @click="difficultyFilter = 'medium'"
          >
            {{ __("Medium") }}
          </button>
          <button
            type="button"
            class="rounded-full border px-3 py-1 text-xs font-semibold transition"
            :class="
              difficultyFilter === 'hard'
                ? 'border-indigo-400 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/20 dark:text-indigo-200'
                : 'border-slate-200 text-slate-600 hover:border-indigo-200 dark:border-slate-700 dark:text-slate-300'
            "
            @click="difficultyFilter = 'hard'"
          >
            {{ __("Hard") }}
          </button>
        </div>
      </div>

      <div
        v-for="path in filteredPaths"
        :key="path.id"
        class="rounded-2xl border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800/60 dark:bg-[#0B0F19]/60"
      >
        <div class="mb-4">
          <h2 class="text-xl font-semibold text-slate-900 dark:text-white">
            {{ path.title }}
          </h2>
          <p v-if="path.description" class="text-sm text-slate-500 dark:text-slate-300">
            {{ path.description }}
          </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <div
            v-for="level in path.levels"
            :key="level.id"
            class="rounded-xl border border-slate-200 bg-slate-50 p-4 transition dark:border-slate-800 dark:bg-slate-900/60"
            :class="level.unlocked ? 'hover:border-indigo-300' : 'opacity-60'"
          >
            <div class="flex items-center justify-between">
              <div class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                {{ level.title }}
              </div>
              <div class="flex items-center gap-1 text-amber-500">
                <Star v-for="star in level.best_stars" :key="star" :size="14" />
              </div>
            </div>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
              {{ level.topic || __("General") }} | {{ __(level.difficulty || "Medium") }}
            </p>
            <div class="mt-3 flex items-center justify-end text-xs text-slate-500 dark:text-slate-400">
              <span>XP {{ level.xp_reward }}</span>
            </div>
            <div class="mt-2">
              <div class="mb-1 flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                <span>{{ __("Progress") }}</span>
                <span>{{ progressPercent(level) }}%</span>
              </div>
              <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                <div
                  class="h-full rounded-full bg-indigo-500 transition-all"
                  :style="{ width: progressPercent(level) + '%' }"
                />
              </div>
            </div>
            <button
              class="mt-4 w-full rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="!level.unlocked || starting === level.id"
              @click="startLevel(level.id)"
            >
              <span v-if="!level.unlocked" class="inline-flex items-center gap-1">
                <Lock :size="12" /> {{ __("Locked") }}
              </span>
              <span v-else>{{ starting === level.id ? __("Starting...") : __("Start") }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
