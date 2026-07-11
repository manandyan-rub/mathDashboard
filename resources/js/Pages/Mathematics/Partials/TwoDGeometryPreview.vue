<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import "konva/lib/shapes/Arc";
import "konva/lib/shapes/Circle";
import "konva/lib/shapes/Line";
import "konva/lib/shapes/Rect";
import "konva/lib/shapes/Text";
import {
  Arc as VArc,
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

const VIRTUAL_WIDTH = 520;
const VIRTUAL_HEIGHT = 320;
const PADDING_X = 66;
const PADDING_Y = 42;

const shell = ref<HTMLElement | null>(null);
const size = ref({ width: VIRTUAL_WIDTH, height: VIRTUAL_HEIGHT });
const { theme } = useTheme();
let resizeObserver: ResizeObserver | null = null;

const stageConfig = computed(() => ({
  width: Math.max(320, Math.round(size.value.width)),
  height: Math.max(220, Math.round(size.value.height)),
}));

const stageScale = computed(() =>
  Math.min(
    stageConfig.value.width / VIRTUAL_WIDTH,
    stageConfig.value.height / VIRTUAL_HEIGHT,
  ),
);

const labelFontSize = computed(() =>
  Math.max(11, Math.round(12.5 * stageScale.value)),
);
const vertexFontSize = computed(() =>
  Math.max(10, Math.round(11 * stageScale.value)),
);
const labelHeight = computed(() => Math.max(24, Math.round(26 * stageScale.value)));

const colors = computed(() => {
  theme.value;

  return {
    accent: themeColor(props.accentColor, "#6366f1"),
    accentSoft: themeColorAlpha(
      props.accentColor,
      0.14,
      "rgba(99, 102, 241, 0.14)",
    ),
    accentMuted: themeColorAlpha(
      props.accentColor,
      0.52,
      "rgba(99, 102, 241, 0.52)",
    ),
    background: themeColor("--background", "#f7f9ff"),
    border: themeColorAlpha("--border", 0.82, "rgba(148, 163, 184, 0.38)"),
    foreground: themeColor("--foreground", "#0f172a"),
    muted: themeColorAlpha("--foreground", 0.48, "rgba(15, 23, 42, 0.48)"),
    guide: themeColorAlpha("--foreground", 0.14, "rgba(15, 23, 42, 0.14)"),
    labelBg: themeColor("--card", "#ffffff"),
    angle: themeColorAlpha(props.accentColor, 0.22, "rgba(99, 102, 241, 0.22)"),
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
  const gap = Math.max(32, 42 * stageScale.value);
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

const rectanglePreview = computed(() => {
  const length = asPositive(props.inputs.rectangle.length) ?? 12;
  const width = asPositive(props.inputs.rectangle.width) ?? 8;
  const scale = fitScale([length, width], magnitudeFactor([length, width], 20));
  const drawWidth = length * scale;
  const drawHeight = width * scale;
  const left = (VIRTUAL_WIDTH - drawWidth) / 2;
  const top = (VIRTUAL_HEIGHT - drawHeight) / 2;
  const right = left + drawWidth;
  const bottom = top + drawHeight;

  return {
    left,
    top,
    right,
    bottom,
    measureBottomY: bottom + 24,
    measureRightX: right + 24,
    length,
    width,
  };
});

const trianglePreview = computed(() => {
  const sideA = asPositive(props.inputs.triangle.sideA);
  const sideB = asPositive(props.inputs.triangle.sideB);
  const sideC = asPositive(props.inputs.triangle.sideC);
  const base = asPositive(props.inputs.triangle.base);
  const height = asPositive(props.inputs.triangle.height);

  const hasValidSides =
    sideA !== null &&
    sideB !== null &&
    sideC !== null &&
    sideA + sideB > sideC &&
    sideA + sideC > sideB &&
    sideB + sideC > sideA;

  let rawBase = base ?? sideC ?? 8;
  let rawHeight = height ?? 6;
  let apexX = rawBase / 2;

  if (hasValidSides && sideA !== null && sideB !== null && sideC !== null) {
    rawBase = sideC;
    apexX = (sideA ** 2 - sideB ** 2 + sideC ** 2) / (2 * sideC);
    rawHeight = Math.sqrt(Math.max(sideA ** 2 - apexX ** 2, 0));
  }

  const scale = fitScale(
    [Math.max(rawBase, 1), Math.max(rawHeight, 1)],
    magnitudeFactor([rawBase, rawHeight, sideA ?? 0, sideB ?? 0], 18),
  );

  const drawBase = rawBase * scale;
  const drawHeight = rawHeight * scale;
  const left = (VIRTUAL_WIDTH - drawBase) / 2;
  const bottom = (VIRTUAL_HEIGHT + drawHeight) / 2;
  const top = bottom - drawHeight;
  const apex = {
    x: left + apexX * scale,
    y: top,
  };
  const leftBase = { x: left, y: bottom };
  const rightBase = { x: left + drawBase, y: bottom };
  const altitudeFoot = { x: apex.x, y: bottom };

  return {
    hasValidSides,
    apex,
    leftBase,
    rightBase,
    altitudeFoot,
    base: base ?? rawBase,
    height: height ?? rawHeight,
    sideA,
    sideB,
    sideC: sideC ?? rawBase,
    angles: hasValidSides && sideA !== null && sideB !== null && sideC !== null
      ? triangleAngles(sideA, sideB, sideC)
      : null,
  };
});

const circlePreview = computed(() => {
  const radius =
    asPositive(props.inputs.circle.radius) ??
    ((asPositive(props.inputs.circle.diameter) ?? 0) > 0
      ? (asPositive(props.inputs.circle.diameter) ?? 0) / 2
      : 6);
  const normalized = magnitudeFactor([radius], 18);
  const drawRadius = 42 + normalized * 64;
  const centerX = VIRTUAL_WIDTH / 2;
  const centerY = VIRTUAL_HEIGHT / 2 - 6;

  return {
    centerX,
    centerY,
    drawRadius,
    radius,
    diameter: radius * 2,
  };
});

const trapezoidPreview = computed(() => {
  const baseA = asPositive(props.inputs.trapezoid.baseA) ?? 10;
  const baseB = asPositive(props.inputs.trapezoid.baseB) ?? 14;
  const height = asPositive(props.inputs.trapezoid.height) ?? 6;
  const sideC = asPositive(props.inputs.trapezoid.sideC);
  const sideD = asPositive(props.inputs.trapezoid.sideD);

  const scale = fitScale(
    [Math.max(baseA, baseB), height],
    magnitudeFactor([baseA, baseB, height], 18),
  );

  const topWidth = baseA * scale;
  const bottomWidth = baseB * scale;
  const drawHeight = height * scale;
  const centerX = VIRTUAL_WIDTH / 2;
  const bottomY = (VIRTUAL_HEIGHT + drawHeight) / 2;
  const topY = bottomY - drawHeight;
  const topLeftX = centerX - topWidth / 2;
  const bottomLeftX = centerX - bottomWidth / 2;
  const topRightX = centerX + topWidth / 2;
  const bottomRightX = centerX + bottomWidth / 2;
  const offset = Math.abs(topLeftX - bottomLeftX);
  const angle =
    offset > 0 ? Math.round((Math.atan2(drawHeight, offset) * 180) / Math.PI) : 90;

  return {
    topLeft: { x: topLeftX, y: topY },
    topRight: { x: topRightX, y: topY },
    bottomLeft: { x: bottomLeftX, y: bottomY },
    bottomRight: { x: bottomRightX, y: bottomY },
    baseA,
    baseB,
    height,
    sideC,
    sideD,
    angle,
  };
});

const parallelogramPreview = computed(() => {
  const base = asPositive(props.inputs.parallelogram.base) ?? 12;
  const side = asPositive(props.inputs.parallelogram.side) ?? 7;
  const height = asPositive(props.inputs.parallelogram.height) ?? 4.5;
  const horizontalOffset =
    side > height ? Math.sqrt(Math.max(side ** 2 - height ** 2, 0)) : base * 0.18;

  const scale = fitScale(
    [base + horizontalOffset, height],
    magnitudeFactor([base, side, height], 18),
  );
  const drawBase = base * scale;
  const drawHeight = height * scale;
  const drawOffset = horizontalOffset * scale;
  const left = (VIRTUAL_WIDTH - (drawBase + drawOffset)) / 2;
  const bottom = (VIRTUAL_HEIGHT + drawHeight) / 2;
  const top = bottom - drawHeight;
  const angle =
    side > height
      ? Math.round((Math.asin(Math.min(height / side, 1)) * 180) / Math.PI)
      : 90;

  return {
    topLeft: { x: left + drawOffset, y: top },
    topRight: { x: left + drawOffset + drawBase, y: top },
    bottomLeft: { x: left, y: bottom },
    bottomRight: { x: left + drawBase, y: bottom },
    heightFoot: { x: left + drawOffset, y: bottom },
    base,
    side,
    height,
    angle,
  };
});

const circleConfig = computed(() => ({
  x: sx(circlePreview.value.centerX),
  y: sy(circlePreview.value.centerY),
  radius: Math.min(sx(circlePreview.value.drawRadius), sy(circlePreview.value.drawRadius)),
  fill: colors.value.accentSoft,
  stroke: colors.value.accent,
  strokeWidth: Math.max(2.5, 3 * stageScale.value),
  listening: false,
}));

onMounted(() => {
  const updateSize = () => {
    if (!shell.value) return;
    size.value = {
      width: shell.value.clientWidth || VIRTUAL_WIDTH,
      height: shell.value.clientHeight || VIRTUAL_HEIGHT,
    };
  };

  updateSize();
  resizeObserver = new ResizeObserver(updateSize);
  if (shell.value) resizeObserver.observe(shell.value);
});

onBeforeUnmount(() => {
  resizeObserver?.disconnect();
});

function asPositive(value: NumericLike): NullableNumber {
  if (value === "" || value === null || value === undefined) return null;
  const parsed = Number(value);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
}

function clamp(value: number, min: number, max: number): number {
  return Math.min(max, Math.max(min, value));
}

function sx(value: number): number {
  return (value / VIRTUAL_WIDTH) * stageConfig.value.width;
}

function sy(value: number): number {
  return (value / VIRTUAL_HEIGHT) * stageConfig.value.height;
}

function points(values: number[]): number[] {
  return values.map((value, index) => (index % 2 === 0 ? sx(value) : sy(value)));
}

function fitScale(dimensions: number[], magnitude = 0.8): number {
  const maxWidth = (VIRTUAL_WIDTH - PADDING_X * 2) * magnitude;
  const maxHeight = (VIRTUAL_HEIGHT - PADDING_Y * 2) * magnitude;
  const width = Math.max(dimensions[0] || 1, 1);
  const height = Math.max(dimensions[1] || 1, 1);

  return Math.min(maxWidth / width, maxHeight / height);
}

function magnitudeFactor(values: number[], reference = 18): number {
  const maxValue = Math.max(1, ...values.filter((value) => value > 0));
  const ratio = clamp(
    Math.log10(maxValue + 1) / Math.log10(reference + 1),
    0,
    1,
  );

  return 0.55 + ratio * 0.35;
}

function shapeConfig(rawPoints: number[]) {
  return {
    points: points(rawPoints),
    closed: true,
    fill: colors.value.accentSoft,
    stroke: colors.value.accent,
    strokeWidth: Math.max(2.5, 3 * stageScale.value),
    lineCap: "round",
    lineJoin: "round",
    listening: false,
  };
}

function helperLineConfig(rawPoints: number[], dashed = true) {
  return {
    points: points(rawPoints),
    stroke: colors.value.guide,
    strokeWidth: Math.max(1, 1.25 * stageScale.value),
    dash: dashed ? [6 * stageScale.value, 6 * stageScale.value] : [],
    lineCap: "round",
    lineJoin: "round",
    listening: false,
  };
}

function measureLineConfig(rawPoints: number[]) {
  return {
    points: points(rawPoints),
    stroke: colors.value.accentMuted,
    strokeWidth: Math.max(1.5, 2 * stageScale.value),
    lineCap: "round",
    lineJoin: "round",
    listening: false,
  };
}

function pointConfig(x: number, y: number) {
  return {
    x: sx(x),
    y: sy(y),
    radius: Math.max(2.5, 3.5 * stageScale.value),
    fill: colors.value.background,
    stroke: colors.value.accent,
    strokeWidth: Math.max(1.5, 2 * stageScale.value),
    listening: false,
  };
}

function labelBoxConfig(x: number, y: number, width = 112) {
  const scaledWidth = width * stageScale.value;

  return {
    x: sx(x) - scaledWidth / 2,
    y: sy(y),
    width: scaledWidth,
    height: labelHeight.value,
    cornerRadius: Math.max(7, 8 * stageScale.value),
    fill: colors.value.labelBg,
    stroke: colors.value.border,
    strokeWidth: 1,
    listening: false,
  };
}

function labelTextConfig(x: number, y: number, text: string, width = 112) {
  const scaledWidth = width * stageScale.value;

  return {
    x: sx(x) - scaledWidth / 2,
    y: sy(y) + Math.max(5, 6 * stageScale.value),
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

function vertexTextConfig(x: number, y: number, text: string) {
  return {
    x: sx(x) - 10 * stageScale.value,
    y: sy(y) - 10 * stageScale.value,
    width: 20 * stageScale.value,
    align: "center",
    text,
    fill: colors.value.accent,
    fontFamily: "Inter, ui-sans-serif, system-ui",
    fontSize: vertexFontSize.value,
    fontStyle: "bold",
    listening: false,
  };
}

function rightAngleConfig(x: number, y: number, size = 14) {
  return {
    points: points([x, y + size, x, y, x + size, y]),
    stroke: colors.value.accentMuted,
    strokeWidth: Math.max(1.5, 2 * stageScale.value),
    lineCap: "round",
    lineJoin: "round",
    listening: false,
  };
}

function angleConfig(
  x: number,
  y: number,
  radius: number,
  angle: number,
  rotation: number,
) {
  return {
    x: sx(x),
    y: sy(y),
    innerRadius: Math.max(12, radius * stageScale.value),
    outerRadius: Math.max(15, (radius + 3) * stageScale.value),
    angle,
    rotation,
    fill: colors.value.angle,
    stroke: colors.value.accentMuted,
    strokeWidth: Math.max(1, 1.25 * stageScale.value),
    listening: false,
  };
}

function lineMidpoint(x1: number, y1: number, x2: number, y2: number, offset = 0) {
  const dx = x2 - x1;
  const dy = y2 - y1;
  const length = Math.hypot(dx, dy) || 1;
  const normalX = (-dy / length) * offset;
  const normalY = (dx / length) * offset;

  return {
    x: (x1 + x2) / 2 + normalX,
    y: (y1 + y2) / 2 + normalY,
  };
}

function triangleAngles(a: number, b: number, c: number) {
  const angleA = radiansToDegrees(Math.acos(clamp((b ** 2 + c ** 2 - a ** 2) / (2 * b * c), -1, 1)));
  const angleB = radiansToDegrees(Math.acos(clamp((a ** 2 + c ** 2 - b ** 2) / (2 * a * c), -1, 1)));
  const angleC = 180 - angleA - angleB;

  return {
    A: Math.round(angleA),
    B: Math.round(angleB),
    C: Math.round(angleC),
  };
}

function radiansToDegrees(value: number): number {
  return (value * 180) / Math.PI;
}

function valueOrDash(value: NullableNumber): string {
  if (value === null) {
    return "-";
  }

  const rounded =
    Math.abs(value) >= 100 ? Number(value.toFixed(2)) : Number(value.toFixed(3));

  return new Intl.NumberFormat(undefined, {
    maximumFractionDigits: Math.abs(value) >= 100 ? 2 : 3,
  }).format(rounded);
}
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
          <VLine
            :config="
              shapeConfig([
                rectanglePreview.left,
                rectanglePreview.top,
                rectanglePreview.right,
                rectanglePreview.top,
                rectanglePreview.right,
                rectanglePreview.bottom,
                rectanglePreview.left,
                rectanglePreview.bottom,
              ])
            "
          />
          <VLine
            :config="
              helperLineConfig([
                rectanglePreview.left,
                rectanglePreview.top,
                rectanglePreview.right,
                rectanglePreview.bottom,
              ], false)
            "
          />

          <VLine
            :config="rightAngleConfig(rectanglePreview.left, rectanglePreview.top)"
          />
          <VLine
            :config="rightAngleConfig(rectanglePreview.right - 14, rectanglePreview.top)"
          />

          <VLine
            :config="
              measureLineConfig([
                rectanglePreview.left,
                rectanglePreview.measureBottomY,
                rectanglePreview.right,
                rectanglePreview.measureBottomY,
              ])
            "
          />
          <VLine
            :config="
              helperLineConfig([
                rectanglePreview.left,
                rectanglePreview.bottom,
                rectanglePreview.left,
                rectanglePreview.measureBottomY + 8,
              ])
            "
          />
          <VLine
            :config="
              helperLineConfig([
                rectanglePreview.right,
                rectanglePreview.bottom,
                rectanglePreview.right,
                rectanglePreview.measureBottomY + 8,
              ])
            "
          />
          <VCircle :config="pointConfig(rectanglePreview.left, rectanglePreview.measureBottomY)" />
          <VCircle :config="pointConfig(rectanglePreview.right, rectanglePreview.measureBottomY)" />
          <VRect
            :config="
              labelBoxConfig(
                (rectanglePreview.left + rectanglePreview.right) / 2,
                rectanglePreview.measureBottomY + 10,
                116,
              )
            "
          />
          <VText
            :config="
              labelTextConfig(
                (rectanglePreview.left + rectanglePreview.right) / 2,
                rectanglePreview.measureBottomY + 10,
                `l = ${valueOrDash(rectanglePreview.length)}`,
                116,
              )
            "
          />

          <VLine
            :config="
              measureLineConfig([
                rectanglePreview.measureRightX,
                rectanglePreview.top,
                rectanglePreview.measureRightX,
                rectanglePreview.bottom,
              ])
            "
          />
          <VLine
            :config="
              helperLineConfig([
                rectanglePreview.right,
                rectanglePreview.top,
                rectanglePreview.measureRightX + 8,
                rectanglePreview.top,
              ])
            "
          />
          <VLine
            :config="
              helperLineConfig([
                rectanglePreview.right,
                rectanglePreview.bottom,
                rectanglePreview.measureRightX + 8,
                rectanglePreview.bottom,
              ])
            "
          />
          <VCircle :config="pointConfig(rectanglePreview.measureRightX, rectanglePreview.top)" />
          <VCircle :config="pointConfig(rectanglePreview.measureRightX, rectanglePreview.bottom)" />
          <VRect
            :config="
              labelBoxConfig(
                rectanglePreview.measureRightX + 30,
                (rectanglePreview.top + rectanglePreview.bottom) / 2 - 12,
                104,
              )
            "
          />
          <VText
            :config="
              labelTextConfig(
                rectanglePreview.measureRightX + 30,
                (rectanglePreview.top + rectanglePreview.bottom) / 2 - 12,
                `w = ${valueOrDash(rectanglePreview.width)}`,
                104,
              )
            "
          />
        </template>

        <template v-else-if="shape === 'triangle'">
          <VLine
            :config="
              shapeConfig([
                trianglePreview.leftBase.x,
                trianglePreview.leftBase.y,
                trianglePreview.rightBase.x,
                trianglePreview.rightBase.y,
                trianglePreview.apex.x,
                trianglePreview.apex.y,
              ])
            "
          />
          <VLine
            :config="
              helperLineConfig([
                trianglePreview.apex.x,
                trianglePreview.apex.y,
                trianglePreview.altitudeFoot.x,
                trianglePreview.altitudeFoot.y,
              ])
            "
          />
          <VLine
            :config="
              rightAngleConfig(
                trianglePreview.altitudeFoot.x,
                trianglePreview.altitudeFoot.y - 14,
                14,
              )
            "
          />

          <VCircle :config="pointConfig(trianglePreview.leftBase.x, trianglePreview.leftBase.y)" />
          <VCircle :config="pointConfig(trianglePreview.rightBase.x, trianglePreview.rightBase.y)" />
          <VCircle :config="pointConfig(trianglePreview.apex.x, trianglePreview.apex.y)" />

          <VText :config="vertexTextConfig(trianglePreview.leftBase.x - 16, trianglePreview.leftBase.y + 6, 'A')" />
          <VText :config="vertexTextConfig(trianglePreview.rightBase.x + 16, trianglePreview.rightBase.y + 6, 'B')" />
          <VText :config="vertexTextConfig(trianglePreview.apex.x, trianglePreview.apex.y - 18, 'C')" />

          <VRect
            :config="
              labelBoxConfig(
                (trianglePreview.leftBase.x + trianglePreview.rightBase.x) / 2,
                trianglePreview.leftBase.y + 18,
                144,
              )
            "
          />
          <VText
            :config="
              labelTextConfig(
                (trianglePreview.leftBase.x + trianglePreview.rightBase.x) / 2,
                trianglePreview.leftBase.y + 18,
                `${__('Base')} = ${valueOrDash(trianglePreview.base)}`,
                144,
              )
            "
          />

          <VRect
            :config="
              labelBoxConfig(
                trianglePreview.altitudeFoot.x + 40,
                (trianglePreview.apex.y + trianglePreview.altitudeFoot.y) / 2 - 12,
                112,
              )
            "
          />
          <VText
            :config="
              labelTextConfig(
                trianglePreview.altitudeFoot.x + 40,
                (trianglePreview.apex.y + trianglePreview.altitudeFoot.y) / 2 - 12,
                `h = ${valueOrDash(trianglePreview.height)}`,
                112,
              )
            "
          />

          <template v-if="trianglePreview.sideA !== null && trianglePreview.sideB !== null && trianglePreview.sideC !== null">
            <VRect
              :config="
                (() => {
                  const point = lineMidpoint(
                    trianglePreview.leftBase.x,
                    trianglePreview.leftBase.y,
                    trianglePreview.apex.x,
                    trianglePreview.apex.y,
                    -20,
                  );
                  return labelBoxConfig(point.x, point.y, 84);
                })()
              "
            />
            <VText
              :config="
                (() => {
                  const point = lineMidpoint(
                    trianglePreview.leftBase.x,
                    trianglePreview.leftBase.y,
                    trianglePreview.apex.x,
                    trianglePreview.apex.y,
                    -20,
                  );
                  return labelTextConfig(
                    point.x,
                    point.y,
                    `a = ${valueOrDash(trianglePreview.sideA)}`,
                    84,
                  );
                })()
              "
            />

            <VRect
              :config="
                (() => {
                  const point = lineMidpoint(
                    trianglePreview.rightBase.x,
                    trianglePreview.rightBase.y,
                    trianglePreview.apex.x,
                    trianglePreview.apex.y,
                    18,
                  );
                  return labelBoxConfig(point.x, point.y, 84);
                })()
              "
            />
            <VText
              :config="
                (() => {
                  const point = lineMidpoint(
                    trianglePreview.rightBase.x,
                    trianglePreview.rightBase.y,
                    trianglePreview.apex.x,
                    trianglePreview.apex.y,
                    18,
                  );
                  return labelTextConfig(
                    point.x,
                    point.y,
                    `b = ${valueOrDash(trianglePreview.sideB)}`,
                    84,
                  );
                })()
              "
            />

            <template v-if="trianglePreview.angles">
              <VText
                :config="
                  labelTextConfig(
                    trianglePreview.leftBase.x + 22,
                    trianglePreview.leftBase.y - 28,
                    `${trianglePreview.angles.A} deg`,
                    70,
                  )
                "
              />
              <VText
                :config="
                  labelTextConfig(
                    trianglePreview.rightBase.x - 22,
                    trianglePreview.rightBase.y - 28,
                    `${trianglePreview.angles.B} deg`,
                    70,
                  )
                "
              />
              <VText
                :config="
                  labelTextConfig(
                    trianglePreview.apex.x,
                    trianglePreview.apex.y + 12,
                    `${trianglePreview.angles.C} deg`,
                    70,
                  )
                "
              />
            </template>
          </template>
        </template>

        <template v-else-if="shape === 'circle'">
          <VCircle :config="circleConfig" />
          <VCircle :config="pointConfig(circlePreview.centerX, circlePreview.centerY)" />

          <VLine
            :config="
              measureLineConfig([
                circlePreview.centerX,
                circlePreview.centerY,
                circlePreview.centerX + circlePreview.drawRadius,
                circlePreview.centerY,
              ])
            "
          />
          <VCircle
            :config="
              pointConfig(
                circlePreview.centerX + circlePreview.drawRadius,
                circlePreview.centerY,
              )
            "
          />
          <VRect
            :config="
              labelBoxConfig(
                circlePreview.centerX + circlePreview.drawRadius / 2,
                circlePreview.centerY - circlePreview.drawRadius - 22,
                96,
              )
            "
          />
          <VText
            :config="
              labelTextConfig(
                circlePreview.centerX + circlePreview.drawRadius / 2,
                circlePreview.centerY - circlePreview.drawRadius - 22,
                `r = ${valueOrDash(circlePreview.radius)}`,
                96,
              )
            "
          />

          <VLine
            :config="
              measureLineConfig([
                circlePreview.centerX - circlePreview.drawRadius,
                circlePreview.centerY + circlePreview.drawRadius + 26,
                circlePreview.centerX + circlePreview.drawRadius,
                circlePreview.centerY + circlePreview.drawRadius + 26,
              ])
            "
          />
          <VLine
            :config="
              helperLineConfig([
                circlePreview.centerX - circlePreview.drawRadius,
                circlePreview.centerY,
                circlePreview.centerX - circlePreview.drawRadius,
                circlePreview.centerY + circlePreview.drawRadius + 34,
              ])
            "
          />
          <VLine
            :config="
              helperLineConfig([
                circlePreview.centerX + circlePreview.drawRadius,
                circlePreview.centerY,
                circlePreview.centerX + circlePreview.drawRadius,
                circlePreview.centerY + circlePreview.drawRadius + 34,
              ])
            "
          />
          <VCircle
            :config="
              pointConfig(
                circlePreview.centerX - circlePreview.drawRadius,
                circlePreview.centerY + circlePreview.drawRadius + 26,
              )
            "
          />
          <VCircle
            :config="
              pointConfig(
                circlePreview.centerX + circlePreview.drawRadius,
                circlePreview.centerY + circlePreview.drawRadius + 26,
              )
            "
          />
          <VRect
            :config="
              labelBoxConfig(
                circlePreview.centerX,
                circlePreview.centerY + circlePreview.drawRadius + 36,
                116,
              )
            "
          />
          <VText
            :config="
              labelTextConfig(
                circlePreview.centerX,
                circlePreview.centerY + circlePreview.drawRadius + 36,
                `d = ${valueOrDash(circlePreview.diameter)}`,
                116,
              )
            "
          />
        </template>

        <template v-else-if="shape === 'trapezoid'">
          <VLine
            :config="
              shapeConfig([
                trapezoidPreview.bottomLeft.x,
                trapezoidPreview.bottomLeft.y,
                trapezoidPreview.bottomRight.x,
                trapezoidPreview.bottomRight.y,
                trapezoidPreview.topRight.x,
                trapezoidPreview.topRight.y,
                trapezoidPreview.topLeft.x,
                trapezoidPreview.topLeft.y,
              ])
            "
          />
          <VLine
            :config="
              helperLineConfig([
                (trapezoidPreview.topLeft.x + trapezoidPreview.topRight.x) / 2,
                trapezoidPreview.topLeft.y,
                (trapezoidPreview.topLeft.x + trapezoidPreview.topRight.x) / 2,
                trapezoidPreview.bottomLeft.y,
              ])
            "
          />

          <VArc
            :config="
              angleConfig(
                trapezoidPreview.bottomLeft.x + 8,
                trapezoidPreview.bottomLeft.y - 4,
                10,
                trapezoidPreview.angle,
                -trapezoidPreview.angle,
              )
            "
          />

          <VRect
            :config="
              labelBoxConfig(
                (trapezoidPreview.topLeft.x + trapezoidPreview.topRight.x) / 2,
                trapezoidPreview.topLeft.y - 30,
                104,
              )
            "
          />
          <VText
            :config="
              labelTextConfig(
                (trapezoidPreview.topLeft.x + trapezoidPreview.topRight.x) / 2,
                trapezoidPreview.topLeft.y - 30,
                `a = ${valueOrDash(trapezoidPreview.baseA)}`,
                104,
              )
            "
          />

          <VRect
            :config="
              labelBoxConfig(
                (trapezoidPreview.bottomLeft.x + trapezoidPreview.bottomRight.x) / 2,
                trapezoidPreview.bottomLeft.y + 18,
                104,
              )
            "
          />
          <VText
            :config="
              labelTextConfig(
                (trapezoidPreview.bottomLeft.x + trapezoidPreview.bottomRight.x) / 2,
                trapezoidPreview.bottomLeft.y + 18,
                `b = ${valueOrDash(trapezoidPreview.baseB)}`,
                104,
              )
            "
          />

          <VRect
            :config="
              labelBoxConfig(
                (trapezoidPreview.topLeft.x + trapezoidPreview.topRight.x) / 2 + 42,
                (trapezoidPreview.topLeft.y + trapezoidPreview.bottomLeft.y) / 2 - 12,
                104,
              )
            "
          />
          <VText
            :config="
              labelTextConfig(
                (trapezoidPreview.topLeft.x + trapezoidPreview.topRight.x) / 2 + 42,
                (trapezoidPreview.topLeft.y + trapezoidPreview.bottomLeft.y) / 2 - 12,
                `h = ${valueOrDash(trapezoidPreview.height)}`,
                104,
              )
            "
          />

          <template v-if="trapezoidPreview.sideC !== null">
            <VRect
              :config="
                (() => {
                  const point = lineMidpoint(
                    trapezoidPreview.bottomLeft.x,
                    trapezoidPreview.bottomLeft.y,
                    trapezoidPreview.topLeft.x,
                    trapezoidPreview.topLeft.y,
                    -18,
                  );
                  return labelBoxConfig(point.x, point.y, 82);
                })()
              "
            />
            <VText
              :config="
                (() => {
                  const point = lineMidpoint(
                    trapezoidPreview.bottomLeft.x,
                    trapezoidPreview.bottomLeft.y,
                    trapezoidPreview.topLeft.x,
                    trapezoidPreview.topLeft.y,
                    -18,
                  );
                  return labelTextConfig(
                    point.x,
                    point.y,
                    `c = ${valueOrDash(trapezoidPreview.sideC)}`,
                    82,
                  );
                })()
              "
            />
          </template>

          <template v-if="trapezoidPreview.sideD !== null">
            <VRect
              :config="
                (() => {
                  const point = lineMidpoint(
                    trapezoidPreview.bottomRight.x,
                    trapezoidPreview.bottomRight.y,
                    trapezoidPreview.topRight.x,
                    trapezoidPreview.topRight.y,
                    18,
                  );
                  return labelBoxConfig(point.x, point.y, 82);
                })()
              "
            />
            <VText
              :config="
                (() => {
                  const point = lineMidpoint(
                    trapezoidPreview.bottomRight.x,
                    trapezoidPreview.bottomRight.y,
                    trapezoidPreview.topRight.x,
                    trapezoidPreview.topRight.y,
                    18,
                  );
                  return labelTextConfig(
                    point.x,
                    point.y,
                    `d = ${valueOrDash(trapezoidPreview.sideD)}`,
                    82,
                  );
                })()
              "
            />
          </template>
        </template>

        <template v-else>
          <VLine
            :config="
              shapeConfig([
                parallelogramPreview.bottomLeft.x,
                parallelogramPreview.bottomLeft.y,
                parallelogramPreview.bottomRight.x,
                parallelogramPreview.bottomRight.y,
                parallelogramPreview.topRight.x,
                parallelogramPreview.topRight.y,
                parallelogramPreview.topLeft.x,
                parallelogramPreview.topLeft.y,
              ])
            "
          />
          <VLine
            :config="
              helperLineConfig([
                parallelogramPreview.topLeft.x,
                parallelogramPreview.topLeft.y,
                parallelogramPreview.heightFoot.x,
                parallelogramPreview.heightFoot.y,
              ])
            "
          />
          <VArc
            :config="
              angleConfig(
                parallelogramPreview.bottomLeft.x + 8,
                parallelogramPreview.bottomLeft.y - 4,
                10,
                parallelogramPreview.angle,
                -parallelogramPreview.angle,
              )
            "
          />

          <VRect
            :config="
              labelBoxConfig(
                (parallelogramPreview.bottomLeft.x + parallelogramPreview.bottomRight.x) / 2,
                parallelogramPreview.bottomLeft.y + 18,
                104,
              )
            "
          />
          <VText
            :config="
              labelTextConfig(
                (parallelogramPreview.bottomLeft.x + parallelogramPreview.bottomRight.x) / 2,
                parallelogramPreview.bottomLeft.y + 18,
                `b = ${valueOrDash(parallelogramPreview.base)}`,
                104,
              )
            "
          />

          <VRect
            :config="
              labelBoxConfig(
                parallelogramPreview.topLeft.x + 40,
                (parallelogramPreview.topLeft.y + parallelogramPreview.heightFoot.y) / 2 - 12,
                104,
              )
            "
          />
          <VText
            :config="
              labelTextConfig(
                parallelogramPreview.topLeft.x + 40,
                (parallelogramPreview.topLeft.y + parallelogramPreview.heightFoot.y) / 2 - 12,
                `h = ${valueOrDash(parallelogramPreview.height)}`,
                104,
              )
            "
          />

          <VRect
            :config="
              (() => {
                const point = lineMidpoint(
                  parallelogramPreview.bottomLeft.x,
                  parallelogramPreview.bottomLeft.y,
                  parallelogramPreview.topLeft.x,
                  parallelogramPreview.topLeft.y,
                  -18,
                );
                return labelBoxConfig(point.x, point.y, 96);
              })()
            "
          />
          <VText
            :config="
              (() => {
                const point = lineMidpoint(
                  parallelogramPreview.bottomLeft.x,
                  parallelogramPreview.bottomLeft.y,
                  parallelogramPreview.topLeft.x,
                  parallelogramPreview.topLeft.y,
                  -18,
                );
                return labelTextConfig(
                  point.x,
                  point.y,
                  `s = ${valueOrDash(parallelogramPreview.side)}`,
                  96,
                );
              })()
            "
          />

          <VText
            :config="
              labelTextConfig(
                parallelogramPreview.bottomLeft.x + 28,
                parallelogramPreview.bottomLeft.y - 28,
                `${parallelogramPreview.angle} deg`,
                74,
              )
            "
          />
        </template>
      </VLayer>
    </VStage>
  </div>
</template>
