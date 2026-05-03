<script setup>
import { PieChart } from "echarts/charts";
import { LegendComponent, TooltipComponent } from "echarts/components";
import { use } from "echarts/core";
import { CanvasRenderer } from "echarts/renderers";
import { useTheme } from "@/composables/useTheme";
import { themeColor, themePalette } from "@/lib/themeColors";
import { computed } from "vue";
import VChart from "vue-echarts";

use([CanvasRenderer, PieChart, LegendComponent, TooltipComponent]);

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    default: "",
  },
  slices: {
    type: Array,
    default: () => [],
  },
  valueLabel: {
    type: String,
    default: "Value",
  },
  colors: {
    type: Array,
    default: () => [
      "--chart-1",
      "--chart-2",
      "--chart-3",
      "--destructive",
      "--chart-4",
    ],
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
  () =>
    props.empty ||
    props.slices.length === 0 ||
    !props.slices.some((slice) => Number(slice.value) > 0),
);

const { theme } = useTheme();

const option = computed(() => {
  theme.value;

  return {
    color: themePalette(props.colors),
    tooltip: {
      trigger: "item",
      formatter: `{b}<br/>${props.valueLabel}: <strong>{c}</strong> ({d}%)`,
    },
    legend: {
      bottom: 0,
      icon: "circle",
      textStyle: { color: themeColor("--muted-foreground") },
    },
    series: [
      {
        name: props.title,
        type: "pie",
        radius: ["30%", "70%"],
        center: ["50%", "43%"],
        avoidLabelOverlap: true,
        label: { show: false },
        emphasis: {
          label: {
            show: true,
            fontSize: 13,
            fontWeight: "bold",
          },
        },
        data: props.slices,
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
        <p v-if="description" class="text-sm text-muted-foreground">
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
