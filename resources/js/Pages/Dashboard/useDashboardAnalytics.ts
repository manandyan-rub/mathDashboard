import axios from "axios";
import { computed, ref } from "vue";
import type { DateRange } from "reka-ui";
import type {
  ActivityHeatmap,
  FunnelStep,
  InsightCard,
  XpAnalytics,
  XpEvent,
  XpSource,
} from "@/types/charts";

export function useDashboardAnalytics() {
  const loading = ref(true);
  const error = ref<string | null>(null);
  const analytics = ref<XpAnalytics | null>(null);
  const dateRange = ref<DateRange | null>(null);

  const dailyPoints = computed(() => analytics.value?.daily ?? []);
  const sourcePoints = computed(() => analytics.value?.sources ?? []);
  const popularModePoints = computed(
    () => analytics.value?.popular_modes ?? [],
  );
  const userLevelPoints = computed(
    () => analytics.value?.user_level_distribution ?? [],
  );
  const completedLevelPoints = computed(
    () => analytics.value?.completed_level_distribution ?? [],
  );
  const activityHeatmap = computed<ActivityHeatmap>(
    () =>
      analytics.value?.activity_heatmap ?? {
        hours: [],
        days: [],
        data: [],
        max: 0,
      },
  );
  const recentEvents = computed(() => analytics.value?.recent ?? []);
  const loadedPeriod = computed(() => analytics.value?.period ?? null);
  const rangeStart = computed(() => datePartToString(dateRange.value?.start));
  const rangeEnd = computed(() => datePartToString(dateRange.value?.end));
  const dateRangeLabel = computed(() => {
    const start = loadedPeriod.value?.start ?? rangeStart.value;
    const end = loadedPeriod.value?.end ?? rangeEnd.value;

    if (!start && !end) {
      return "last 14 days";
    }

    if (start && end) {
      return start === end ? start : `${start} - ${end}`;
    }

    if (start) {
      return `${start} - now`;
    }

    return `up to ${end}`;
  });
  const dailyLabels = computed(() =>
    dailyPoints.value.map((point) => point.label),
  );
  const dailyXpValues = computed(() =>
    dailyPoints.value.map((point) => point.xp),
  );
  const dailyClaimValues = computed(() =>
    dailyPoints.value.map((point) => point.claims),
  );
  const retentionValues = computed(() =>
    dailyPoints.value.map((point) => (point.claims > 0 ? 1 : 0)),
  );
  const sourceSlices = computed(() =>
    sourcePoints.value.map((source) => ({
      name: source.label,
      value: source.xp,
    })),
  );
  const popularModeLabels = computed(() =>
    popularModePoints.value.map((point) => point.label),
  );
  const popularModeClaimValues = computed(() =>
    popularModePoints.value.map((point) => point.claims),
  );
  const popularModeXpValues = computed(() =>
    popularModePoints.value.map((point) => point.xp),
  );
  const userLevelLabels = computed(() =>
    userLevelPoints.value.map((point) => point.label),
  );
  const userLevelUserValues = computed(() =>
    userLevelPoints.value.map((point) => point.users),
  );
  const userLevelXpValues = computed(() =>
    userLevelPoints.value.map((point) => point.total_xp),
  );
  const completedLevelLabels = computed(() =>
    completedLevelPoints.value.map((point) => point.label),
  );
  const completedLevelUserValues = computed(() =>
    completedLevelPoints.value.map((point) => point.users),
  );
  const activityHeatmapHasData = computed(() =>
    activityHeatmap.value.data.some((point) => point[2] > 0),
  );

  const totalXp = computed(() => analytics.value?.profile.total_xp ?? 0);
  const periodXp = computed(() => analytics.value?.summary.period_xp ?? 0);
  const periodClaims = computed(
    () => analytics.value?.summary.period_claims ?? 0,
  );
  const bestDayXp = computed(
    () => analytics.value?.summary.best_day_xp ?? 0,
  );
  const activeDays = computed(
    () => dailyPoints.value.filter((point) => point.claims > 0).length,
  );
  const retentionRate = computed(() => {
    const totalDays = Math.max(1, dailyPoints.value.length);
    return Math.round((activeDays.value / totalDays) * 100);
  });
  const previousWeekXp = computed(() =>
    dailyPoints.value
      .slice(0, 7)
      .reduce((sum, point) => sum + Math.max(0, point.xp), 0),
  );
  const currentWeekXp = computed(() =>
    dailyPoints.value
      .slice(-7)
      .reduce((sum, point) => sum + Math.max(0, point.xp), 0),
  );
  const weeklyGrowthPercent = computed(() => {
    const previous = previousWeekXp.value;
    const current = currentWeekXp.value;
    if (previous <= 0) {
      return current > 0 ? 100 : 0;
    }

    return Math.round(((current - previous) / previous) * 100);
  });

  const sourceByKey = computed(() => {
    const map = new Map<string, XpSource>();
    sourcePoints.value.forEach((source) => map.set(source.source, source));
    return map;
  });
  const totalClaimsAllTime = computed(() =>
    sourcePoints.value.reduce(
      (sum, source) => sum + Math.max(0, source.claims),
      0,
    ),
  );
  const gameClaims = computed(
    () => sourceByKey.value.get("game")?.claims ?? 0,
  );
  const mathLevelClaims = computed(
    () => sourceByKey.value.get("math_level")?.claims ?? 0,
  );
  const einsteinClaims = computed(
    () =>
      popularModePoints.value.find(
        (mode) => mode.mode.toLowerCase() === "einstein",
      )?.claims ?? 0,
  );

  const funnelSteps = computed<FunnelStep[]>(() => {
    const raw = [
      { label: "All XP claims", value: totalClaimsAllTime.value },
      { label: "Game claims", value: gameClaims.value },
      { label: "Math level claims", value: mathLevelClaims.value },
      { label: "Einstein claims", value: einsteinClaims.value },
    ];

    let previous = Number.POSITIVE_INFINITY;
    const normalized = raw.map((step) => {
      const clamped = Math.max(0, Math.min(step.value, previous));
      previous = clamped;
      return { ...step, value: clamped };
    });

    const maxValue = Math.max(1, normalized[0]?.value ?? 1);
    return normalized.map((step) => ({
      ...step,
      widthPercent: Math.max(12, Math.round((step.value / maxValue) * 100)),
    }));
  });

  const topSourceLabel = computed(
    () => sourcePoints.value[0]?.label ?? "No source yet",
  );
  const topSourceXp = computed(() => sourcePoints.value[0]?.xp ?? 0);
  const topModeLabel = computed(
    () => popularModePoints.value[0]?.label ?? "No mode yet",
  );
  const topModeClaims = computed(
    () => popularModePoints.value[0]?.claims ?? 0,
  );
  const globalLearners = computed(() =>
    userLevelPoints.value.reduce(
      (sum, point) => sum + Math.max(0, point.users),
      0,
    ),
  );
  const globalXp = computed(() =>
    userLevelPoints.value.reduce(
      (sum, point) => sum + Math.max(0, point.total_xp),
      0,
    ),
  );
  const globalGameClaims = computed(() =>
    popularModePoints.value.reduce(
      (sum, point) => sum + Math.max(0, point.claims),
      0,
    ),
  );
  const globalLevelCompletions = computed(() =>
    completedLevelPoints.value.reduce(
      (sum, point) => sum + Math.max(0, point.users),
      0,
    ),
  );

  const insightCards = computed<InsightCard[]>(() => [
    {
      title: "Momentum",
      value: `${weeklyGrowthPercent.value >= 0 ? "+" : ""}${weeklyGrowthPercent.value}%`,
      description: "Week-over-week XP growth in the selected period.",
    },
    {
      title: "Retention",
      value: `${retentionRate.value}%`,
      description: "Days with at least one XP claim in the observed period.",
    },
    {
      title: "Best Source",
      value: `${topSourceLabel.value} (${formatNumber(topSourceXp.value)} XP)`,
      description: "Most impactful source by total earned XP.",
    },
    {
      title: "Most Used Mode",
      value: `${topModeLabel.value} (${formatNumber(topModeClaims.value)} claims)`,
      description: "Game mode used most frequently for XP.",
    },
  ]);
  const globalInsightCards = computed<InsightCard[]>(() => [
    {
      title: "Learners",
      value: formatNumber(globalLearners.value),
      description: "Profiles currently represented in XP levels.",
    },
    {
      title: "Global XP",
      value: formatNumber(globalXp.value),
      description: "Combined XP across all learner profiles.",
    },
    {
      title: "Game Claims",
      value: formatNumber(globalGameClaims.value),
      description: "All game XP claim events by mode.",
    },
    {
      title: "Level Completions",
      value: formatNumber(globalLevelCompletions.value),
      description: "Completed math level records across the platform.",
    },
  ]);

  async function loadAnalytics() {
    loading.value = true;
    error.value = null;

    try {
      const params = {
        start: rangeStart.value || undefined,
        end: rangeEnd.value || undefined,
      };
      const response = await axios.get<XpAnalytics>(
        "/api/dashboard/xp-analytics",
        { params },
      );
      analytics.value = response.data;
    } catch {
      error.value = "Could not load XP analytics.";
    } finally {
      loading.value = false;
    }
  }

  return {
    loading,
    error,
    dateRange,
    dateRangeLabel,
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
    globalLearners,
    globalXp,
    globalGameClaims,
    globalLevelCompletions,
    loadAnalytics,
    formatNumber,
    formatEventTime,
    eventDetail,
  };
}

function datePartToString(value: unknown): string {
  if (!value) {
    return "";
  }

  if (typeof value === "string") {
    return value.slice(0, 10);
  }

  if (typeof value === "object" && value !== null && "toString" in value) {
    return String(value).slice(0, 10);
  }

  return String(value).slice(0, 10);
}

function formatNumber(value: number): string {
  return new Intl.NumberFormat().format(value);
}

function formatEventTime(value: string | null): string {
  if (!value) {
    return "Unknown";
  }

  return new Intl.DateTimeFormat(undefined, {
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(value));
}

function eventDetail(event: XpEvent): string {
  if (event.source === "game") {
    const game = String(event.metadata.game ?? "game");
    const mode = String(event.metadata.mode ?? "practice");
    return `${game} / ${mode}`;
  }

  if (event.source === "math_level") {
    const level = String(event.metadata.level_slug ?? "level");
    const score = Number(event.metadata.score ?? 0);
    return `${level} / ${score}%`;
  }

  return event.source;
}
