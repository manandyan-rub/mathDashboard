<script setup lang="ts">
import { computed, ref } from "vue";
import { Box, ChartLine } from "lucide-vue-next";
import LineChart from "@/charts/LineChart.vue";
import { cmsTranslate as __ } from "@/lib/i18n";
import TwoDGeometryPreview from "@/Pages/Mathematics/Partials/TwoDGeometryPreview.vue";
import type { MathWorkspaceState } from "@/types/mathWorkspace";
import type { TwoDInputs, TwoDShapeKey } from "@/types/twoDGeometry";

const props = defineProps<{
  state: MathWorkspaceState;
}>();

const mode = ref<"visual" | "graph">("visual");
const values = computed(() =>
  Object.fromEntries(
    [...props.state.userEnteredValues, ...props.state.calculatedValues].map(
      (value) => [value.key, value.value],
    ),
  ),
);
const isTwoD = computed(
  () => props.state.visualizationConfiguration?.kind === "geometry-2d",
);
const previewShape = computed<TwoDShapeKey>(() =>
  isTwoD.value
    ? (props.state.visualizationConfiguration?.shape as TwoDShapeKey)
    : "rectangle",
);
const previewInputs = computed<TwoDInputs>(() => ({
  rectangle: {
    length: values.value.length ?? null,
    width: values.value.width ?? null,
    area: values.value.area ?? null,
    perimeter: values.value.perimeter ?? null,
    diagonal: values.value.diagonal ?? null,
  },
  triangle: {
    sideA: values.value.sideA ?? null,
    sideB: values.value.sideB ?? null,
    sideC: values.value.sideC ?? null,
    angleA: values.value.angleA ?? null,
    angleB: values.value.angleB ?? null,
    angleC: values.value.angleC ?? null,
    base: values.value.base ?? null,
    height: values.value.height ?? null,
  },
  circle: {
    radius: values.value.radius ?? null,
    diameter: values.value.diameter ?? null,
    circumference: values.value.circumference ?? null,
    area: values.value.area ?? null,
  },
  trapezoid: {
    baseA: values.value.baseA ?? null,
    baseB: values.value.baseB ?? null,
    height: values.value.height ?? null,
    sideC: values.value.sideC ?? null,
    sideD: values.value.sideD ?? null,
  },
  parallelogram: {
    base: values.value.base ?? null,
    side: values.value.side ?? null,
    height: values.value.height ?? null,
    area: values.value.area ?? null,
    perimeter: values.value.perimeter ?? null,
    angle: values.value.angle ?? null,
    diagonalA: values.value.diagonalA ?? null,
    diagonalB: values.value.diagonalB ?? null,
  },
}));
</script>

<template>
  <section class="workspace-visualization">
    <div class="workspace-visualization-heading">
      <div>
        <p>{{ __("Visualization") }}</p>
        <h2>{{ __("Live geometry") }}</h2>
      </div>
      <div class="workspace-view-switch">
        <button
          type="button"
          :class="{ 'is-active': mode === 'visual' }"
          :aria-label="__('Geometry')"
          @click="mode = 'visual'"
        >
          <Box :size="16" />
        </button>
        <button
          type="button"
          :class="{ 'is-active': mode === 'graph' }"
          :disabled="!state.graphConfiguration"
          :aria-label="__('Graph')"
          @click="mode = 'graph'"
        >
          <ChartLine :size="16" />
        </button>
      </div>
    </div>

    <div v-if="mode === 'visual'" class="workspace-visual-frame">
      <TwoDGeometryPreview
        v-if="isTwoD"
        :shape="previewShape"
        :inputs="previewInputs"
        accent-color="--chart-1"
      />

      <svg
        v-else-if="state.visualizationConfiguration"
        class="workspace-cube"
        viewBox="0 0 520 320"
        role="img"
        :aria-label="__('Cube geometry preview')"
      >
        <defs>
          <linearGradient id="workspaceCubeFace" x1="0" x2="1" y1="0" y2="1">
            <stop offset="0" stop-color="var(--primary)" stop-opacity="0.28" />
            <stop offset="1" stop-color="var(--chart-2)" stop-opacity="0.12" />
          </linearGradient>
        </defs>
        <path
          d="M145 104 282 55 397 118 259 170Z"
          fill="url(#workspaceCubeFace)"
          stroke="var(--primary)"
          stroke-width="3"
        />
        <path
          d="M145 104 259 170 259 276 145 207Z"
          fill="var(--primary)"
          fill-opacity="0.13"
          stroke="var(--primary)"
          stroke-width="3"
        />
        <path
          d="M259 170 397 118 397 224 259 276Z"
          fill="var(--chart-2)"
          fill-opacity="0.12"
          stroke="var(--primary)"
          stroke-width="3"
        />
        <path
          d="M145 207 282 156 397 224"
          fill="none"
          stroke="var(--border)"
          stroke-dasharray="7 7"
          stroke-width="2"
        />
        <text x="271" y="302" fill="var(--foreground)" text-anchor="middle">
          a = {{ values.side ?? "—" }} {{ state.units.length }}
        </text>
      </svg>

      <div v-else class="workspace-visual-empty">
        {{ __("Solve the topic to open its visualization.") }}
      </div>
    </div>

    <LineChart
      v-else-if="state.graphConfiguration"
      :title="__(state.graphConfiguration.title)"
      :description="__(state.graphConfiguration.description)"
      :labels="state.graphConfiguration.labels.map((label) => __(label))"
      :values="state.graphConfiguration.values"
      :series-name="state.graphConfiguration.unit"
      :tooltip-value-label="state.graphConfiguration.unit"
      color="--chart-2"
      size="sm"
      card-class="workspace-embedded-chart"
    />
  </section>
</template>

<style scoped>
.workspace-visualization {
  min-width: 0;
}

.workspace-visualization-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.workspace-visualization-heading p {
  color: var(--muted-foreground);
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
}

.workspace-visualization-heading h2 {
  margin-top: 0.2rem;
  color: var(--foreground);
  font-size: 1.05rem;
  font-weight: 750;
}

.workspace-view-switch {
  display: flex;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 0.2rem;
}

.workspace-view-switch button {
  display: inline-flex;
  width: 2rem;
  height: 2rem;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-xs);
  color: var(--muted-foreground);
}

.workspace-view-switch button.is-active {
  background: var(--accent);
  color: var(--primary);
}

.workspace-view-switch button:disabled {
  cursor: not-allowed;
  opacity: 0.35;
}

.workspace-visual-frame {
  height: 20rem;
  min-height: 20rem;
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  background: var(--background);
}

.workspace-cube {
  width: 100%;
  height: 100%;
}

.workspace-cube text {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 14px;
}

.workspace-visual-empty {
  display: flex;
  height: 100%;
  align-items: center;
  justify-content: center;
  color: var(--muted-foreground);
  padding: 1rem;
  text-align: center;
  font-size: 0.8rem;
}

:deep(.workspace-embedded-chart) {
  border-radius: var(--radius-sm);
  box-shadow: none;
}
</style>
