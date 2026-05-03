<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import axios from "axios";
import {
  Award,
  ChevronDown,
  ChevronUp,
  Medal,
  Minus,
  Star,
  Trophy,
} from "lucide-vue-next";
import { cmsTranslate as __ } from "@/lib/i18n";
import { computed, onMounted, onUnmounted, ref } from "vue";

type Trend = "up" | "down" | "neutral";

type LeaderboardUser = {
  user_id: number;
  rank: number;
  name: string;
  handle: string;
  avatar: string;
  level: number;
  xp: number;
  trend: Trend;
};

const loading = ref(true);
const error = ref<string | null>(null);
const seasonName = ref(__("Season 1"));
const seasonEndsAt = ref<string | null>(null);
const leaderboardData = ref<LeaderboardUser[]>([]);
const currentUser = ref<LeaderboardUser | null>(null);
const nowTick = ref(Date.now());
let timerId: number | null = null;

const topThree = computed(() => leaderboardData.value.slice(0, 3));
const listData = computed(() => leaderboardData.value.slice(3));

const seasonTimeLeft = computed(() => {
  if (!seasonEndsAt.value) return "-";
  const diffMs = new Date(seasonEndsAt.value).getTime() - nowTick.value;
  if (diffMs <= 0) return __("Ended");

  const totalMinutes = Math.floor(diffMs / (1000 * 60));
  const days = Math.floor(totalMinutes / (60 * 24));
  const hours = Math.floor((totalMinutes % (60 * 24)) / 60);
  const minutes = totalMinutes % 60;

  return `${days}d ${String(hours).padStart(2, "0")}h ${String(minutes).padStart(2, "0")}m`;
});

function userAt(index: number): LeaderboardUser {
  return (
    topThree.value[index] ?? {
      user_id: 0,
      rank: index + 1,
      name: "-",
      handle: "@-",
      avatar: "*",
      level: 1,
      xp: 0,
      trend: "neutral",
    }
  );
}

async function loadLeaderboard() {
  loading.value = true;
  error.value = null;

  try {
    const response = await axios.get("/api/math-levels/leaderboard", {
      params: { limit: 50 },
    });

    seasonName.value = response.data?.season?.name ?? __("Season 1");
    seasonEndsAt.value = response.data?.season?.ends_at ?? null;
    leaderboardData.value = response.data?.leaderboard ?? [];
    currentUser.value = response.data?.current_user ?? null;
  } catch (err: any) {
    error.value =
      err?.response?.data?.message ||
      err?.message ||
      __("Failed to load leaderboard.");
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  loadLeaderboard();
  timerId = window.setInterval(() => {
    nowTick.value = Date.now();
  }, 60000);
});

onUnmounted(() => {
  if (timerId) {
    window.clearInterval(timerId);
  }
});
</script>

<template>
  <Head :title="__('Hall of Fame')" />

  <div
    class="mx-auto w-full max-w-5xl animate-in fade-in p-6 text-foreground duration-500 dark:text-foreground lg:p-12"
  >
    <div
      class="mb-12 flex flex-col justify-between gap-6 md:flex-row md:items-end"
    >
      <div>
        <div
          class="mb-4 inline-flex items-center gap-2 rounded-full border border-chart-3/20 bg-chart-3/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-chart-3"
        >
          <Trophy :size="14" /> {{ __("Global League") }}
        </div>
        <h1
          class="mb-2 text-3xl font-bold text-foreground dark:text-primary-foreground md:text-4xl"
        >
          {{ __("Hall of Fame") }}
        </h1>
        <p class="max-w-lg text-muted-foreground dark:text-muted-foreground">
          {{
            __(
              "Solve problems, earn XP, and climb the ranks. Top scholars this season get platform rewards.",
            )
          }}
        </p>
      </div>

      <div
        class="flex items-center gap-4 rounded-xl border border-border bg-card/80 p-4 shadow-sm dark:border-border dark:bg-card/50 dark:shadow-none"
      >
        <div
          class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10"
        >
          <Star class="text-primary" :size="24" />
        </div>
        <div>
          <p
            class="text-xs font-bold uppercase tracking-wider text-muted-foreground dark:text-muted-foreground"
          >
            {{ seasonName }} {{ __("Ends In") }}
          </p>
          <p class="font-mono text-xl font-bold text-foreground dark:text-primary-foreground">
            {{ seasonTimeLeft }}
          </p>
        </div>
      </div>
    </div>

    <div
      v-if="loading"
      class="mb-8 rounded-2xl border border-border bg-card p-8 text-center text-muted-foreground dark:border-border dark:bg-card/40 dark:text-muted-foreground"
    >
      {{ __("Loading leaderboard...") }}
    </div>

    <div
      v-else-if="error"
      class="mb-8 rounded-2xl border border-destructive/30 bg-destructive/10 p-4 text-destructive"
    >
      {{ error }}
    </div>

    <template v-else>
      <div
        class="mt-8 mb-16 flex h-64 flex-row items-end justify-center gap-2 md:gap-6"
      >
        <div class="group relative flex w-28 flex-col items-center md:w-40">
          <div
            class="pointer-events-none absolute -top-16 z-10 whitespace-nowrap rounded-lg border border-border bg-card px-3 py-2 text-xs text-card-foreground opacity-0 transition-opacity group-hover:opacity-100"
          >
            {{ userAt(1).xp.toLocaleString() }} XP
          </div>
          <div
            class="z-10 mb-[-1.5rem] flex h-16 w-16 items-center justify-center rounded-full border-4 border-border bg-card text-xl font-bold text-muted-foreground shadow-lg shadow-border/40 dark:bg-card dark:text-muted-foreground"
          >
            {{ userAt(1).avatar }}
          </div>
          <div
            class="relative flex h-32 w-full flex-col items-center justify-start overflow-hidden rounded-t-xl border-t-2 border-border bg-gradient-to-t from-muted via-muted to-card pt-8 pb-4 dark:from-card dark:via-card dark:to-card"
          >
            <div class="absolute inset-0 bg-muted/5" />
            <Medal :size="24" class="mb-1 text-muted-foreground" />
            <span class="text-2xl font-bold text-muted-foreground dark:text-foreground"
              >2</span
            >
          </div>
          <div class="mt-4 text-center">
            <p
            class="w-full truncate px-2 text-sm font-bold text-foreground dark:text-foreground"
            >
              {{ userAt(1).name }}
            </p>
            <p class="mt-0.5 text-xs text-primary">
              LvL {{ userAt(1).level }}
            </p>
          </div>
        </div>

        <div class="group relative flex w-32 flex-col items-center md:w-48">
          <div
            class="pointer-events-none absolute -top-16 z-10 whitespace-nowrap rounded-lg border border-border bg-card px-3 py-2 text-xs text-card-foreground opacity-0 transition-opacity group-hover:opacity-100"
          >
            {{ userAt(0).xp.toLocaleString() }} XP
          </div>
          <div
            class="z-10 mb-[-1.5rem] flex h-20 w-20 items-center justify-center rounded-full border-4 border-chart-3 bg-chart-3/10 text-2xl font-bold text-chart-3 shadow-lg shadow-chart-3/40"
          >
            {{ userAt(0).avatar }}
          </div>
          <div
            class="relative flex h-44 w-full flex-col items-center justify-start overflow-hidden rounded-t-xl border-t-2 border-chart-3 bg-gradient-to-t from-muted via-chart-3/70 to-chart-3 pt-10 pb-4 dark:from-card dark:via-chart-3/20 dark:to-chart-3/30"
          >
            <div
              class="absolute inset-x-0 top-0 h-1/2 bg-gradient-to-b from-chart-3/20 to-transparent"
            />
            <Trophy :size="32" class="mb-1 text-chart-3 drop-shadow-lg" />
            <span class="text-3xl font-bold text-chart-3">1</span>
          </div>
          <div class="mt-4 text-center">
            <p
              class="w-full truncate px-2 text-base font-bold text-foreground dark:text-foreground"
            >
              {{ userAt(0).name }}
            </p>
            <p class="mt-0.5 text-xs font-medium text-chart-3">
              LvL {{ userAt(0).level }}
            </p>
          </div>
        </div>

        <div class="group relative flex w-28 flex-col items-center md:w-40">
          <div
            class="pointer-events-none absolute -top-16 z-10 whitespace-nowrap rounded-lg border border-border bg-card px-3 py-2 text-xs text-card-foreground opacity-0 transition-opacity group-hover:opacity-100"
          >
            {{ userAt(2).xp.toLocaleString() }} XP
          </div>
          <div
            class="z-10 mb-[-1.5rem] flex h-16 w-16 items-center justify-center rounded-full border-4 border-chart-3 bg-card text-xl font-bold text-chart-3 shadow-lg shadow-chart-3/30 dark:bg-card"
          >
            {{ userAt(2).avatar }}
          </div>
          <div
            class="relative flex h-24 w-full flex-col items-center justify-start overflow-hidden rounded-t-xl border-t-2 border-chart-3 bg-gradient-to-t from-muted via-muted to-chart-3/60 pt-8 pb-4 dark:from-card dark:via-card dark:to-chart-3/40"
          >
            <div class="absolute inset-0 bg-chart-3/5" />
            <Award :size="24" class="mb-1 text-chart-3" />
            <span class="text-xl font-bold text-chart-3">3</span>
          </div>
          <div class="mt-4 text-center">
            <p
              class="w-full truncate px-2 text-sm font-bold text-foreground dark:text-foreground"
            >
              {{ userAt(2).name }}
            </p>
            <p class="mt-0.5 text-xs text-primary">
              LvL {{ userAt(2).level }}
            </p>
          </div>
        </div>
      </div>

      <div
        v-if="currentUser"
        class="relative mb-8 flex items-center justify-between overflow-hidden rounded-2xl border border-primary/60 bg-gradient-to-r from-primary to-chart-4 p-4 shadow-lg shadow-primary/20 backdrop-blur-md dark:border-primary/30 dark:from-primary/20 dark:to-chart-4/20 md:p-6"
      >
        <div class="shimmer-line" />

        <div class="z-10 flex items-center gap-4 md:gap-6">
          <div
            class="w-12 text-center text-2xl font-black text-primary dark:text-primary md:w-16 md:text-3xl"
          >
            #{{ currentUser.rank }}
          </div>
          <div
            class="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-tr from-primary to-chart-4 font-bold text-primary-foreground shadow-lg ring-2 ring-ring/50"
          >
            {{ currentUser.avatar }}
          </div>
          <div>
            <h3 class="text-lg font-bold text-foreground dark:text-primary-foreground">
              {{ currentUser.name }}
              <span
                class="ml-2 hidden text-sm font-normal text-muted-foreground md:inline"
                >{{ __("You") }}</span
              >
            </h3>
            <p class="text-sm text-primary dark:text-primary">
              {{ currentUser.handle }}
            </p>
          </div>
        </div>

        <div class="z-10 flex items-center gap-6 md:gap-12">
          <div class="hidden text-right md:block">
            <p class="mb-1 text-xs uppercase tracking-wider text-muted-foreground">
              {{ __("Level") }}
            </p>
            <p class="text-lg font-bold text-foreground dark:text-primary-foreground">
              {{ currentUser.level }}
            </p>
          </div>
          <div class="text-right">
            <p class="mb-1 text-xs uppercase tracking-wider text-muted-foreground">
              {{ __("Total XP") }}
            </p>
            <p class="font-mono text-lg font-bold text-chart-2">
              {{ currentUser.xp.toLocaleString() }}
            </p>
          </div>
          <div class="flex w-8 justify-center">
            <ChevronUp
              v-if="currentUser.trend === 'up'"
              :size="24"
              class="text-chart-2"
            />
            <ChevronDown
              v-else-if="currentUser.trend === 'down'"
              :size="24"
              class="text-destructive"
            />
            <Minus v-else :size="24" class="text-muted-foreground" />
          </div>
        </div>
      </div>

      <div
        class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm dark:border-border dark:bg-card/40 dark:shadow-none"
      >
        <div
          class="grid grid-cols-12 gap-4 border-b border-border bg-muted p-4 text-xs font-semibold uppercase tracking-wider text-muted-foreground dark:border-border/60 dark:bg-card/80"
        >
          <div class="col-span-2 text-center md:col-span-1">
            {{ __("Rank") }}
          </div>
          <div class="col-span-6 md:col-span-5">{{ __("Scholar") }}</div>
          <div class="col-span-2 hidden text-center md:block">
            {{ __("Level") }}
          </div>
          <div class="col-span-4 text-right md:col-span-3">
            {{ __("Experience") }}
          </div>
          <div class="col-span-2 text-center md:col-span-1">
            {{ __("Trend") }}
          </div>
        </div>

        <div class="divide-y divide-border dark:divide-border/50">
          <div
            v-for="user in listData"
            :key="user.rank"
            class="group grid grid-cols-12 items-center gap-4 p-4 transition-colors hover:bg-muted dark:hover:bg-card/30"
          >
            <div
              class="col-span-2 text-center text-lg font-bold text-muted-foreground dark:text-muted-foreground md:col-span-1"
            >
              #{{ user.rank }}
            </div>

            <div class="col-span-6 flex items-center gap-3 md:col-span-5">
              <div
                class="flex h-10 w-10 items-center justify-center rounded-full border border-border bg-muted text-sm font-bold text-muted-foreground transition-colors group-hover:border-primary/50 dark:border-border dark:bg-card dark:text-muted-foreground"
              >
                {{ user.avatar }}
              </div>
              <div>
                <h4
                  class="text-sm font-medium text-foreground dark:text-primary-foreground md:text-base"
                >
                  {{ user.name }}
                </h4>
                <p class="text-xs text-muted-foreground">{{ user.handle }}</p>
              </div>
            </div>

            <div class="col-span-2 hidden items-center justify-center md:flex">
              <span
                class="rounded-md border border-border bg-muted px-2.5 py-1 text-xs font-medium text-primary dark:border-border dark:bg-card dark:text-primary"
              >
                Lv {{ user.level }}
              </span>
            </div>

            <div
              class="col-span-4 text-right font-mono text-sm font-medium text-muted-foreground dark:text-muted-foreground md:col-span-3 md:text-base"
            >
              {{ user.xp.toLocaleString() }}
              <span class="font-sans text-xs text-muted-foreground">XP</span>
            </div>

            <div class="col-span-2 flex justify-center md:col-span-1">
              <ChevronUp
                v-if="user.trend === 'up'"
                :size="20"
                class="text-chart-2"
              />
              <ChevronDown
                v-else-if="user.trend === 'down'"
                :size="20"
                class="text-destructive"
              />
              <Minus v-else :size="20" class="text-muted-foreground" />
            </div>
          </div>
        </div>

        <div
          class="border-t border-border bg-muted p-4 text-center dark:border-border/60 dark:bg-card/20"
        >
          <button
            class="text-sm font-medium text-primary transition-colors hover:text-primary dark:text-primary dark:hover:text-primary"
          >
            {{ __("Live ranking updates every minute") }}
          </button>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
@keyframes shimmer {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(100%);
  }
}

.shimmer-line {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    to right,
    transparent,
    color-mix(in srgb, var(--primary-foreground) 6%, transparent),
    transparent
  );
  animation: shimmer 3s linear infinite;
}
</style>
