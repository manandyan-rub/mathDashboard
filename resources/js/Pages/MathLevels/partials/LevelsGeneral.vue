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
      class="rounded-2xl border border-border/70 bg-card p-6 shadow-sm dark:border-border/60 dark:bg-card/60"
    >
      <div class="flex items-center gap-3">
        <div
          class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary text-primary-foreground"
        >
          <Sparkles :size="20" />
        </div>
        <div>
          <h1 class="text-2xl font-semibold text-foreground dark:text-primary-foreground">
            {{ __("Math Levels") }}
          </h1>
          <p class="text-sm text-muted-foreground dark:text-muted-foreground">
            {{ __("Choose your level, earn stars, and collect XP.") }}
          </p>
        </div>
      </div>

      <div class="mt-4 grid gap-3 sm:grid-cols-4">
        <div class="rounded-xl bg-muted p-4 text-center dark:bg-card/60">
          <p class="text-xs uppercase tracking-wider text-muted-foreground">XP</p>
          <p class="mt-2 text-2xl font-semibold text-foreground dark:text-primary-foreground">
            {{ profile.total_xp }}
          </p>
        </div>
        <div class="rounded-xl bg-muted p-4 text-center dark:bg-card/60">
          <p class="text-xs uppercase tracking-wider text-muted-foreground">{{ __("Rank") }}</p>
          <p class="mt-2 text-2xl font-semibold text-foreground dark:text-primary-foreground">
            {{ __(profile.current_rank) }}
          </p>
        </div>
        <div class="rounded-xl bg-muted p-4 text-center dark:bg-card/60">
          <p class="text-xs uppercase tracking-wider text-muted-foreground">{{ __("Stars") }}</p>
          <p class="mt-2 text-2xl font-semibold text-foreground dark:text-primary-foreground">
            {{ profile.total_stars }}
          </p>
        </div>
        <div class="rounded-xl bg-muted p-4 text-center dark:bg-card/60">
          <p class="text-xs uppercase tracking-wider text-muted-foreground">
            {{ __("Completed") }}
          </p>
          <p class="mt-2 text-2xl font-semibold text-foreground dark:text-primary-foreground">
            {{ profile.completed_levels }}
          </p>
        </div>
      </div>
    </div>

    <div
      v-if="loading"
      class="rounded-2xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground dark:border-border dark:text-muted-foreground"
    >
      {{ __("Loading...") }}
    </div>

    <div
      v-else-if="error"
      class="rounded-2xl border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive"
    >
      {{ error }}
    </div>

    <div v-else class="space-y-6">
      <div
        class="rounded-2xl border border-border/70 bg-card p-4 shadow-sm dark:border-border/60 dark:bg-card/60"
      >
        <label class="text-xs font-semibold uppercase text-muted-foreground">
          {{ __("Difficulty") }}
        </label>
        <div class="mt-2 flex flex-wrap gap-2">
          <button
            type="button"
            class="rounded-full border px-3 py-1 text-xs font-semibold transition"
            :class="
              difficultyFilter === 'all'
                ? 'border-primary/30 bg-accent text-accent-foreground dark:border-primary/40 dark:bg-primary/20 dark:text-primary'
                : 'border-border text-muted-foreground hover:border-primary dark:border-border dark:text-muted-foreground'
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
                ? 'border-primary/30 bg-accent text-accent-foreground dark:border-primary/40 dark:bg-primary/20 dark:text-primary'
                : 'border-border text-muted-foreground hover:border-primary dark:border-border dark:text-muted-foreground'
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
                ? 'border-primary/30 bg-accent text-accent-foreground dark:border-primary/40 dark:bg-primary/20 dark:text-primary'
                : 'border-border text-muted-foreground hover:border-primary dark:border-border dark:text-muted-foreground'
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
                ? 'border-primary/30 bg-accent text-accent-foreground dark:border-primary/40 dark:bg-primary/20 dark:text-primary'
                : 'border-border text-muted-foreground hover:border-primary dark:border-border dark:text-muted-foreground'
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
        class="rounded-2xl border border-border/70 bg-card p-6 shadow-sm dark:border-border/60 dark:bg-card/60"
      >
        <div class="mb-4">
          <h2 class="text-xl font-semibold text-foreground dark:text-primary-foreground">
            {{ path.title }}
          </h2>
          <p v-if="path.description" class="text-sm text-muted-foreground dark:text-muted-foreground">
            {{ path.description }}
          </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <div
            v-for="level in path.levels"
            :key="level.id"
            class="rounded-xl border border-border bg-muted p-4 transition dark:border-border dark:bg-card/60"
            :class="level.unlocked ? 'hover:border-primary' : 'opacity-60'"
          >
            <div class="flex items-center justify-between">
              <div class="text-sm font-semibold text-muted-foreground dark:text-foreground">
                {{ level.title }}
              </div>
              <div class="flex items-center gap-1 text-chart-3">
                <Star v-for="star in level.best_stars" :key="star" :size="14" />
              </div>
            </div>
            <p class="mt-1 text-xs text-muted-foreground dark:text-muted-foreground">
              {{ level.topic || __("General") }} | {{ __(level.difficulty || "Medium") }}
            </p>
            <div class="mt-3 flex items-center justify-end text-xs text-muted-foreground dark:text-muted-foreground">
              <span>XP {{ level.xp_reward }}</span>
            </div>
            <div class="mt-2">
              <div class="mb-1 flex items-center justify-between text-[11px] text-muted-foreground dark:text-muted-foreground">
                <span>{{ __("Progress") }}</span>
                <span>{{ progressPercent(level) }}%</span>
              </div>
              <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted dark:bg-card">
                <div
                  class="h-full rounded-full bg-primary transition-all"
                  :style="{ width: progressPercent(level) + '%' }"
                />
              </div>
            </div>
            <button
              class="mt-4 w-full rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-primary-foreground transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
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
