<script setup>
import { LineChart as EchartsLineChart } from "echarts/charts";
import { GridComponent, TooltipComponent } from "echarts/components";
import { use } from "echarts/core";
import { CanvasRenderer } from "echarts/renderers";
import { useTheme } from "@/composables/useTheme";
import { themeColor, themeColorAlpha } from "@/lib/themeColors";
import { computed } from "vue";
import VChart from "vue-echarts";

use([CanvasRenderer, EchartsLineChart, GridComponent, TooltipComponent]);

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    default: "",
  },
  labels: {
    type: Array,
    default: () => [],
  },
  values: {
    type: Array,
    default: () => [],
  },
  seriesName: {
    type: String,
    default: "Value",
  },
  tooltipValueLabel: {
    type: String,
    default: "Value",
  },
  secondaryValues: {
    type: Array,
    default: () => [],
  },
  secondaryLabel: {
    type: String,
    default: "",
  },
  color: {
    type: String,
    default: "--chart-1",
  },
  areaColor: {
    type: String,
    default: "",
  },
  smooth: {
    type: Boolean,
    default: true,
  },
  step: {
    type: Boolean,
    default: false,
  },
  binary: {
    type: Boolean,
    default: false,
  },
  activeLabel: {
    type: String,
    default: "Active",
  },
  inactiveLabel: {
    type: String,
    default: "Inactive",
  },
  labelInterval: {
    type: [Number, String],
    default: "auto",
  },
  labelWidth: {
    type: Number,
    default: 0,
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
  size: {
    type: String,
    default: "md",
  },
  cardClass: {
    type: String,
    default: "",
  },
});

const frameClass = computed(() => ({
  "chart-frame--sm": props.size === "sm",
  "chart-frame--lg": props.size === "lg",
}));

const showEmpty = computed(
  () => props.empty || props.labels.length === 0 || props.values.length === 0,
);

const { theme } = useTheme();

const option = computed(() => {
  theme.value;

  const color = themeColor(props.color);
  const axisColor = themeColor("--muted-foreground");
  const borderColor = themeColor("--border");
  const areaColor =
    props.areaColor || themeColorAlpha(props.color, 0.14);

  return {
    color: [color],
    tooltip: {
      trigger: "axis",
      axisPointer: { type: "line" },
      formatter: (items) => {
        const item = Array.isArray(items) ? items[0] : items;
        if (!item) {
          return "";
        }

        const index = item.dataIndex ?? 0;
        const label = props.labels[index] ?? "";
        const value = Number(props.values[index] ?? 0);
        const displayValue = props.binary
          ? value > 0
            ? props.activeLabel
            : props.inactiveLabel
          : formatNumber(value);

        let content = `${label}<br/>${props.tooltipValueLabel}: <strong>${displayValue}</strong>`;
        if (
          props.secondaryLabel &&
          props.secondaryValues[index] !== undefined
        ) {
          content += `<br/>${props.secondaryLabel}: ${formatNumber(props.secondaryValues[index])}`;
        }

        return content;
      },
    },
    grid: {
      left: 42,
      right: 18,
      top: 20,
      bottom: props.labelWidth > 0 ? 58 : 34,
    },
    xAxis: {
      type: "category",
      data: props.labels,
      axisTick: { show: false },
      axisLine: { lineStyle: { color: borderColor } },
      axisLabel: {
        color: axisColor,
        interval: props.labelInterval,
        overflow: props.labelWidth > 0 ? "truncate" : undefined,
        width: props.labelWidth || undefined,
      },
    },
    yAxis: {
      type: "value",
      min: 0,
      max: props.binary ? 1 : undefined,
      interval: props.binary ? 1 : undefined,
      minInterval: props.binary ? undefined : 1,
      axisLabel: {
        color: axisColor,
        formatter: props.binary
          ? (value) => (value > 0 ? "On" : "Off")
          : undefined,
      },
      splitLine: { lineStyle: { color: borderColor } },
    },
    series: [
      {
        name: props.seriesName,
        type: "line",
        smooth: props.step ? false : props.smooth,
        step: props.step ? "middle" : false,
        symbolSize: 7,
        data: props.values,
        lineStyle: { width: 3, color },
        itemStyle: { color },
        areaStyle: areaColor ? { color: areaColor } : undefined,
      },
    ],
  };
});

function formatNumber(value) {
  return new Intl.NumberFormat().format(Number(value) || 0);
}
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

    <div class="chart-frame" :class="frameClass">
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
  height: 18rem;
  min-height: 18rem;
  width: 100%;
  min-width: 0;
}

.chart-frame--sm {
  height: 14rem;
  min-height: 14rem;
}

.chart-frame--lg {
  height: 20rem;
  min-height: 20rem;
}

.chart-fill {
  display: block;
  width: 100%;
  height: 100%;
  min-height: 0;
}
</style>
