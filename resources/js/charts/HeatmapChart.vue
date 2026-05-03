<script setup>
import { HeatmapChart as EchartsHeatmapChart } from "echarts/charts";
import {
  GridComponent,
  TooltipComponent,
  VisualMapComponent,
} from "echarts/components";
import { use } from "echarts/core";
import { CanvasRenderer } from "echarts/renderers";
import { useTheme } from "@/composables/useTheme";
import { themeColor, themeColorAlpha, themePalette } from "@/lib/themeColors";
import { computed } from "vue";
import VChart from "vue-echarts";

use([
  CanvasRenderer,
  EchartsHeatmapChart,
  GridComponent,
  TooltipComponent,
  VisualMapComponent,
]);

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    default: "",
  },
  hours: {
    type: Array,
    default: () => [],
  },
  days: {
    type: Array,
    default: () => [],
  },
  data: {
    type: Array,
    default: () => [],
  },
  max: {
    type: Number,
    default: 0,
  },
  valueLabel: {
    type: String,
    default: "Value",
  },
  colors: {
    type: Array,
    default: () => ["--muted", "--accent", "--chart-5", "--chart-1"],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  empty: {
    type: Boolean,
    default: false,
  },
  emptyText: {
    type: String,
    default: "No chart data yet",
  },
  cardClass: {
    type: String,
    default: "",
  },
});

const showEmpty = computed(
  () =>
    props.empty ||
    props.hours.length === 0 ||
    props.days.length === 0 ||
    props.data.length === 0,
);

const normalizedData = computed(() =>
  props.data.map(([hourIndex, dayIndex, value]) => [
    hourIndex,
    dayIndex,
    value || "-",
  ]),
);

const { theme } = useTheme();

const option = computed(() => {
  theme.value;

  const axisColor = themeColor("--muted-foreground");
  const borderColor = themeColor("--border");

  return {
    tooltip: {
      position: "top",
      formatter: (params) => {
        const [hourIndex, dayIndex, rawValue] = params.data;
        const value = Number(rawValue) || 0;
        const day = props.days[dayIndex] ?? "";
        const hour = props.hours[hourIndex] ?? "";

        return `${day} ${hour}<br/>${props.valueLabel}: <strong>${value}</strong>`;
      },
    },
    grid: {
      height: "58%",
      left: 90,
      right: 24,
      top: 18,
      bottom: 78,
    },
    xAxis: {
      type: "category",
      data: props.hours,
      axisTick: { show: false },
      axisLine: { lineStyle: { color: borderColor } },
      axisLabel: { color: axisColor, interval: 1 },
      splitArea: { show: true },
    },
    yAxis: {
      type: "category",
      data: props.days,
      axisTick: { show: false },
      axisLine: { lineStyle: { color: borderColor } },
      axisLabel: { color: axisColor },
      splitArea: { show: true },
    },
    visualMap: {
      min: 0,
      max: Math.max(1, props.max),
      calculable: true,
      orient: "horizontal",
      left: "center",
      bottom: 12,
      inRange: {
        color: themePalette(props.colors),
      },
      textStyle: { color: axisColor },
    },
    series: [
      {
        name: props.title,
        type: "heatmap",
        data: normalizedData.value,
        label: {
          show: true,
          color: themeColor("--card-foreground"),
          fontSize: 10,
          formatter: (params) => {
            const value = params.value[2];
            return value === "-" ? "" : String(value);
          },
        },
        emphasis: {
          itemStyle: {
            shadowBlur: 10,
            shadowColor: themeColorAlpha("--foreground", 0.28),
          },
        },
      },
    ],
  };
});
</script>

<template>
  <section
    class="rounded-lg border border-border bg-card p-5 text-card-foreground shadow-sm"
    :class="cardClass"
  >
    <div class="mb-4 flex items-center justify-between gap-4">
      <div>
        <h3 class="text-base font-semibold text-card-foreground">
          {{ title }}
        </h3>
        <p
          v-if="description"
          class="text-sm text-muted-foreground"
        >
          {{ description }}
        </p>
      </div>
      <slot name="icon" />
    </div>

    <div class="chart-frame">
      <div
        v-if="loading"
        class="chart-fill animate-pulse rounded-md bg-muted"
      />
      <div
        v-else-if="showEmpty"
        class="chart-fill flex items-center justify-center rounded-md border border-dashed border-border text-sm text-muted-foreground"
      >
        {{ emptyText }}
      </div>
      <VChart v-else class="chart-fill" :option="option" autoresize />
    </div>
  </section>
</template>

<style scoped>
.chart-frame {
  height: 22rem;
  min-height: 22rem;
  width: 100%;
  min-width: 0;
}

.chart-fill {
  display: block;
  width: 100%;
  height: 100%;
  min-height: 0;
}
</style>
