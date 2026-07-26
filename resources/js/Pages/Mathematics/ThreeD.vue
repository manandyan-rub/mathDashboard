<script setup lang="ts">
import { computed, ref, type Component } from "vue";
import { useRemember } from "@inertiajs/vue3";
import { TresCanvas } from "@tresjs/core";
import { OrbitControls } from "@tresjs/cientos";
import AdminFormLayout from "@/Layouts/AdminFormLayout.vue";
import { useTheme } from "@/composables/useTheme";
import { cmsTranslate as __ } from "@/lib/i18n";
import { themeColor } from "@/lib/themeColors";
import {
  Box,
  Boxes,
  Calculator,
  Circle,
  Layers,
  Sparkles,
  Triangle,
} from "lucide-vue-next";

import CubeCalculator from "./Partials/CubeCalculator.vue";
import CuboidCalculator from "./Partials/CuboidCalculator.vue";
import SphereCalculator from "./Partials/SphereCalculator.vue";
import ConeCalculator from "./Partials/ConeCalculator.vue";
import CylinderCalculator from "./Partials/CylinderCalculator.vue";
import StairCalculator from "./Partials/StairCalculator.vue";

type NullableNumber = number | null;
type ShapeKey =
  | "cuboid"
  | "cube"
  | "sphere"
  | "cone"
  | "cylinder"
  | "stairs";

type Dimensions = {
  length: NullableNumber;
  width: NullableNumber;
  height: NullableNumber;
};

type StairInput = {
  steps: NullableNumber;
  stepWidth: NullableNumber;
  rise: NullableNumber;
  run: NullableNumber;
  depth: NullableNumber;
};

type ShapeOption = {
  value: ShapeKey;
  label: string;
  metric: string;
  icon: Component;
  color: string;
};

const rememberedState = useRemember(
  { shape: "cuboid" as ShapeKey },
  "mathematics.three-d",
);
const shape = computed({
  get: () => rememberedState.shape,
  set: (value: ShapeKey) => {
    rememberedState.shape = value;
  },
});
const { theme } = useTheme();
const shapeOptions: ShapeOption[] = [
  {
    value: "cuboid",
    label: "Cuboid",
    metric: "l × w × h",
    icon: Boxes,
    color: "var(--chart-4)",
  },
  {
    value: "cube",
    label: "Cube",
    metric: "any 1 known",
    icon: Box,
    color: "var(--chart-1)",
  },
  {
    value: "sphere",
    label: "Sphere",
    metric: "r, d, V, S",
    icon: Circle,
    color: "var(--chart-2)",
  },
  {
    value: "cone",
    label: "Cone",
    metric: "r, h, l",
    icon: Triangle,
    color: "var(--chart-3)",
  },
  {
    value: "cylinder",
    label: "Cylinder",
    metric: "r, h, D",
    icon: Circle,
    color: "var(--chart-5)",
  },
  {
    value: "stairs",
    label: "Stairs",
    metric: "rise + run",
    icon: Layers,
    color: "var(--primary)",
  },
];

const activeShape = computed(
  () =>
    shapeOptions.find((item) => item.value === shape.value) ?? shapeOptions[0],
);

const formDimensions = ref<Dimensions>({ length: 4, width: 3, height: 2 });
const cubeSide = ref<NullableNumber>(null);
const stairInput = ref<StairInput>({
  steps: 8,
  stepWidth: 1.2,
  rise: 0.18,
  run: 0.28,
  depth: 0.15,
});

const sphereRadius = ref<NullableNumber>(1.4);
const coneRadius = ref<NullableNumber>(1.2);
const coneHeight = ref<NullableNumber>(2.4);
const cylinderRadius = ref<NullableNumber>(1.2);
const cylinderHeight = ref<NullableNumber>(2.4);

const toPositive = (value: NullableNumber, fallback = 1) => {
  if (value === null || value === undefined) return fallback;
  const parsed = Number(value);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
};

const renderBox = computed(() => ({
  length: toPositive(formDimensions.value.length, 4),
  width: toPositive(formDimensions.value.width, 3),
  height: toPositive(formDimensions.value.height, 2),
}));

const cuboidCameraPosition = computed<[number, number, number]>(() => {
  const maxDimension = Math.max(
    renderBox.value.length,
    renderBox.value.width,
    renderBox.value.height,
  );
  const distance = Math.max(8, maxDimension * 2.2);

  return [
    distance,
    Math.max(6, renderBox.value.height * 2.1),
    distance * 1.15,
  ];
});

const cuboidLookAt = computed<[number, number, number]>(() => [
  0,
  renderBox.value.height / 2,
  0,
]);

const renderCubeSide = computed(() => {
  const side = cubeSide.value;
  if (side === null || !Number.isFinite(side) || side <= 0) return 1.8;

  return Math.min(6, Math.max(0.8, 2.8 + Math.log10(side)));
});

const cubeCameraPosition = computed<[number, number, number]>(() => {
  const distance = Math.max(7, renderCubeSide.value * 2.5);
  return [distance, distance * 0.78, distance * 1.08];
});

const cubeLookAt = computed<[number, number, number]>(() => [
  0,
  renderCubeSide.value / 2,
  0,
]);

const sphereCameraPosition = computed<[number, number, number]>(() => {
  const radius = renderSphereRadius.value;
  const distance = Math.max(7, radius * 3.2);

  return [distance * 0.55, Math.max(4, radius * 1.35), distance];
});

const sphereLookAt = computed<[number, number, number]>(() => [
  0,
  renderSphereRadius.value,
  0,
]);

const coneCameraPosition = computed<[number, number, number]>(() => {
  const radius = renderConeRadius.value;
  const height = renderConeHeight.value;
  const maxDimension = Math.max(radius * 2, height);
  const distance = Math.max(7, maxDimension * 2.3);

  return [distance * 0.76, Math.max(4.5, height * 1.15), distance];
});

const coneLookAt = computed<[number, number, number]>(() => [
  0,
  renderConeHeight.value / 2,
  0,
]);

const cylinderCameraPosition = computed<[number, number, number]>(() => {
  const radius = renderCylinderRadius.value;
  const height = renderCylinderHeight.value;
  const maxDimension = Math.max(radius * 2, height);
  const distance = Math.max(7, maxDimension * 2.2);

  return [distance * 0.78, Math.max(4.5, height * 1.1), distance];
});

const cylinderLookAt = computed<[number, number, number]>(() => [
  0,
  renderCylinderHeight.value / 2,
  0,
]);

const stairCameraPosition = computed<[number, number, number]>(() => {
  const maxDimension = Math.max(
    renderStair.value.totalGoing,
    renderStair.value.totalRise,
    renderStair.value.stepWidth,
  );
  const distance = Math.max(7, maxDimension * 1.8);

  return [
    distance * 0.92,
    Math.max(5, renderStair.value.totalRise * 1.25),
    distance * 1.08,
  ];
});

const stairLookAt = computed<[number, number, number]>(() => [
  0,
  renderStair.value.totalRise / 2,
  0,
]);

const activeCameraPosition = computed<[number, number, number]>(() => {
  if (shape.value === "cuboid") {
    return cuboidCameraPosition.value;
  }

  if (shape.value === "cube") {
    return cubeCameraPosition.value;
  }

  if (shape.value === "sphere") {
    return sphereCameraPosition.value;
  }

  if (shape.value === "cone") {
    return coneCameraPosition.value;
  }

  if (shape.value === "cylinder") {
    return cylinderCameraPosition.value;
  }

  if (shape.value === "stairs") {
    return stairCameraPosition.value;
  }

  return [8, 6, 10];
});

const activeLookAt = computed<[number, number, number]>(() => {
  if (shape.value === "cuboid") {
    return cuboidLookAt.value;
  }

  if (shape.value === "cube") {
    return cubeLookAt.value;
  }

  if (shape.value === "sphere") {
    return sphereLookAt.value;
  }

  if (shape.value === "cone") {
    return coneLookAt.value;
  }

  if (shape.value === "cylinder") {
    return cylinderLookAt.value;
  }

  if (shape.value === "stairs") {
    return stairLookAt.value;
  }

  return [0, 0, 0];
});

const renderSphereRadius = computed(() => toPositive(sphereRadius.value, 1.4));
const renderConeRadius = computed(() => toPositive(coneRadius.value, 1.2));
const renderConeHeight = computed(() => toPositive(coneHeight.value, 2.4));
const renderCylinderRadius = computed(() =>
  toPositive(cylinderRadius.value, 1.2),
);
const renderCylinderHeight = computed(() =>
  toPositive(cylinderHeight.value, 2.4),
);

const renderStair = computed(() => {
  const risers = Math.max(2, Math.round(toPositive(stairInput.value.steps, 8)));
  const treads = Math.max(1, risers - 1);
  const stepWidth = toPositive(stairInput.value.stepWidth, 1.2);
  const rise = toPositive(stairInput.value.rise, 0.18);
  const run = toPositive(stairInput.value.run, 0.28);
  const depth = toPositive(stairInput.value.depth, 0.05);

  return {
    risers,
    treads,
    stepWidth,
    rise,
    run,
    depth,
    totalRise: risers * rise,
    totalGoing: treads * run,
  };
});

function handleCuboidSubmit(value: Dimensions) {
  formDimensions.value = { ...value };
}

function handleStairUpdate(value: StairInput) {
  stairInput.value = { ...value };
}

const steps = computed(() =>
  Array.from({ length: renderStair.value.treads }, (_, i) => {
    const x =
      -renderStair.value.totalGoing / 2 +
      renderStair.value.run / 2 +
      i * renderStair.value.run;
    const y = (i + 1) * renderStair.value.rise - renderStair.value.depth / 2;

    return {
      id: i,
      x,
      y,
    };
  }),
);

const sceneColors = computed(() => {
  theme.value;

  return {
    background: themeColor("--background"),
    cuboid: themeColor("--chart-4"),
    cube: themeColor("--chart-1"),
    sphere: themeColor("--chart-2"),
    stairs: themeColor("--primary"),
    cone: themeColor("--chart-3"),
    cylinder: themeColor("--chart-5"),
  };
});
</script>

<template>
  <AdminFormLayout :title="__('3D Geometry')" content-width="big">
    <div class="geometry-engine space-y-6">
      <section class="geometry-hero">
        <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
          <div class="min-w-0">
            <div class="geometry-badge">
              <Triangle :size="14" />
              {{ __("3D Geometry Engine") }}
            </div>
            <h2 class="mt-4 text-3xl font-bold tracking-tight text-foreground md:text-4xl">
              {{ __("3D Geometry") }}
            </h2>
            <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
              <span class="geometry-pill">
                <Sparkles :size="14" />
                {{ __(activeShape.label) }}
              </span>
              <span class="font-mono">{{ __(activeShape.metric) }}</span>
            </div>
          </div>

          <div class="grid w-full gap-2 sm:grid-cols-2 lg:grid-cols-6 xl:w-auto xl:min-w-[48rem]">
            <button
              v-for="item in shapeOptions"
              :key="item.value"
              type="button"
              class="geometry-shape-button"
              :class="{ 'is-active': shape === item.value }"
              :style="{ '--shape-accent': item.color }"
              :aria-pressed="shape === item.value"
              @click="shape = item.value"
            >
              <component :is="item.icon" :size="18" />
              <span>{{ __(item.label) }}</span>
            </button>
          </div>
        </div>
      </section>

      <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="geometry-preview-shell">
          <div class="geometry-preview-header">
            <div>
              <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                {{ __("Geometry Preview") }}
              </p>
              <h3 class="mt-1 text-xl font-bold text-foreground">
                {{ __(activeShape.label) }}
              </h3>
            </div>
            <div
              class="geometry-preview-icon"
              :style="{ '--shape-accent': activeShape.color }"
            >
              <component :is="activeShape.icon" :size="22" />
            </div>
          </div>

          <div class="geometry-canvas-shell">
            <div class="geometry-canvas-glow" />
            <TresCanvas :clear-color="sceneColors.background">
              <TresPerspectiveCamera
                :position="activeCameraPosition"
                :look-at="activeLookAt"
              />
              <OrbitControls />
              <TresAmbientLight :intensity="1.5" />
              <TresDirectionalLight :position="[3, 5, 3]" :intensity="1.5" />
              <TresGridHelper :args="[20, 20]" />

              <template v-if="shape === 'cuboid'">
                <TresMesh :position="[0, renderBox.height / 2, 0]">
                  <TresBoxGeometry
                    :args="[renderBox.length, renderBox.height, renderBox.width]"
                  />
                  <TresMeshStandardMaterial
                    :color="sceneColors.cuboid"
                    :metalness="0.08"
                    :roughness="0.34"
                  />
                </TresMesh>

                <TresMesh
                  :position="[0, renderBox.height / 2, 0]"
                  :scale="[1.003, 1.003, 1.003]"
                >
                  <TresBoxGeometry
                    :args="[renderBox.length, renderBox.height, renderBox.width]"
                  />
                  <TresMeshBasicMaterial
                    color="#ffffff"
                    :wireframe="true"
                    :transparent="true"
                    :opacity="0.24"
                  />
                </TresMesh>
              </template>

              <template v-else-if="shape === 'cube'">
                <TresMesh :position="[0, renderCubeSide / 2, 0]">
                  <TresBoxGeometry
                    :args="[renderCubeSide, renderCubeSide, renderCubeSide]"
                  />
                  <TresMeshStandardMaterial
                    :color="sceneColors.cube"
                    :metalness="0.08"
                    :roughness="0.3"
                  />
                </TresMesh>

                <TresMesh
                  :position="[0, renderCubeSide / 2, 0]"
                  :scale="[1.003, 1.003, 1.003]"
                >
                  <TresBoxGeometry
                    :args="[renderCubeSide, renderCubeSide, renderCubeSide]"
                  />
                  <TresMeshBasicMaterial
                    color="#ffffff"
                    :wireframe="true"
                    :transparent="true"
                    :opacity="0.24"
                  />
                </TresMesh>
              </template>

              <template v-else-if="shape === 'sphere'">
                <TresMesh :position="[0, renderSphereRadius, 0]">
                  <TresSphereGeometry :args="[renderSphereRadius, 32, 32]" />
                  <TresMeshStandardMaterial
                    :color="sceneColors.sphere"
                    :metalness="0.08"
                    :roughness="0.28"
                  />
                </TresMesh>

                <TresMesh
                  :position="[0, renderSphereRadius, 0]"
                  :scale="[1.002, 1.002, 1.002]"
                >
                  <TresSphereGeometry :args="[renderSphereRadius, 32, 32]" />
                  <TresMeshBasicMaterial
                    color="#ffffff"
                    :wireframe="true"
                    :transparent="true"
                    :opacity="0.18"
                  />
                </TresMesh>
              </template>

              <template v-else-if="shape === 'stairs'">
                <TresMesh
                  v-for="step in steps"
                  :key="step.id"
                  :position="[step.x, step.y, 0]"
                >
                  <TresBoxGeometry
                    :args="[
                      renderStair.run,
                      renderStair.depth,
                      renderStair.stepWidth,
                    ]"
                  />
                  <TresMeshStandardMaterial
                    :color="sceneColors.stairs"
                    :metalness="0.08"
                    :roughness="0.32"
                  />
                </TresMesh>

                <TresMesh
                  v-for="step in steps"
                  :key="`wire-${step.id}`"
                  :position="[step.x, step.y, 0]"
                  :scale="[1.002, 1.002, 1.002]"
                >
                  <TresBoxGeometry
                    :args="[
                      renderStair.run,
                      renderStair.depth,
                      renderStair.stepWidth,
                    ]"
                  />
                  <TresMeshBasicMaterial
                    color="#ffffff"
                    :wireframe="true"
                    :transparent="true"
                    :opacity="0.16"
                  />
                </TresMesh>

                <TresMesh
                  :position="[
                    renderStair.totalGoing / 2,
                    renderStair.totalRise - renderStair.rise / 2,
                    0,
                  ]"
                >
                  <TresBoxGeometry
                    :args="[
                      Math.max(0.04, renderStair.run * 0.08),
                      renderStair.rise,
                      renderStair.stepWidth,
                    ]"
                  />
                  <TresMeshStandardMaterial
                    :color="sceneColors.stairs"
                    :metalness="0.08"
                    :roughness="0.36"
                  />
                </TresMesh>
              </template>

              <template v-else-if="shape === 'cone'">
                <TresMesh :position="[0, renderConeHeight / 2, 0]">
                  <TresConeGeometry
                    :args="[renderConeRadius, renderConeHeight, 48]"
                  />
                  <TresMeshStandardMaterial
                    :color="sceneColors.cone"
                    :metalness="0.08"
                    :roughness="0.3"
                  />
                </TresMesh>

                <TresMesh
                  :position="[0, renderConeHeight / 2, 0]"
                  :scale="[1.002, 1.002, 1.002]"
                >
                  <TresConeGeometry
                    :args="[renderConeRadius, renderConeHeight, 48]"
                  />
                  <TresMeshBasicMaterial
                    color="#ffffff"
                    :wireframe="true"
                    :transparent="true"
                    :opacity="0.18"
                  />
                </TresMesh>
              </template>

              <template v-else-if="shape === 'cylinder'">
                <TresMesh :position="[0, renderCylinderHeight / 2, 0]">
                  <TresCylinderGeometry
                    :args="[
                      renderCylinderRadius,
                      renderCylinderRadius,
                      renderCylinderHeight,
                      48,
                    ]"
                  />
                  <TresMeshStandardMaterial
                    :color="sceneColors.cylinder"
                    :metalness="0.08"
                    :roughness="0.3"
                  />
                </TresMesh>

                <TresMesh
                  :position="[0, renderCylinderHeight / 2, 0]"
                  :scale="[1.002, 1.002, 1.002]"
                >
                  <TresCylinderGeometry
                    :args="[
                      renderCylinderRadius,
                      renderCylinderRadius,
                      renderCylinderHeight,
                      48,
                    ]"
                  />
                  <TresMeshBasicMaterial
                    color="#ffffff"
                    :wireframe="true"
                    :transparent="true"
                    :opacity="0.18"
                  />
                </TresMesh>
              </template>
            </TresCanvas>
          </div>
        </div>

        <aside class="geometry-side-panel">
          <div class="flex items-center gap-3">
            <div
              class="geometry-side-icon"
              :style="{ '--shape-accent': activeShape.color }"
            >
              <Calculator :size="20" />
            </div>
            <div>
              <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                {{ __("Active Calculator") }}
              </p>
              <h3 class="text-lg font-bold text-foreground">
                {{ __(activeShape.label) }}
              </h3>
            </div>
          </div>

          <div class="mt-6 space-y-3">
            <div
              v-for="item in shapeOptions"
              :key="`summary-${item.value}`"
              class="geometry-summary-row"
              :class="{ 'is-active': shape === item.value }"
              :style="{ '--shape-accent': item.color }"
            >
              <span>{{ __(item.label) }}</span>
              <span class="font-mono text-xs">{{ __(item.metric) }}</span>
            </div>
          </div>
        </aside>
      </section>

      <section class="geometry-calculator-panel">
        <CuboidCalculator
          v-if="shape === 'cuboid'"
          :dimensions="formDimensions"
          @update:dimensions="formDimensions = $event"
          @submit="handleCuboidSubmit"
        />

        <CubeCalculator
          v-else-if="shape === 'cube'"
          :side="cubeSide"
          @update:side="cubeSide = $event"
        />

        <SphereCalculator
          v-else-if="shape === 'sphere'"
          @update:radius="sphereRadius = $event"
        />

        <ConeCalculator
          v-else-if="shape === 'cone'"
          @update:cone="
            (v) => {
              coneRadius = v.radius;
              coneHeight = v.height;
            }
          "
        />

        <CylinderCalculator
          v-else-if="shape === 'cylinder'"
          @update:cylinder="
            (v) => {
              cylinderRadius = v.radius;
              cylinderHeight = v.height;
            }
          "
        />

        <StairCalculator
          v-else-if="shape === 'stairs'"
          @update:stair="handleStairUpdate"
        />
      </section>
    </div>
  </AdminFormLayout>
</template>

<style scoped>
.geometry-engine {
  --geometry-soft-shadow: 0 24px 80px color-mix(in srgb, var(--ring) 14%, transparent);
}

.geometry-hero,
.geometry-preview-shell,
.geometry-side-panel,
.geometry-calculator-panel {
  border: 1px solid color-mix(in srgb, var(--border) 82%, transparent);
  background:
    radial-gradient(
      circle at top left,
      color-mix(in srgb, var(--chart-5) 10%, transparent),
      transparent 28rem
    ),
    color-mix(in srgb, var(--card) 92%, transparent);
  box-shadow: var(--geometry-soft-shadow);
}

.geometry-hero,
.geometry-preview-shell,
.geometry-side-panel {
  border-radius: 1.25rem;
  padding: 1.5rem;
}

.geometry-calculator-panel {
  border-radius: 1.25rem;
  padding: 1rem;
}

.geometry-badge,
.geometry-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  border-radius: 999px;
  border: 1px solid color-mix(in srgb, var(--chart-5) 28%, transparent);
  background: color-mix(in srgb, var(--chart-5) 10%, transparent);
  color: var(--chart-5);
  font-size: 0.75rem;
  font-weight: 700;
}

.geometry-badge {
  padding: 0.35rem 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.geometry-pill {
  padding: 0.45rem 0.8rem;
}

.geometry-shape-button {
  display: inline-flex;
  min-height: 3rem;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  border-radius: 0.875rem;
  border: 1px solid color-mix(in srgb, var(--border) 80%, transparent);
  background: color-mix(in srgb, var(--muted) 55%, transparent);
  color: var(--muted-foreground);
  font-size: 0.875rem;
  font-weight: 700;
  transition:
    border-color 0.18s ease,
    background-color 0.18s ease,
    color 0.18s ease,
    transform 0.18s ease,
    box-shadow 0.18s ease;
}

.geometry-shape-button:hover,
.geometry-shape-button.is-active {
  border-color: color-mix(in srgb, var(--shape-accent) 55%, var(--border));
  background: color-mix(in srgb, var(--shape-accent) 12%, var(--card));
  color: var(--foreground);
  box-shadow: 0 12px 30px color-mix(in srgb, var(--shape-accent) 16%, transparent);
  transform: translateY(-1px);
}

.geometry-shape-button svg,
.geometry-preview-icon svg,
.geometry-side-icon svg {
  color: var(--shape-accent);
}

.geometry-preview-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding-bottom: 1rem;
}

.geometry-preview-icon,
.geometry-side-icon {
  display: flex;
  height: 3rem;
  width: 3rem;
  align-items: center;
  justify-content: center;
  border-radius: 0.875rem;
  border: 1px solid color-mix(in srgb, var(--shape-accent) 30%, var(--border));
  background: color-mix(in srgb, var(--shape-accent) 12%, transparent);
}

.geometry-canvas-shell {
  position: relative;
  height: clamp(26rem, 46vw, 34rem);
  overflow: hidden;
  border-radius: 1rem;
  border: 1px solid color-mix(in srgb, var(--border) 78%, transparent);
  background: color-mix(in srgb, var(--background) 92%, black 8%);
}

.geometry-canvas-glow {
  pointer-events: none;
  position: absolute;
  inset: auto 15% 2rem;
  z-index: 1;
  height: 5rem;
  border-radius: 999px;
  background: color-mix(in srgb, var(--chart-5) 14%, transparent);
  filter: blur(32px);
}

.geometry-side-panel {
  align-self: stretch;
}

.geometry-summary-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  border-radius: 0.875rem;
  border: 1px solid color-mix(in srgb, var(--border) 82%, transparent);
  background: color-mix(in srgb, var(--muted) 42%, transparent);
  padding: 0.85rem 1rem;
  color: var(--muted-foreground);
  font-size: 0.875rem;
}

.geometry-summary-row.is-active {
  border-color: color-mix(in srgb, var(--shape-accent) 45%, var(--border));
  background: color-mix(in srgb, var(--shape-accent) 10%, var(--card));
  color: var(--foreground);
}

.geometry-engine :deep([data-slot="card"]) {
  overflow: hidden;
  border-color: color-mix(in srgb, var(--border) 82%, transparent);
  background:
    radial-gradient(
      circle at top right,
      color-mix(in srgb, var(--chart-5) 8%, transparent),
      transparent 14rem
    ),
    color-mix(in srgb, var(--card) 88%, transparent);
  box-shadow: 0 18px 46px color-mix(in srgb, var(--ring) 9%, transparent);
}

.geometry-engine :deep([data-slot="card-header"]) {
  border-bottom: 1px solid color-mix(in srgb, var(--border) 75%, transparent);
  background: color-mix(in srgb, var(--muted) 35%, transparent);
  padding-bottom: 1rem;
}

.geometry-engine :deep([data-slot="card-title"]) {
  color: var(--foreground);
  font-weight: 800;
  letter-spacing: 0;
}

.geometry-engine :deep(label) {
  color: var(--muted-foreground);
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.04em;
}

.geometry-engine :deep([data-slot="input-group"]) {
  min-height: 3rem;
  border-color: color-mix(in srgb, var(--border) 84%, transparent);
  background: color-mix(in srgb, var(--background) 70%, transparent);
  box-shadow: inset 0 1px 0 color-mix(in srgb, var(--foreground) 4%, transparent);
}

.geometry-engine :deep([data-slot="input-group"]:focus-within) {
  border-color: color-mix(in srgb, var(--chart-5) 65%, var(--border));
  box-shadow:
    0 0 0 3px color-mix(in srgb, var(--chart-5) 14%, transparent),
    inset 0 1px 0 color-mix(in srgb, var(--foreground) 4%, transparent);
}

.geometry-engine :deep(input) {
  font-family: var(--font-mono);
  color: var(--foreground);
}

.geometry-engine :deep([data-slot="button"]) {
  min-height: 2.75rem;
  border-radius: 0.75rem;
  font-weight: 800;
}

.geometry-engine :deep([data-slot="button"]:not([data-variant="outline"])) {
  border: 1px solid color-mix(in srgb, var(--chart-5) 35%, transparent);
  background: linear-gradient(135deg, var(--primary), var(--chart-5));
  color: var(--primary-foreground);
  box-shadow: 0 14px 30px color-mix(in srgb, var(--chart-5) 20%, transparent);
}

.geometry-engine :deep([data-slot="button"][data-variant="outline"]) {
  border-color: color-mix(in srgb, var(--border) 88%, transparent);
  background: color-mix(in srgb, var(--muted) 45%, transparent);
  color: var(--foreground);
}

.geometry-engine :deep([role="alert"]) {
  border-radius: 1rem;
}

@media (max-width: 48rem) {
  .geometry-hero,
  .geometry-preview-shell,
  .geometry-side-panel,
  .geometry-calculator-panel {
    padding: 1rem;
  }

  .geometry-canvas-shell {
    height: 24rem;
  }
}
</style>
