<script setup lang="ts">
import { computed, ref } from "vue";
import { TresCanvas } from "@tresjs/core";
import { OrbitControls } from "@tresjs/cientos";
import * as THREE from "three";
import AdminFormLayout from "@/Layouts/AdminFormLayout.vue";
import { useTheme } from "@/composables/useTheme";
import { cmsTranslate as __ } from "@/lib/i18n";
import { themeColor } from "@/lib/themeColors";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

import CubeCalculator from "./Partials/CubeCalculator.vue";
import SphereCalculator from "./Partials/SphereCalculator.vue";
import ConeCalculator from "./Partials/ConeCalculator.vue";
import CylinderCalculator from "./Partials/CylinderCalculator.vue";
import StairCalculator from "./Partials/StairCalculator.vue";

type NullableNumber = number | null;

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

const shape = ref("cuboid");
const { theme } = useTheme();

const formDimensions = ref<Dimensions>({ length: 4, width: 3, height: 2 });
const submittedDimensions = ref<Dimensions>({ length: 4, width: 3, height: 2 });
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
  length: toPositive(submittedDimensions.value.length, 4),
  width: toPositive(submittedDimensions.value.width, 3),
  height: toPositive(submittedDimensions.value.height, 2),
}));

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
  const steps = Math.max(2, Math.round(toPositive(stairInput.value.steps, 8)));
  const stepWidth = toPositive(stairInput.value.stepWidth, 1.2);
  const rise = toPositive(stairInput.value.rise, 0.18);
  const run = toPositive(stairInput.value.run, 0.28);
  const depth = toPositive(stairInput.value.depth, 0.15);

  const innerRadius = 0.45;
  const outerRadius = innerRadius + stepWidth;
  const meanRadius = (innerRadius + outerRadius) / 2;
  const angleStep = Math.max(0.2, Math.min(1.2, run / meanRadius));

  return {
    steps,
    rise,
    depth,
    innerRadius,
    outerRadius,
    angleStep,
  };
});

function handleCuboidSubmit(value: Dimensions) {
  submittedDimensions.value = { ...value };
}

function handleStairUpdate(value: StairInput) {
  stairInput.value = { ...value };
}

function createStepShape(
  startAngle: number,
  endAngle: number,
  innerRadius: number,
  outerRadius: number,
) {
  const shape = new THREE.Shape();

  const x1 = Math.cos(startAngle) * innerRadius;
  const y1 = Math.sin(startAngle) * innerRadius;

  const x2 = Math.cos(startAngle) * outerRadius;
  const y2 = Math.sin(startAngle) * outerRadius;

  const x3 = Math.cos(endAngle) * outerRadius;
  const y3 = Math.sin(endAngle) * outerRadius;

  const x4 = Math.cos(endAngle) * innerRadius;
  const y4 = Math.sin(endAngle) * innerRadius;

  shape.moveTo(x1, y1);
  shape.lineTo(x2, y2);
  shape.lineTo(x3, y3);
  shape.lineTo(x4, y4);
  shape.lineTo(x1, y1);

  return shape;
}

const steps = computed(() =>
  Array.from({ length: renderStair.value.steps }, (_, i) => {
    const startAngle = i * renderStair.value.angleStep;
    const endAngle = startAngle + renderStair.value.angleStep;

    return {
      id: i,
      y: i * renderStair.value.rise,
      depth: renderStair.value.depth,
      shape: createStepShape(
        startAngle,
        endAngle,
        renderStair.value.innerRadius,
        renderStair.value.outerRadius,
      ),
    };
  }),
);

const sceneColors = computed(() => {
  theme.value;

  return {
    background: themeColor("--background"),
    cuboid: themeColor("--chart-4"),
    sphere: themeColor("--chart-2"),
    stairs: themeColor("--chart-3"),
    cone: themeColor("--chart-3"),
    cylinder: themeColor("--destructive"),
  };
});
</script>

<template>
  <AdminFormLayout :title="__('3D Geometry')">
    <div class="space-y-6">
      <div class="max-w-xs">
        <Select v-model="shape">
          <SelectTrigger class="w-full">
            <SelectValue :placeholder="__('Select shape')" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="cuboid">{{ __("Cuboid") }}</SelectItem>
            <SelectItem value="sphere">{{ __("Sphere") }}</SelectItem>
            <SelectItem value="cone">{{ __("Cone") }}</SelectItem>
            <SelectItem value="cylinder">{{ __("Cylinder") }}</SelectItem>
            <SelectItem value="stairs">{{ __("Stairs") }}</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="h-120 overflow-hidden rounded-xl border bg-background">
        <TresCanvas :clear-color="sceneColors.background">
          <TresPerspectiveCamera :position="[8, 6, 10]" :look-at="[0, 0, 0]" />
          <OrbitControls />
          <TresAmbientLight :intensity="1.5" />
          <TresDirectionalLight :position="[3, 5, 3]" :intensity="1.5" />
          <TresGridHelper :args="[20, 20]" />

          <TresMesh
            v-if="shape === 'cuboid'"
            :position="[0, renderBox.height / 2, 0]"
          >
            <TresBoxGeometry
              :args="[renderBox.length, renderBox.height, renderBox.width]"
            />
            <TresMeshStandardMaterial :color="sceneColors.cuboid" />
          </TresMesh>

          <TresMesh
            v-else-if="shape === 'sphere'"
            :position="[0, renderSphereRadius, 0]"
          >
            <TresSphereGeometry :args="[renderSphereRadius, 32, 32]" />
            <TresMeshStandardMaterial :color="sceneColors.sphere" />
          </TresMesh>

          <template v-else-if="shape === 'stairs'">
            <TresMesh
              v-for="step in steps"
              :key="step.id"
              :position="[0, step.y, 0]"
              :rotation="[-Math.PI / 2, 0, 0]"
            >
              <TresExtrudeGeometry
                :args="[
                  step.shape,
                  {
                    depth: step.depth,
                    bevelEnabled: false,
                  },
                ]"
              />
              <TresMeshStandardMaterial :color="sceneColors.stairs" />
            </TresMesh>
          </template>

          <TresMesh
            v-else-if="shape === 'cone'"
            :position="[0, renderConeHeight / 2, 0]"
          >
            <TresConeGeometry
              :args="[renderConeRadius, renderConeHeight, 32]"
            />
            <TresMeshStandardMaterial :color="sceneColors.cone" />
          </TresMesh>

          <TresMesh
            v-else-if="shape === 'cylinder'"
            :position="[0, renderCylinderHeight / 2, 0]"
          >
            <TresCylinderGeometry
              :args="[
                renderCylinderRadius,
                renderCylinderRadius,
                renderCylinderHeight,
                32,
              ]"
            />
            <TresMeshStandardMaterial :color="sceneColors.cylinder" />
          </TresMesh>
        </TresCanvas>
      </div>

      <CubeCalculator
        v-if="shape === 'cuboid'"
        :dimensions="formDimensions"
        @update:dimensions="formDimensions = $event"
        @submit="handleCuboidSubmit"
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
    </div>
  </AdminFormLayout>
</template>
