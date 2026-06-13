<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import "konva/lib/shapes/Circle";
import "konva/lib/shapes/Line";
import "konva/lib/shapes/Rect";
import "konva/lib/shapes/Text";
import {
  Circle as VCircle,
  Layer as VLayer,
  Line as VLine,
  Rect as VRect,
  Stage as VStage,
  Text as VText,
} from "vue-konva/core";
import { useTheme } from "@/composables/useTheme";
import { cmsTranslate as __ } from "@/lib/i18n";
import { themeColor, themeColorAlpha } from "@/lib/themeColors";
import type {
  NullableNumber,
  NumericLike,
  TwoDInputs,
  TwoDShapeKey,
} from "@/types/twoDGeometry";

const props = defineProps<{
  shape: TwoDShapeKey;
  inputs: TwoDInputs;
  accentColor: string;
}>();

const shell = ref<HTMLElement | null>(null);
const size = ref({ width: 520, height: 320 });
const { theme } = useTheme();
let resizeObserver: ResizeObserver | null = null;

const stageConfig = computed(() => ({
  width: Math.max(320, Math.round(size.value.width)),
  height: Math.max(220, Math.round(size.value.height)),
}));

const scale = computed(() =>
  Math.min(stageConfig.value.width / 520, stageConfig.value.height / 320),
);

const labelFontSize = computed(() => Math.max(11, Math.round(12.5 * scale.value)));
const labelHeight = computed(() => Math.max(24, Math.round(26 * scale.value)));

const colors = computed(() => {
  theme.value;

  return {
    accent: themeColor(props.accentColor, "#6366f1"),
    accentSoft: themeColorAlpha(props.accentColor, 0.13, "rgba(99, 102, 241, 0.13)"),
    accentMuted: themeColorAlpha(props.accentColor, 0.5, "rgba(99, 102, 241, 0.5)"),
    background: themeColor("--background", "#f7f9ff"),
    border: themeColorAlpha("--border", 0.8, "rgba(148, 163, 184, 0.4)"),
    foreground: themeColor("--foreground", "#0f172a"),
    muted: themeColorAlpha("--foreground", 0.44, "rgba(15, 23, 42, 0.44)"),
    guide: themeColorAlpha("--foreground", 0.14, "rgba(15, 23, 42, 0.14)"),
    labelBg: themeColor("--card", "#ffffff"),
  };
});

const backdropConfig = computed(() => ({
  x: 0,
  y: 0,
  width: stageConfig.value.width,
  height: stageConfig.value.height,
  fill: colors.value.background,
  listening: false,
}));

const paperLines = computed(() => {
  const lines: Array<{ points: number[]; major: boolean }> = [];
  const gap = Math.max(32, 42 * scale.value);
  const width = stageConfig.value.width;
  const height = stageConfig.value.height;
  let index = 1;

  for (let x = gap; x < width; x += gap, index += 1) {
    lines.push({ points: [x, 0, x, height], major: index % 4 === 0 });
  }

  index = 1;
  for (let y = gap; y < height; y += gap, index += 1) {
    lines.push({ points: [0, y, width, y], major: index % 4 === 0 });
  }

  return lines;
});

function asPositive(value: NumericLike): NullableNumber {
  if (value === "" || value === null || value === undefined) return null;
  const parsed = Number(value);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
}

function valueOrDash(value: NullableNumber): string {
  return value === null ? "-" : String(value);
}

function sx(value: number): number {
  return (value / 520) * stageConfig.value.width;
}

function sy(value: number): number {
  return (value / 320) * stageConfig.value.height;
}

function points(values: number[]): number[] {
  return values.map((value, index) => (index % 2 === 0 ? sx(value) : sy(value)));
}

function shapeConfig(rawPoints: number[]) {
  return {
    points: points(rawPoints),
    closed: true,
    fill: colors.value.accentSoft,
    stroke: colors.value.accent,
    strokeWidth: Math.max(2.5, 3 * scale.value),
    lineCap: "square",
    lineJoin: "miter",
    listening: false,
  };
}

function helperLineConfig(rawPoints: number[], dashed = true) {
  return {
    points: points(rawPoints),
    stroke: colors.value.guide,
    strokeWidth: Math.max(1, 1.25 * scale.value),
    dash: dashed ? [6 * scale.value, 6 * scale.value] : [],
    lineCap: "round",
    lineJoin: "round",
    listening: false,
  };
}

function measureLineConfig(rawPoints: number[]) {
  return {
    points: points(rawPoints),
    stroke: colors.value.accentMuted,
    strokeWidth: Math.max(1.5, 2 * scale.value),
    lineCap: "round",
    lineJoin: "round",
    listening: false,
  };
}

function pointConfig(x: number, y: number) {
  return {
    x: sx(x),
    y: sy(y),
    radius: Math.max(2.5, 3.5 * scale.value),
    fill: colors.value.background,
    stroke: colors.value.accent,
    strokeWidth: Math.max(1.5, 2 * scale.value),
    listening: false,
  };
}

function labelBoxConfig(x: number, y: number, width = 110) {
  const scaledWidth = width * scale.value;

  return {
    x: sx(x) - scaledWidth / 2,
    y: sy(y),
    width: scaledWidth,
    height: labelHeight.value,
    cornerRadius: Math.max(6, 8 * scale.value),
    fill: colors.value.labelBg,
    stroke: colors.value.border,
    strokeWidth: 1,
    listening: false,
  };
}

function labelTextConfig(x: number, y: number, text: string, width = 110) {
  const scaledWidth = width * scale.value;

  return {
    x: sx(x) - scaledWidth / 2,
    y: sy(y) + Math.max(5, 6 * scale.value),
    width: scaledWidth,
    text,
    align: "center",
    fill: colors.value.foreground,
    fontFamily: "Inter, ui-sans-serif, system-ui",
    fontSize: labelFontSize.value,
    fontStyle: "bold",
    listening: false,
  };
}

const circleRadius = computed(
  () =>
    asPositive(props.inputs.circle.radius) ??
    (asPositive(props.inputs.circle.diameter)
      ? asPositive(props.inputs.circle.diameter)! / 2
      : null),
);

const circleDiameter = computed(
  () =>
    asPositive(props.inputs.circle.diameter) ??
    (circleRadius.value ? circleRadius.value * 2 : null),
);

const circleConfig = computed(() => ({
  x: sx(260),
  y: sy(155),
  radius: Math.min(sx(90), sy(90)),
  fill: colors.value.accentSoft,
  stroke: colors.value.accent,
  strokeWidth: Math.max(2.5, 3 * scale.value),
  listening: false,
}));

onMounted(() => {
  const updateSize = () => {
    if (!shell.value) return;
    size.value = {
      width: shell.value.clientWidth || 520,
      height: shell.value.clientHeight || 320,
    };
  };

  updateSize();
  resizeObserver = new ResizeObserver(updateSize);
  if (shell.value) resizeObserver.observe(shell.value);
});

onBeforeUnmount(() => {
  resizeObserver?.disconnect();
});
</script>

<template>
  <div ref="shell" class="h-full w-full">
    <VStage :config="stageConfig" class="plane-konva-stage">
      <VLayer>
        <VRect :config="backdropConfig" />
        <VLine
          v-for="(line, index) in paperLines"
          :key="`paper-${index}`"
          :config="{
            points: line.points,
            stroke: colors.guide,
            strokeWidth: line.major ? 1 : 0.75,
            opacity: line.major ? 0.46 : 0.24,
            listening: false,
          }"
        />

        <template v-if="shape === 'rectangle'">
          <VLine :config="shapeConfig([120, 78, 400, 78, 400, 238, 120, 238])" />
          <VLine :config="helperLineConfig([120, 78, 400, 238], false)" />

          <VLine :config="measureLineConfig([120, 262, 400, 262])" />
          <VLine :config="helperLineConfig([120, 238, 120, 270])" />
          <VLine :config="helperLineConfig([400, 238, 400, 270])" />
          <VCircle :config="pointConfig(120, 262)" />
          <VCircle :config="pointConfig(400, 262)" />
          <VRect :config="labelBoxConfig(260, 274, 104)" />
          <VText
            :config="
              labelTextConfig(
                260,
                274,
                `l = ${valueOrDash(asPositive(inputs.rectangle.length))}`,
                104,
              )
            "
          />

          <VLine :config="measureLineConfig([424, 78, 424, 238])" />
          <VLine :config="helperLineConfig([400, 78, 432, 78])" />
          <VLine :config="helperLineConfig([400, 238, 432, 238])" />
          <VCircle :config="pointConfig(424, 78)" />
          <VCircle :config="pointConfig(424, 238)" />
          <VRect :config="labelBoxConfig(454, 146, 98)" />
          <VText
            :config="
              labelTextConfig(
                454,
                146,
                `w = ${valueOrDash(asPositive(inputs.rectangle.width))}`,
                98,
              )
            "
          />
        </template>

        <template v-else-if="shape === 'triangle'">
          <VLine :config="shapeConfig([104, 242, 416, 242, 282, 62])" />
          <VLine :config="helperLineConfig([282, 62, 282, 242])" />

          <VLine :config="measureLineConfig([104, 264, 416, 264])" />
          <VLine :config="helperLineConfig([104, 242, 104, 272])" />
          <VLine :config="helperLineConfig([416, 242, 416, 272])" />
          <VCircle :config="pointConfig(104, 264)" />
          <VCircle :config="pointConfig(416, 264)" />
          <VRect :config="labelBoxConfig(260, 276, 150)" />
          <VText
            :config="
              labelTextConfig(
                260,
                276,
                `${__('Base')} = ${valueOrDash(asPositive(inputs.triangle.base))}`,
                150,
              )
            "
          />

          <VCircle :config="pointConfig(282, 62)" />
          <VCircle :config="pointConfig(282, 242)" />
          <VRect :config="labelBoxConfig(326, 140, 98)" />
          <VText
            :config="
              labelTextConfig(
                326,
                140,
                `h = ${valueOrDash(asPositive(inputs.triangle.height))}`,
                98,
              )
            "
          />
        </template>

        <template v-else-if="shape === 'circle'">
          <VCircle :config="circleConfig" />
          <VCircle :config="pointConfig(260, 155)" />

          <VLine :config="measureLineConfig([260, 155, 350, 155])" />
          <VCircle :config="pointConfig(350, 155)" />
          <VRect :config="labelBoxConfig(322, 126, 98)" />
          <VText
            :config="
              labelTextConfig(
                322,
                126,
                `r = ${valueOrDash(circleRadius)}`,
                98,
              )
            "
          />

          <VLine :config="measureLineConfig([170, 264, 350, 264])" />
          <VLine :config="helperLineConfig([170, 155, 170, 272])" />
          <VLine :config="helperLineConfig([350, 155, 350, 272])" />
          <VCircle :config="pointConfig(170, 264)" />
          <VCircle :config="pointConfig(350, 264)" />
          <VRect :config="labelBoxConfig(260, 276, 112)" />
          <VText
            :config="
              labelTextConfig(
                260,
                276,
                `d = ${valueOrDash(circleDiameter)}`,
                112,
              )
            "
          />
        </template>

        <template v-else-if="shape === 'trapezoid'">
          <VLine :config="shapeConfig([132, 242, 396, 242, 340, 78, 190, 78])" />
          <VLine :config="helperLineConfig([264, 78, 264, 242])" />

          <VLine :config="measureLineConfig([190, 58, 340, 58])" />
          <VLine :config="helperLineConfig([190, 78, 190, 50])" />
          <VLine :config="helperLineConfig([340, 78, 340, 50])" />
          <VCircle :config="pointConfig(190, 58)" />
          <VCircle :config="pointConfig(340, 58)" />
          <VRect :config="labelBoxConfig(265, 24, 104)" />
          <VText
            :config="
              labelTextConfig(
                265,
                24,
                `a = ${valueOrDash(asPositive(inputs.trapezoid.baseA))}`,
                104,
              )
            "
          />

          <VLine :config="measureLineConfig([132, 264, 396, 264])" />
          <VLine :config="helperLineConfig([132, 242, 132, 272])" />
          <VLine :config="helperLineConfig([396, 242, 396, 272])" />
          <VCircle :config="pointConfig(132, 264)" />
          <VCircle :config="pointConfig(396, 264)" />
          <VRect :config="labelBoxConfig(264, 276, 104)" />
          <VText
            :config="
              labelTextConfig(
                264,
                276,
                `b = ${valueOrDash(asPositive(inputs.trapezoid.baseB))}`,
                104,
              )
            "
          />

          <VCircle :config="pointConfig(264, 78)" />
          <VCircle :config="pointConfig(264, 242)" />
          <VRect :config="labelBoxConfig(314, 146, 98)" />
          <VText
            :config="
              labelTextConfig(
                314,
                146,
                `h = ${valueOrDash(asPositive(inputs.trapezoid.height))}`,
                98,
              )
            "
          />
        </template>

        <template v-else>
          <VLine :config="shapeConfig([140, 242, 390, 242, 330, 78, 80, 78])" />
          <VLine :config="helperLineConfig([330, 78, 330, 242])" />

          <VLine :config="measureLineConfig([140, 264, 390, 264])" />
          <VLine :config="helperLineConfig([140, 242, 140, 272])" />
          <VLine :config="helperLineConfig([390, 242, 390, 272])" />
          <VCircle :config="pointConfig(140, 264)" />
          <VCircle :config="pointConfig(390, 264)" />
          <VRect :config="labelBoxConfig(265, 276, 104)" />
          <VText
            :config="
              labelTextConfig(
                265,
                276,
                `b = ${valueOrDash(asPositive(inputs.parallelogram.base))}`,
                104,
              )
            "
          />

          <VCircle :config="pointConfig(330, 78)" />
          <VCircle :config="pointConfig(330, 242)" />
          <VRect :config="labelBoxConfig(378, 146, 98)" />
          <VText
            :config="
              labelTextConfig(
                378,
                146,
                `h = ${valueOrDash(asPositive(inputs.parallelogram.height))}`,
                98,
              )
            "
          />
        </template>
      </VLayer>
    </VStage>
  </div>
</template>
