<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import { cmsTranslate as __, useLanguageVersion } from "@/lib/i18n";
import axios from "axios";
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import {
  Award,
  Check,
  Coins,
  LoaderCircle,
  Lock,
  Play,
  Sparkles,
  Star,
  Trophy,
  Zap,
} from "lucide-vue-next";
import AnimatedNumber from "@/components/motion/AnimatedNumber.vue";

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

function startLevel(levelId: number) {
  starting.value = levelId;
  error.value = null;

  router.post(
    "/api/math-levels/start",
    {
      level_id: levelId,
    },
    {
      preserveScroll: true,
      onError: (errors) => {
        error.value =
          String(Object.values(errors)[0] ?? "") ||
          __("Failed to start level.");
      },
      onFinish: () => {
        starting.value = null;
      },
    },
  );
}

const heroStats = computed(() => [
  { key: "xp", label: "XP", value: Number(profile.value.total_xp) || 0, icon: Coins, tone: "bg-primary/15 text-primary" },
  { key: "rank", label: __("Rank"), value: __(profile.value.current_rank), icon: Trophy, tone: "bg-chart-4/15 text-chart-4" },
  { key: "stars", label: __("Stars"), value: Number(profile.value.total_stars) || 0, icon: Star, tone: "bg-chart-3/15 text-chart-3" },
  { key: "completed", label: __("Completed"), value: Number(profile.value.completed_levels) || 0, icon: Award, tone: "bg-chart-2/15 text-chart-2" },
]);

const difficultyOptions = computed(() => [
  { value: "all" as const, label: __("All") },
  { value: "beginner" as const, label: __("Beginner") },
  { value: "medium" as const, label: __("Medium") },
  { value: "hard" as const, label: __("Hard") },
]);

const difficultyTone = (value: string | null | undefined) =>
  ({
    beginner: "bg-chart-2/15 text-chart-2",
    medium: "bg-chart-3/15 text-chart-3",
    hard: "bg-destructive/15 text-destructive",
  })[difficultyBucket(value)];

const completedCount = (path: PathItem) =>
  path.levels.filter((level) => level.completed).length;

const pathPercent = (path: PathItem) =>
  path.levels.length === 0
    ? 0
    : Math.round((completedCount(path) / path.levels.length) * 100);

/* sliding pill behind the active difficulty filter */
const filterTrack = ref<HTMLElement | null>(null);
const filterButtons = new Map<string, HTMLElement>();
const indicatorStyle = ref<Record<string, string>>({ opacity: "0" });

function setFilterButton(value: string, el: unknown) {
  if (el instanceof HTMLElement) filterButtons.set(value, el);
  else filterButtons.delete(value);
}

function moveIndicator() {
  const button = filterButtons.get(difficultyFilter.value);
  if (!button) return;
  indicatorStyle.value = {
    left: `${button.offsetLeft}px`,
    width: `${button.offsetWidth}px`,
    opacity: "1",
  };
}

watch(difficultyFilter, () => nextTick(moveIndicator));
watch(loading, (isLoading) => {
  if (!isLoading) nextTick(moveIndicator);
});
watch(languageVersion, () => nextTick(moveIndicator));

onMounted(() => {
  window.addEventListener("resize", moveIndicator);
});
onBeforeUnmount(() => {
  window.removeEventListener("resize", moveIndicator);
});

onMounted(loadLevels);

watch(languageVersion, () => {
  loadLevels();
});
</script>

<template>
  <Head :title="__('Math Levels')" />

  <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
    <div
      class="aurora rounded-2xl border border-border/70 bg-card/70 p-6 shadow-sm backdrop-blur-sm md:p-8"
    >
      <span class="float-glyph" style="top: 10%; right: 6%; font-size: 4.5rem; --dur: 10s; --dx: -16px" aria-hidden="true">★</span>
      <span class="float-glyph" style="bottom: 12%; right: 30%; font-size: 2.5rem; --dur: 8s; --delay: -3s" aria-hidden="true">π</span>
      <span class="float-glyph" style="top: 18%; right: 42%; font-size: 2rem; --dur: 11s; --delay: -6s; --dx: 18px" aria-hidden="true">Δ</span>

      <div v-reveal="'blur'" class="flex items-center gap-4">
        <div
          class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-chart-4 text-primary-foreground shadow-lg shadow-primary/30"
        >
          <Sparkles :size="24" class="spin-slow" />
        </div>
        <div>
          <h1 class="text-shine text-3xl font-extrabold tracking-tight">
            {{ __("Math Levels") }}
          </h1>
          <p class="text-sm text-muted-foreground">
            {{ __("Choose your level, earn stars, and collect XP.") }}
          </p>
        </div>
      </div>

      <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div
          v-for="(stat, index) in heroStats"
          :key="stat.key"
          v-reveal="{ variant: 'up', delay: 120 + index * 90 }"
          v-tilt
          class="rounded-xl border border-border bg-card/80 p-4"
        >
          <div class="flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
              {{ stat.label }}
            </p>
            <span
              class="motion-pop flex h-8 w-8 items-center justify-center rounded-lg"
              :class="stat.tone"
            >
              <component :is="stat.icon" :size="16" />
            </span>
          </div>
          <p class="mt-2 text-2xl font-bold text-foreground">
            <AnimatedNumber v-if="typeof stat.value === 'number'" :value="stat.value" />
            <span v-else>{{ stat.value }}</span>
          </p>
        </div>
      </div>
    </div>

    <div
      v-if="loading"
      class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
      aria-busy="true"
    >
      <div
        v-for="n in 6"
        :key="n"
        class="h-48 animate-pulse rounded-xl border border-border bg-muted/60"
        :style="{ animationDelay: `${n * 90}ms` }"
      />
    </div>

    <div
      v-else-if="error"
      class="shake rounded-2xl border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive"
    >
      {{ error }}
    </div>

    <div v-else class="space-y-6">
      <div
        v-reveal="'up'"
        class="flex flex-col gap-3 rounded-2xl border border-border/70 bg-card/70 p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
      >
        <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
          {{ __("Difficulty") }}
        </span>
        <div
          ref="filterTrack"
          class="relative inline-flex max-w-full overflow-x-auto rounded-full border border-border bg-muted/60 p-1"
          role="tablist"
        >
          <span
            class="absolute top-1 bottom-1 rounded-full bg-gradient-to-r from-primary to-chart-4 shadow-md shadow-primary/30 transition-all duration-500 ease-[cubic-bezier(0.34,1.56,0.64,1)]"
            :style="indicatorStyle"
            aria-hidden="true"
          />
          <button
            v-for="option in difficultyOptions"
            :key="option.value"
            :ref="(el) => setFilterButton(option.value, el)"
            type="button"
            role="tab"
            :aria-selected="difficultyFilter === option.value"
            class="relative z-10 whitespace-nowrap rounded-full px-4 py-1.5 text-xs font-semibold transition-colors duration-300"
            :class="
              difficultyFilter === option.value
                ? 'text-primary-foreground'
                : 'text-muted-foreground hover:text-foreground'
            "
            @click="difficultyFilter = option.value"
          >
            {{ option.label }}
          </button>
        </div>
      </div>

      <TransitionGroup name="list" tag="div" class="space-y-6">
        <section
          v-for="path in filteredPaths"
          :key="path.id"
          v-reveal="'up'"
          class="rounded-2xl border border-border/70 bg-card/70 p-6 shadow-sm"
        >
          <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div>
              <h2 class="text-xl font-bold text-foreground">
                {{ path.title }}
              </h2>
              <p v-if="path.description" class="text-sm text-muted-foreground">
                {{ path.description }}
              </p>
            </div>
            <div class="flex items-center gap-2 text-xs text-muted-foreground">
              <span>{{ completedCount(path) }} / {{ path.levels.length }}</span>
              <div class="h-1.5 w-28 overflow-hidden rounded-full bg-muted">
                <div
                  :key="`${path.id}-${difficultyFilter}`"
                  class="bar-grow h-full rounded-full bg-gradient-to-r from-primary to-chart-2"
                  :style="{ width: `${pathPercent(path)}%` }"
                />
              </div>
            </div>
          </div>

          <TransitionGroup
            name="list"
            tag="div"
            class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
          >
            <article
              v-for="(level, index) in path.levels"
              :key="level.id"
              v-reveal="{ variant: 'up', delay: (index % 3) * 90 }"
              v-tilt="level.unlocked ? 6 : 0"
              class="flex flex-col rounded-xl border border-border bg-card p-4"
              :class="[
                level.unlocked ? '' : 'lock-wiggle opacity-60 grayscale-[40%]',
                level.completed ? 'border-chart-2/40' : '',
              ]"
              :style="{ '--i-delay': `${(index % 6) * 50}ms` }"
            >
              <div class="flex items-start justify-between gap-2">
                <div class="flex items-center gap-2">
                  <span
                    class="motion-pop flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold"
                    :class="
                      level.completed
                        ? 'bg-chart-2/15 text-chart-2'
                        : level.unlocked
                          ? 'bg-primary/15 text-primary'
                          : 'bg-muted text-muted-foreground'
                    "
                  >
                    <Check v-if="level.completed" :size="16" :stroke-width="3" />
                    <Lock v-else-if="!level.unlocked" :size="14" class="lock-icon" />
                    <span v-else>{{ level.order }}</span>
                  </span>
                  <h3 class="text-sm font-semibold text-foreground">
                    {{ level.title }}
                  </h3>
                </div>
                <div class="flex items-center gap-0.5">
                  <span
                    v-for="star in 3"
                    :key="star"
                    class="star-pop"
                    :style="{ '--star-delay': `${300 + star * 120}ms` }"
                  >
                    <Star
                      :size="15"
                      :class="
                        star <= level.best_stars
                          ? 'star-glow fill-chart-3 text-chart-3'
                          : 'text-muted-foreground/40'
                      "
                    />
                  </span>
                </div>
              </div>

              <div class="mt-2 flex flex-wrap items-center gap-1.5 text-[11px]">
                <span class="rounded-full bg-muted px-2 py-0.5 text-muted-foreground">
                  {{ level.topic || __("General") }}
                </span>
                <span
                  class="rounded-full px-2 py-0.5 font-medium"
                  :class="difficultyTone(level.difficulty)"
                >
                  {{ __(level.difficulty || "Medium") }}
                </span>
                <span class="ml-auto inline-flex items-center gap-1 font-semibold text-primary">
                  <Zap :size="12" /> {{ level.xp_reward }} XP
                </span>
              </div>

              <div class="mt-4">
                <div class="mb-1 flex items-center justify-between text-[11px] text-muted-foreground">
                  <span>{{ __("Progress") }}</span>
                  <span class="font-semibold text-foreground">{{ progressPercent(level) }}%</span>
                </div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                  <div
                    v-if="progressPercent(level) > 0"
                    class="bar-grow h-full rounded-full bg-gradient-to-r from-primary to-chart-4"
                    :style="{
                      width: progressPercent(level) + '%',
                      '--bar-delay': `${200 + (index % 3) * 90}ms`,
                    }"
                  />
                </div>
              </div>

              <button
                class="mt-4 inline-flex w-full items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold transition disabled:cursor-not-allowed"
                :class="
                  level.unlocked
                    ? 'btn-sheen text-primary-foreground'
                    : 'border border-border bg-muted text-muted-foreground'
                "
                :disabled="!level.unlocked || starting === level.id"
                @click="startLevel(level.id)"
              >
                <template v-if="!level.unlocked">
                  <Lock :size="12" class="lock-icon" /> {{ __("Locked") }}
                </template>
                <template v-else-if="starting === level.id">
                  <LoaderCircle :size="14" class="animate-spin" /> {{ __("Starting...") }}
                </template>
                <template v-else>
                  <Play :size="12" class="fill-current" />
                  {{ level.completed ? __("Play again") : __("Start") }}
                </template>
              </button>
            </article>
          </TransitionGroup>
        </section>
      </TransitionGroup>
    </div>
  </div>
</template>
