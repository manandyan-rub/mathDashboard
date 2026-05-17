<script setup>
import BarChart from "@/charts/BarChart.vue";
import DonutChart from "@/charts/DonutChart.vue";
import HeatmapChart from "@/charts/HeatmapChart.vue";
import LineChart from "@/charts/LineChart.vue";
import FormCalendarRange from "@/Forms/FormCalendarRange.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head } from "@inertiajs/vue3";
import {
  Award,
  BarChart3,
  Clock3,
  Coins,
  Globe2,
  History,
  Users,
} from "lucide-vue-next";
import { computed, onMounted } from "vue";
import { useDashboardAnalytics } from "./Dashboard/useDashboardAnalytics";

defineOptions({
  layout: AppLayout,
});

const props = defineProps({
  scope: {
    type: String,
    default: "local",
  },
});

const isGlobalDashboard = computed(() => props.scope === "global");
const isLocalDashboard = computed(() => !isGlobalDashboard.value);
const pageTitle = computed(() =>
  isGlobalDashboard.value ? "Global dashboard" : "Local dashboard",
);
const pageEyebrow = computed(() =>
  isGlobalDashboard.value ? "Global activity" : "Your stats",
);
const pageHeading = computed(() =>
  isGlobalDashboard.value
    ? "What is happening across the platform"
    : "Your learning data",
);
const pageDescription = computed(() =>
  isGlobalDashboard.value
    ? "Platform-wide mode usage, learner distribution, and completed math levels."
    : "XP, streak behavior, source mix, and the most recent claims from your account.",
);

const {
  loading,
  error,
  dateRange,
  activityHeatmap,
  activityHeatmapHasData,
  dailyLabels,
  dailyXpValues,
  dailyClaimValues,
  retentionValues,
  sourceSlices,
  popularModeLabels,
  popularModeClaimValues,
  popularModeXpValues,
  userLevelLabels,
  userLevelUserValues,
  userLevelXpValues,
  completedLevelLabels,
  completedLevelUserValues,
  recentEvents,
  totalXp,
  periodXp,
  periodClaims,
  bestDayXp,
  retentionRate,
  funnelSteps,
  insightCards,
  globalInsightCards,
  loadAnalytics,
  formatNumber,
  formatEventTime,
  eventDetail,
} = useDashboardAnalytics();

onMounted(loadAnalytics);
</script>

<template>
  <Head :title="pageTitle" />

  <div class="mx-auto max-w-7xl space-y-6">
    <section class="flex flex-col gap-2">
      <p class="text-sm font-medium text-primary">
        Learning analytics
      </p>
      <div class="flex flex-col justify-between gap-3 md:flex-row md:items-end">
        <div>
          <h2 class="text-2xl font-bold text-foreground">
            {{ pageHeading }}
          </h2>
          <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
            {{ pageDescription }}
          </p>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
          <FormCalendarRange
            v-model="dateRange"
            placeholder="Filter chart range"
            :number-of-months="2"
            class="sm:w-[310px]"
            trigger-class="h-10 bg-card text-card-foreground shadow-sm"
            @change="loadAnalytics"
          />

          <button
            type="button"
            class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-border bg-card px-3 text-sm font-semibold text-card-foreground transition hover:border-ring hover:bg-accent hover:text-accent-foreground"
            @click="loadAnalytics"
          >
            <History :size="16" />
            Refresh
          </button>
        </div>
      </div>
    </section>

    <div
      v-if="error"
      class="rounded-md border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
    >
      {{ error }}
    </div>

    <section v-if="isLocalDashboard" class="space-y-4">
      <div class="flex flex-col justify-between gap-3 md:flex-row md:items-end">
        <div>
          <p class="text-sm font-semibold text-primary">{{ pageEyebrow }}</p>
          <h3 class="mt-1 text-xl font-bold text-foreground">
            Your learning data
          </h3>
          <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
            XP, streak behavior, source mix, and the most recent claims from
            your account.
          </p>
        </div>
        <div class="rounded-md border border-border bg-card px-3 py-2 text-sm text-card-foreground shadow-sm">
          <span class="text-muted-foreground">Active days</span>
          <span class="ml-2 font-bold text-primary">{{ retentionRate }}%</span>
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-lg border border-border bg-card p-4 text-card-foreground shadow-sm">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-muted-foreground">Total XP</p>
            <Coins :size="18" class="text-primary" />
          </div>
          <p class="mt-3 text-3xl font-bold text-card-foreground">
            {{ formatNumber(totalXp) }}
          </p>
          <p class="mt-1 text-xs text-muted-foreground">Lifetime claimed XP</p>
        </div>

        <div class="rounded-lg border border-border bg-card p-4 text-card-foreground shadow-sm">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-muted-foreground">Period XP</p>
            <BarChart3 :size="18" class="text-primary" />
          </div>
          <p class="mt-3 text-3xl font-bold text-card-foreground">
            {{ formatNumber(periodXp) }}
          </p>
          <p class="mt-1 text-xs text-muted-foreground">
            XP claimed in the selected period
          </p>
        </div>

        <div class="rounded-lg border border-border bg-card p-4 text-card-foreground shadow-sm">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-muted-foreground">
              Period claims
            </p>
            <Clock3 :size="18" class="text-primary" />
          </div>
          <p class="mt-3 text-3xl font-bold text-card-foreground">
            {{ formatNumber(periodClaims) }}
          </p>
          <p class="mt-1 text-xs text-muted-foreground">
            Number of XP claim events
          </p>
        </div>

        <div class="rounded-lg border border-border bg-card p-4 text-card-foreground shadow-sm">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-muted-foreground">
              Best day XP
            </p>
            <Award :size="18" class="text-primary" />
          </div>
          <p class="mt-3 text-3xl font-bold text-card-foreground">
            {{ formatNumber(bestDayXp) }}
          </p>
          <p class="mt-1 text-xs text-muted-foreground">
            Highest single-day XP in this period
          </p>
        </div>
      </div>

      <section class="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(320px,1fr)_minmax(320px,1fr)]">
        <div class="rounded-lg border border-border bg-card p-5 text-card-foreground shadow-sm">
          <div class="mb-4 flex items-center justify-between">
            <h3 class="text-base font-semibold text-card-foreground">
              Claim mix
            </h3>
            <p class="text-xs text-muted-foreground">Your flow</p>
          </div>

          <div class="space-y-3">
            <div
              v-for="step in funnelSteps"
              :key="step.label"
              class="space-y-1"
            >
              <div class="flex items-center justify-between text-xs text-muted-foreground">
                <span>{{ step.label }}</span>
                <span class="font-semibold text-card-foreground">
                  {{ formatNumber(step.value) }}
                </span>
              </div>
              <div class="h-3 rounded-full bg-muted">
                <div
                  class="h-3 rounded-full bg-gradient-to-r from-primary to-accent-foreground"
                  :style="{ width: `${step.widthPercent}%` }"
                />
              </div>
            </div>
          </div>
        </div>

        <LineChart
          title="Retention"
          :description="`Active days ${retentionRate}%`"
          :labels="dailyLabels"
          :values="retentionValues"
          :secondary-values="dailyClaimValues"
          series-name="Retention"
          tooltip-value-label="Status"
          secondary-label="Claims"
          color="--chart-4"
          :binary="true"
          :step="true"
          size="sm"
          :loading="loading"
        />

        <div class="rounded-lg border border-border bg-card p-5 text-card-foreground shadow-sm">
          <div class="mb-4 flex items-center justify-between">
            <h3 class="text-base font-semibold text-card-foreground">
              Insights
            </h3>
            <p class="text-xs text-muted-foreground">Your summary</p>
          </div>

          <div class="space-y-3">
            <article
              v-for="card in insightCards"
              :key="card.title"
              class="rounded-md border border-border p-3"
            >
              <p class="text-xs uppercase tracking-wide text-muted-foreground">
                {{ card.title }}
              </p>
              <p class="mt-1 text-sm font-semibold text-card-foreground">
                {{ card.value }}
              </p>
              <p class="mt-1 text-xs text-muted-foreground">
                {{ card.description }}
              </p>
            </article>
          </div>
        </div>
      </section>

      <section class="grid gap-4 xl:grid-cols-[minmax(0,1.6fr)_minmax(320px,0.9fr)]">
        <LineChart
          title="Daily XP claimed"
          description="Your XP history for the selected period"
          :labels="dailyLabels"
          :values="dailyXpValues"
          :secondary-values="dailyClaimValues"
          series-name="XP"
          tooltip-value-label="XP claimed"
          secondary-label="Claims"
          color="--chart-2"
          :loading="loading"
        >
          <template #icon>
            <Clock3 :size="18" class="text-muted-foreground" />
          </template>
        </LineChart>

        <DonutChart
          title="Your XP by source"
          description="Where your claimed XP comes from"
          :slices="sourceSlices"
          value-label="XP"
          :loading="loading"
          empty-text="No XP events yet"
        />
      </section>

      <HeatmapChart
        title="Your activity heatmap"
        description="Your XP claims by weekday and hour"
        :hours="activityHeatmap.hours"
        :days="activityHeatmap.days"
        :data="activityHeatmap.data"
        :max="activityHeatmap.max"
        value-label="Claims"
        :loading="loading"
        :empty="!activityHeatmapHasData"
        empty-text="No hourly XP activity yet"
      >
        <template #icon>
          <Clock3 :size="18" class="text-primary" />
        </template>
      </HeatmapChart>

      <section class="rounded-lg border border-border bg-card text-card-foreground shadow-sm">
        <div class="border-b border-border px-5 py-4">
          <h3 class="text-base font-semibold text-card-foreground">
            Your recent XP history
          </h3>
          <p class="text-sm text-muted-foreground">
            Latest claims stored for your account
          </p>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="bg-muted/60 text-xs uppercase tracking-wide text-muted-foreground">
              <tr>
                <th class="px-5 py-3 font-semibold">Time</th>
                <th class="px-5 py-3 font-semibold">Source</th>
                <th class="px-5 py-3 font-semibold">Detail</th>
                <th class="px-5 py-3 text-right font-semibold">XP</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border">
              <tr v-if="loading">
                <td colspan="4" class="px-5 py-8 text-center text-muted-foreground">
                  Loading XP history...
                </td>
              </tr>
              <tr v-else-if="recentEvents.length === 0">
                <td colspan="4" class="px-5 py-8 text-center text-muted-foreground">
                  No XP claims yet.
                </td>
              </tr>
              <tr
                v-for="event in recentEvents"
                v-else
                :key="event.id"
                class="text-card-foreground"
              >
                <td class="px-5 py-3 text-muted-foreground">
                  {{ formatEventTime(event.earned_at) }}
                </td>
                <td class="px-5 py-3 font-medium">{{ event.label }}</td>
                <td class="px-5 py-3">{{ eventDetail(event) }}</td>
                <td class="px-5 py-3 text-right font-bold text-primary">
                  +{{ formatNumber(event.amount) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </section>

    <section v-if="isGlobalDashboard" class="space-y-4">
      <div class="flex flex-col justify-between gap-3 md:flex-row md:items-end">
        <div>
          <p class="text-sm font-semibold text-primary">{{ pageEyebrow }}</p>
          <h3 class="mt-1 text-xl font-bold text-foreground">
            What is happening across the platform
          </h3>
          <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
            Platform-wide mode usage, learner distribution, and completed math
            levels.
          </p>
        </div>
        <div class="flex items-center gap-2 rounded-md border border-border bg-card px-3 py-2 text-sm text-card-foreground shadow-sm">
          <Globe2 :size="16" class="text-primary" />
          <span>Global view</span>
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article
          v-for="card in globalInsightCards"
          :key="card.title"
          class="rounded-lg border border-border bg-card p-4 text-card-foreground shadow-sm"
        >
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-medium text-muted-foreground">
              {{ card.title }}
            </p>
            <Users :size="18" class="text-primary" />
          </div>
          <p class="mt-3 text-2xl font-bold text-card-foreground">
            {{ card.value }}
          </p>
          <p class="mt-1 text-xs text-muted-foreground">
            {{ card.description }}
          </p>
        </article>
      </div>

      <section class="grid gap-4 xl:grid-cols-2">
        <BarChart
          title="Popular game modes"
          description="Global claim volume by game mode"
          :labels="popularModeLabels"
          :values="popularModeClaimValues"
          :secondary-values="popularModeXpValues"
          series-name="Claims"
          tooltip-value-label="Claims"
          secondary-label="XP"
          color="--chart-5"
          :loading="loading"
        />

        <BarChart
          title="Users by XP level"
          description="Global profile distribution across XP levels"
          :labels="userLevelLabels"
          :values="userLevelUserValues"
          :secondary-values="userLevelXpValues"
          series-name="Users"
          tooltip-value-label="Users"
          secondary-label="Combined XP"
          color="--chart-4"
          :loading="loading"
        />

        <LineChart
          title="Completed math levels"
          description="How many users finished each level"
          :labels="completedLevelLabels"
          :values="completedLevelUserValues"
          series-name="Users"
          tooltip-value-label="Completed users"
          color="--chart-3"
          :label-interval="0"
          :label-width="90"
          :loading="loading"
          card-class="xl:col-span-2"
        />
      </section>
    </section>
  </div>
</template>
