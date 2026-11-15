<script setup>
import BarChart from "@/charts/BarChart.vue";
import CountryMapChart from "@/charts/CountryMapChart.vue";
import DonutChart from "@/charts/DonutChart.vue";
import HeatmapChart from "@/charts/HeatmapChart.vue";
import LineChart from "@/charts/LineChart.vue";
import AnimatedNumber from "@/components/motion/AnimatedNumber.vue";
import FormCalendarRange from "@/Forms/FormCalendarRange.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { cmsTranslate as __ } from "@/lib/i18n";
import { Head, usePage } from "@inertiajs/vue3";
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

const page = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);
const tenant = computed(() => page.props.auth?.tenant ?? null);
const aggregateScopeCopy = computed(() => {
  if (authUser.value?.role === "admin") {
    return {
      title: __("Platform dashboard"),
      eyebrow: __("Platform activity"),
      heading: __("What is happening across the platform"),
      description: __(
        "Platform-wide mode usage, learner distribution, and completed math levels.",
      ),
      badge: __("Platform view"),
    };
  }

  if (authUser.value?.role === "lecturer") {
    return {
      title: __("Class dashboard"),
      eyebrow: __("Class activity"),
      heading: __("What is happening in your classes"),
      description: __(
        "Mode usage, learner distribution, and completed math levels across your assigned classes.",
      ),
      badge: __("Class view"),
    };
  }

  if (tenant.value) {
    return {
      title: __("Organization dashboard"),
      eyebrow: __("Organization activity"),
      heading: __("What is happening in your organization"),
      description: __(
        "Mode usage, learner distribution, and completed math levels across your organization.",
      ),
      badge: __("Organization view"),
    };
  }

  return {
    title: __("Community dashboard"),
    eyebrow: __("Community activity"),
    heading: __("What is happening in your community"),
    description: __(
      "Mode usage, learner distribution, and completed math levels across unassigned learners.",
    ),
    badge: __("Community view"),
  };
});

const isGlobalDashboard = computed(() => props.scope === "global");
const isLocalDashboard = computed(() => !isGlobalDashboard.value);
const pageTitle = computed(() =>
  isGlobalDashboard.value
    ? aggregateScopeCopy.value.title
    : __("Local dashboard"),
);
const pageEyebrow = computed(() =>
  isGlobalDashboard.value
    ? aggregateScopeCopy.value.eyebrow
    : __("Your stats"),
);
const pageHeading = computed(() =>
  isGlobalDashboard.value
    ? aggregateScopeCopy.value.heading
    : __("Your learning data"),
);
const pageDescription = computed(() =>
  isGlobalDashboard.value
    ? aggregateScopeCopy.value.description
    : __("XP, streak behavior, source mix, and the most recent claims from your account."),
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
  countryDistribution,
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

const localStats = computed(() => [
  {
    label: __("Total XP"),
    hint: __("Lifetime claimed XP"),
    value: totalXp.value,
    icon: Coins,
  },
  {
    label: __("Period XP"),
    hint: __("XP claimed in the selected period"),
    value: periodXp.value,
    icon: BarChart3,
  },
  {
    label: __("Period claims"),
    hint: __("Number of XP claim events"),
    value: periodClaims.value,
    icon: Clock3,
  },
  {
    label: __("Best day XP"),
    hint: __("Highest single-day XP in this period"),
    value: bestDayXp.value,
    icon: Award,
  },
]);

const heroGlyphs = [
  { symbol: "∫", style: { top: "8%", right: "6%", fontSize: "5.5rem", "--dur": "11s", "--dx": "-18px", "--dy": "16px" } },
  { symbol: "π", style: { bottom: "10%", right: "28%", fontSize: "3.5rem", "--dur": "8s", "--delay": "-3s" } },
  { symbol: "Σ", style: { top: "14%", right: "38%", fontSize: "2.75rem", "--dur": "10s", "--delay": "-5s", "--dx": "20px" } },
  { symbol: "√x", style: { bottom: "6%", right: "4%", fontSize: "2.5rem", "--dur": "9s", "--delay": "-2s", "--dy": "-14px" } },
  { symbol: "∞", style: { top: "44%", right: "18%", fontSize: "2rem", "--dur": "12s", "--delay": "-7s" } },
];

onMounted(loadAnalytics);
</script>

<template>
  <Head :title="pageTitle" />

  <div class="mx-auto max-w-7xl space-y-6">
    <section
      class="aurora flex flex-col gap-2 rounded-2xl border border-border bg-card/60 p-6 backdrop-blur-sm md:p-8"
    >
      <span
        v-for="glyph in heroGlyphs"
        :key="glyph.symbol"
        class="float-glyph"
        :style="glyph.style"
        aria-hidden="true"
        >{{ glyph.symbol }}</span
      >

      <p
        v-reveal="'blur'"
        class="inline-flex items-center gap-2 text-sm font-medium text-primary"
      >
        <span class="pulse-dot" />
        {{ __("Learning analytics") }}
      </p>
      <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
          <h2
            v-reveal="{ variant: 'up', delay: 80 }"
            class="text-shine text-3xl font-extrabold tracking-tight md:text-4xl"
          >
            {{ pageHeading }}
          </h2>
          <p
            v-reveal="{ variant: 'up', delay: 160 }"
            class="mt-2 max-w-2xl text-sm text-muted-foreground"
          >
            {{ pageDescription }}
          </p>
        </div>

        <div
          v-reveal="{ variant: 'right', delay: 220 }"
          class="flex flex-col gap-2 sm:flex-row sm:items-end"
        >
          <FormCalendarRange
            v-model="dateRange"
            :placeholder="__('Filter chart range')"
            :number-of-months="2"
            class="sm:w-[310px]"
            trigger-class="h-10 bg-card text-card-foreground shadow-sm"
            @change="loadAnalytics"
          />

          <button
            v-magnetic="0.2"
            type="button"
            class="group inline-flex h-10 items-center justify-center gap-2 rounded-md border border-border bg-card px-3 text-sm font-semibold text-card-foreground hover:border-ring hover:bg-accent hover:text-accent-foreground"
            @click="loadAnalytics"
          >
            <History
              :size="16"
              class="transition-transform duration-700 group-hover:-rotate-[360deg]"
              :class="{ 'animate-spin': loading }"
            />
            {{ __("Refresh") }}
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
            {{ __("Your learning data") }}
          </h3>
          <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
            {{ __("XP, streak behavior, source mix, and the most recent claims from your account.") }}
          </p>
        </div>
        <div class="rounded-md border border-border bg-card px-3 py-2 text-sm text-card-foreground shadow-sm">
          <span class="text-muted-foreground">{{ __("Active days") }}</span>
          <span class="ml-2 font-bold text-primary">{{ retentionRate }}%</span>
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div
          v-for="(stat, index) in localStats"
          :key="stat.label"
          v-reveal="{ variant: 'up', delay: index * 90 }"
          v-tilt
          class="rounded-xl border border-border bg-card p-4 text-card-foreground shadow-sm"
        >
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-muted-foreground">{{ stat.label }}</p>
            <span
              class="motion-pop flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary"
            >
              <component :is="stat.icon" :size="18" />
            </span>
          </div>
          <p class="mt-3 text-3xl font-bold text-card-foreground">
            <AnimatedNumber :value="stat.value" :format="formatNumber" />
          </p>
          <p class="mt-1 text-xs text-muted-foreground">{{ stat.hint }}</p>
        </div>
      </div>

      <section class="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(320px,1fr)_minmax(320px,1fr)]">
        <div
          v-reveal="{ variant: 'up', delay: 0 }"
          v-tilt="3"
          class="rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm"
        >
          <div class="mb-4 flex items-center justify-between">
            <h3 class="text-base font-semibold text-card-foreground">
              {{ __("Claim mix") }}
            </h3>
            <p class="text-xs text-muted-foreground">{{ __("Your flow") }}</p>
          </div>

          <div class="space-y-3">
            <div
              v-for="(step, index) in funnelSteps"
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
                  :key="`${step.label}-${step.value}`"
                  class="bar-grow h-3 rounded-full bg-gradient-to-r from-primary via-chart-4 to-chart-2"
                  :style="{ width: `${step.widthPercent}%`, '--bar-delay': `${index * 120}ms` }"
                />
              </div>
            </div>
          </div>
        </div>

        <LineChart
          v-reveal="{ variant: 'up', delay: 100 }"
          :title="__('Retention')"
          :description="__('Active days :rate%', { rate: String(retentionRate) })"
          :labels="dailyLabels"
          :values="retentionValues"
          :secondary-values="dailyClaimValues"
          :series-name="__('Retention')"
          :tooltip-value-label="__('Status')"
          :secondary-label="__('Claims')"
          color="--chart-4"
          :binary="true"
          :step="true"
          size="sm"
          :loading="loading"
        />

        <div
          v-reveal="{ variant: 'up', delay: 200 }"
          class="rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm"
        >
          <div class="mb-4 flex items-center justify-between">
            <h3 class="text-base font-semibold text-card-foreground">
              {{ __("Insights") }}
            </h3>
            <p class="text-xs text-muted-foreground">{{ __("Your summary") }}</p>
          </div>

          <div class="space-y-3">
            <article
              v-for="(card, index) in insightCards"
              :key="card.title"
              v-reveal="{ variant: 'right', delay: 260 + index * 90 }"
              class="rounded-md border border-border p-3 transition duration-300 hover:-translate-y-0.5 hover:border-primary/40 hover:bg-primary/5"
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
          v-reveal="'up'"
          :title="__('Daily XP claimed')"
          :description="__('Your XP history for the selected period')"
          :labels="dailyLabels"
          :values="dailyXpValues"
          :secondary-values="dailyClaimValues"
          series-name="XP"
          :tooltip-value-label="__('XP claimed')"
          :secondary-label="__('Claims')"
          color="--chart-2"
          :loading="loading"
        >
          <template #icon>
            <Clock3 :size="18" class="text-muted-foreground" />
          </template>
        </LineChart>

        <DonutChart
          v-reveal="{ variant: 'scale', delay: 120 }"
          :title="__('Your XP by source')"
          :description="__('Where your claimed XP comes from')"
          :slices="sourceSlices"
          value-label="XP"
          :loading="loading"
          :empty-text="__('No XP events yet')"
        />
      </section>

      <HeatmapChart
        v-reveal="'blur'"
        :title="__('Your activity heatmap')"
        :description="__('Your XP claims by weekday and hour')"
        :hours="activityHeatmap.hours"
        :days="activityHeatmap.days"
        :data="activityHeatmap.data"
        :max="activityHeatmap.max"
        :value-label="__('Claims')"
        :loading="loading"
        :empty="!activityHeatmapHasData"
        :empty-text="__('No hourly XP activity yet')"
      >
        <template #icon>
          <Clock3 :size="18" class="text-primary" />
        </template>
      </HeatmapChart>

      <section
        v-reveal="'up'"
        class="rounded-xl border border-border bg-card text-card-foreground shadow-sm"
      >
        <div class="border-b border-border px-5 py-4">
          <h3 class="text-base font-semibold text-card-foreground">
            {{ __("Your recent XP history") }}
          </h3>
          <p class="text-sm text-muted-foreground">
            {{ __("Latest claims stored for your account") }}
          </p>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="bg-muted/60 text-xs uppercase tracking-wide text-muted-foreground">
              <tr>
                <th class="px-5 py-3 font-semibold">{{ __("Time") }}</th>
                <th class="px-5 py-3 font-semibold">{{ __("Source") }}</th>
                <th class="px-5 py-3 font-semibold">{{ __("Detail") }}</th>
                <th class="px-5 py-3 text-right font-semibold">XP</th>
              </tr>
            </thead>
            <TransitionGroup tag="tbody" name="list" class="divide-y divide-border">
              <tr v-if="loading" key="loading">
                <td colspan="4" class="px-5 py-8 text-center text-muted-foreground">
                  {{ __("Loading XP history...") }}
                </td>
              </tr>
              <tr v-else-if="recentEvents.length === 0" key="empty">
                <td colspan="4" class="px-5 py-8 text-center text-muted-foreground">
                  {{ __("No XP claims yet.") }}
                </td>
              </tr>
              <tr
                v-for="(event, index) in recentEvents"
                v-else
                :key="event.id"
                class="text-card-foreground transition-colors hover:bg-primary/5"
                :style="{ '--i-delay': `${Math.min(index, 12) * 45}ms` }"
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
            </TransitionGroup>
          </table>
        </div>
      </section>
    </section>

    <section v-if="isGlobalDashboard" class="space-y-4">
      <div class="flex flex-col justify-between gap-3 md:flex-row md:items-end">
        <div>
          <p class="text-sm font-semibold text-primary">{{ pageEyebrow }}</p>
          <h3 class="mt-1 text-xl font-bold text-foreground">
            {{ pageHeading }}
          </h3>
          <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
            {{ pageDescription }}
          </p>
        </div>
        <div class="flex items-center gap-2 rounded-md border border-border bg-card px-3 py-2 text-sm text-card-foreground shadow-sm">
          <Globe2 :size="16" class="text-primary" />
          <span>{{ aggregateScopeCopy.badge }}</span>
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article
          v-for="(card, index) in globalInsightCards"
          :key="card.title"
          v-reveal="{ variant: 'up', delay: index * 90 }"
          v-tilt
          class="rounded-xl border border-border bg-card p-4 text-card-foreground shadow-sm"
        >
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-medium text-muted-foreground">
              {{ card.title }}
            </p>
            <span
              class="motion-pop flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary"
            >
              <Users :size="18" />
            </span>
          </div>
          <p class="mt-3 text-2xl font-bold text-card-foreground">
            {{ card.value }}
          </p>
          <p class="mt-1 text-xs text-muted-foreground">
            {{ card.description }}
          </p>
        </article>
      </div>

      <CountryMapChart
        v-reveal="'blur'"
        :title="__('Learner map')"
        :description="__('Active learners by country in the selected period')"
        :points="countryDistribution"
        :users-label="__('Learners')"
        :claims-label="__('Claims')"
        :xp-label="__('Combined XP')"
        :loading="loading"
        :empty-text="__('No country activity yet')"
      >
        <template #icon>
          <Globe2 :size="18" class="text-primary" />
        </template>
      </CountryMapChart>

      <section class="grid gap-4 xl:grid-cols-2">
        <BarChart
          v-reveal="'left'"
          :title="__('Popular game modes')"
          :description="__('Visible claim volume by game mode')"
          :labels="popularModeLabels"
          :values="popularModeClaimValues"
          :secondary-values="popularModeXpValues"
          :series-name="__('Claims')"
          :tooltip-value-label="__('Claims')"
          secondary-label="XP"
          color="--chart-5"
          :loading="loading"
        />

        <BarChart
          v-reveal="{ variant: 'right', delay: 120 }"
          :title="__('Users by XP level')"
          :description="__('Visible profile distribution across XP levels')"
          :labels="userLevelLabels"
          :values="userLevelUserValues"
          :secondary-values="userLevelXpValues"
          :series-name="__('Users')"
          :tooltip-value-label="__('Users')"
          :secondary-label="__('Combined XP')"
          color="--chart-4"
          :loading="loading"
        />

        <LineChart
          v-reveal="'up'"
          :title="__('Completed math levels')"
          :description="__('How many users finished each level')"
          :labels="completedLevelLabels"
          :values="completedLevelUserValues"
          :series-name="__('Users')"
          :tooltip-value-label="__('Completed users')"
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
