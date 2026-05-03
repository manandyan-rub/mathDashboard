<script setup>
import BarChart from "@/charts/BarChart.vue";
import DonutChart from "@/charts/DonutChart.vue";
import HeatmapChart from "@/charts/HeatmapChart.vue";
import LineChart from "@/charts/LineChart.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head } from "@inertiajs/vue3";
import {
  Award,
  BarChart3,
  Clock3,
  Coins,
  History,
} from "lucide-vue-next";
import { onMounted } from "vue";
import { useDashboardAnalytics } from "./Dashboard/useDashboardAnalytics";

defineOptions({
  layout: AppLayout,
});

const {
  loading,
  error,
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
  loadAnalytics,
  formatNumber,
  formatEventTime,
  eventDetail,
} = useDashboardAnalytics();

onMounted(loadAnalytics);
</script>

<template>
  <Head title="Dashboard" />

  <div class="mx-auto max-w-7xl space-y-6">
    <section class="flex flex-col gap-2">
      <p class="text-sm font-medium text-primary">
        Learning analytics
      </p>
      <div class="flex flex-col justify-between gap-3 md:flex-row md:items-end">
        <div>
          <h2 class="text-2xl font-bold text-foreground">
            XP performance dashboard
          </h2>
          <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
            Track earned XP, claim patterns, and the activity sources behind
            learning progress.
          </p>
        </div>

        <button
          type="button"
          class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-border bg-card px-3 text-sm font-semibold text-card-foreground transition hover:border-ring hover:bg-accent hover:text-accent-foreground"
          @click="loadAnalytics"
        >
          <History :size="16" />
          Refresh
        </button>
      </div>
    </section>

    <div
      v-if="error"
      class="rounded-md border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
    >
      {{ error }}
    </div>

    <section class="space-y-3">
      <div class="flex items-center justify-between">
        <h3 class="text-base font-semibold text-foreground">
          KPI
        </h3>
        <p class="text-xs text-muted-foreground">
          Core performance indicators
        </p>
      </div>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-lg border border-border bg-card p-4 text-card-foreground shadow-sm">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-muted-foreground">
              Total XP
            </p>
            <Coins :size="18" class="text-primary" />
          </div>
          <p class="mt-3 text-3xl font-bold text-card-foreground">
            {{ formatNumber(totalXp) }}
          </p>
          <p class="mt-1 text-xs text-muted-foreground">
            Lifetime claimed XP
          </p>
        </div>

        <div class="rounded-lg border border-border bg-card p-4 text-card-foreground shadow-sm">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-muted-foreground">
              14-Day XP
            </p>
            <BarChart3 :size="18" class="text-primary" />
          </div>
          <p class="mt-3 text-3xl font-bold text-card-foreground">
            {{ formatNumber(periodXp) }}
          </p>
          <p class="mt-1 text-xs text-muted-foreground">
            XP claimed in last 14 days
          </p>
        </div>

        <div class="rounded-lg border border-border bg-card p-4 text-card-foreground shadow-sm">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-muted-foreground">
              14-Day Claims
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
              Best Day XP
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
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(320px,1fr)_minmax(320px,1fr)]">
      <div class="rounded-lg border border-border bg-card p-5 text-card-foreground shadow-sm">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="text-base font-semibold text-card-foreground">
            Funnel
          </h3>
          <p class="text-xs text-muted-foreground">Claims flow</p>
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
          <p class="text-xs text-muted-foreground">Auto summary</p>
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
        description="XP history from the last two weeks"
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
        title="XP by source"
        description="Where claimed XP comes from"
        :slices="sourceSlices"
        value-label="XP"
        :loading="loading"
        empty-text="No XP events yet"
      />
    </section>

    <HeatmapChart
      title="Activity heatmap"
      description="XP claims by weekday and hour"
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

    <section class="grid gap-4 xl:grid-cols-2">
      <BarChart
        title="Popular game modes"
        description="Claim volume by game mode"
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
        description="Profile distribution across XP levels"
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

    <section class="rounded-lg border border-border bg-card text-card-foreground shadow-sm">
      <div class="border-b border-border px-5 py-4">
        <h3 class="text-base font-semibold text-card-foreground">
          Recent XP history
        </h3>
        <p class="text-sm text-muted-foreground">
          Latest claims stored in the XP event table
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
  </div>
</template>
