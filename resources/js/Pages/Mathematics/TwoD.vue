<script setup lang="ts">
import { computed, reactive, ref, type Component } from "vue";
import AdminFormLayout from "@/Layouts/AdminFormLayout.vue";
import FormInput from "@/Forms/FormInput.vue";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { cmsTranslate as __ } from "@/lib/i18n";
import TwoDGeometryPreview from "./Partials/TwoDGeometryPreview.vue";
import type {
  NullableNumber,
  NumericLike,
  TwoDCalculation,
  TwoDInputs,
  TwoDShapeKey,
} from "@/types/twoDGeometry";
import {
  Box,
  Calculator,
  Circle,
  Ruler,
  Sparkles,
  Square,
  Triangle,
} from "lucide-vue-next";

type ShapeOption = {
  value: TwoDShapeKey;
  label: string;
  metric: string;
  formula: string;
  icon: Component;
  color: string;
};

const shape = ref<TwoDShapeKey>("rectangle");
const inputsHidden = ref(false);
const note = ref("");
const result = ref<TwoDCalculation | null>(null);

const inputs = reactive<TwoDInputs>({
  rectangle: {
    length: 12,
    width: 8,
  },
  triangle: {
    sideA: 5,
    sideB: 6,
    sideC: 7,
    base: "",
    height: "",
  },
  circle: {
    radius: 6,
    diameter: "",
  },
  trapezoid: {
    baseA: 12,
    baseB: 7,
    height: 5,
    sideC: 6,
    sideD: 6,
  },
  parallelogram: {
    base: 10,
    side: 6,
    height: 4,
  },
});

const shapeOptions: ShapeOption[] = [
  {
    value: "rectangle",
    label: "Rectangle",
    metric: "l, w",
    formula: "A = l x w",
    icon: Square,
    color: "var(--primary)",
  },
  {
    value: "triangle",
    label: "Triangle",
    metric: "a, b, c / b, h",
    formula: "A = bh / 2",
    icon: Triangle,
    color: "var(--chart-2)",
  },
  {
    value: "circle",
    label: "Circle",
    metric: "r, d",
    formula: "A = pi r^2",
    icon: Circle,
    color: "var(--chart-5)",
  },
  {
    value: "trapezoid",
    label: "Trapezoid",
    metric: "a, b, h",
    formula: "A = (a + b)h / 2",
    icon: Box,
    color: "var(--chart-3)",
  },
  {
    value: "parallelogram",
    label: "Parallelogram",
    metric: "b, s, h",
    formula: "A = bh",
    icon: Ruler,
    color: "var(--destructive)",
  },
];

const activeShape = computed(
  () =>
    shapeOptions.find((item) => item.value === shape.value) ?? shapeOptions[0],
);

const round = (value: number, digits = 4): number =>
  Number(value.toFixed(digits));

const asPositive = (value: NumericLike): NullableNumber => {
  if (value === "" || value === null || value === undefined) return null;
  const parsed = Number(value);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
};

const resultHasData = computed(
  () =>
    result.value !== null &&
    result.value.items.some((item) => item.value !== null),
);

function makeResult(
  title: string,
  formula: string,
  items: TwoDCalculation["items"],
  resultNote = "",
): TwoDCalculation {
  return {
    title,
    formula,
    note: resultNote,
    items,
  };
}

function calculateRectangle(): TwoDCalculation {
  const length = asPositive(inputs.rectangle.length);
  const width = asPositive(inputs.rectangle.width);
  const area = length && width ? length * width : null;
  const perimeter = length && width ? 2 * (length + width) : null;
  const diagonal = length && width ? Math.sqrt(length ** 2 + width ** 2) : null;

  return makeResult("Rectangle", "A = l x w", [
    { label: "Area", value: area === null ? null : round(area) },
    { label: "Perimeter", value: perimeter === null ? null : round(perimeter) },
    { label: "Diagonal", value: diagonal === null ? null : round(diagonal) },
    { label: "Length", value: length },
    { label: "Width", value: width },
  ]);
}

function calculateTriangle(): TwoDCalculation {
  const sideA = asPositive(inputs.triangle.sideA);
  const sideB = asPositive(inputs.triangle.sideB);
  const sideC = asPositive(inputs.triangle.sideC);
  const base = asPositive(inputs.triangle.base);
  const height = asPositive(inputs.triangle.height);
  const hasAllSides = sideA !== null && sideB !== null && sideC !== null;
  const validSides =
    hasAllSides &&
    sideA + sideB > sideC &&
    sideA + sideC > sideB &&
    sideB + sideC > sideA;

  const semiperimeter = validSides ? (sideA + sideB + sideC) / 2 : null;
  const heronArea =
    validSides && semiperimeter
      ? Math.sqrt(
          semiperimeter *
            (semiperimeter - sideA) *
            (semiperimeter - sideB) *
            (semiperimeter - sideC),
        )
      : null;
  const baseHeightArea = base && height ? (base * height) / 2 : null;
  const area = heronArea ?? baseHeightArea;
  const perimeter = validSides ? sideA + sideB + sideC : null;

  return makeResult(
    "Triangle",
    heronArea !== null ? "A = sqrt(s(s-a)(s-b)(s-c))" : "A = bh / 2",
    [
      { label: "Area", value: area === null ? null : round(area) },
      { label: "Perimeter", value: perimeter === null ? null : round(perimeter) },
      {
        label: "Semiperimeter",
        value: semiperimeter === null ? null : round(semiperimeter),
      },
      { label: "Base", value: base },
      { label: "Height", value: height },
    ],
    hasAllSides && !validSides
      ? "Those side lengths cannot form a triangle."
      : "",
  );
}

function calculateCircle(): TwoDCalculation {
  const givenRadius = asPositive(inputs.circle.radius);
  const givenDiameter = asPositive(inputs.circle.diameter);
  const radius = givenRadius ?? (givenDiameter ? givenDiameter / 2 : null);
  const diameter = radius ? radius * 2 : givenDiameter;
  const area = radius ? Math.PI * radius ** 2 : null;
  const circumference = radius ? 2 * Math.PI * radius : null;

  return makeResult("Circle", "A = pi r^2", [
    { label: "Area", value: area === null ? null : round(area) },
    {
      label: "Circumference",
      value: circumference === null ? null : round(circumference),
    },
    { label: "Radius", value: radius === null ? null : round(radius) },
    { label: "Diameter", value: diameter === null ? null : round(diameter) },
  ]);
}

function calculateTrapezoid(): TwoDCalculation {
  const baseA = asPositive(inputs.trapezoid.baseA);
  const baseB = asPositive(inputs.trapezoid.baseB);
  const height = asPositive(inputs.trapezoid.height);
  const sideC = asPositive(inputs.trapezoid.sideC);
  const sideD = asPositive(inputs.trapezoid.sideD);
  const area = baseA && baseB && height ? ((baseA + baseB) * height) / 2 : null;
  const perimeter =
    baseA && baseB && sideC && sideD ? baseA + baseB + sideC + sideD : null;
  const midline = baseA && baseB ? (baseA + baseB) / 2 : null;

  return makeResult("Trapezoid", "A = (a + b)h / 2", [
    { label: "Area", value: area === null ? null : round(area) },
    { label: "Perimeter", value: perimeter === null ? null : round(perimeter) },
    { label: "Midline", value: midline === null ? null : round(midline) },
    { label: "Base A", value: baseA },
    { label: "Base B", value: baseB },
    { label: "Height", value: height },
  ]);
}

function calculateParallelogram(): TwoDCalculation {
  const base = asPositive(inputs.parallelogram.base);
  const side = asPositive(inputs.parallelogram.side);
  const height = asPositive(inputs.parallelogram.height);
  const area = base && height ? base * height : null;
  const perimeter = base && side ? 2 * (base + side) : null;

  return makeResult("Parallelogram", "A = bh", [
    { label: "Area", value: area === null ? null : round(area) },
    { label: "Perimeter", value: perimeter === null ? null : round(perimeter) },
    { label: "Base", value: base },
    { label: "Side", value: side },
    { label: "Height", value: height },
  ]);
}

function calculateActiveShape() {
  note.value = "";

  const nextResult =
    shape.value === "rectangle"
      ? calculateRectangle()
      : shape.value === "triangle"
        ? calculateTriangle()
        : shape.value === "circle"
          ? calculateCircle()
          : shape.value === "trapezoid"
            ? calculateTrapezoid()
            : calculateParallelogram();

  result.value = nextResult;
  note.value = nextResult.note ?? "";

  if (!nextResult.items.some((item) => item.value !== null)) {
    note.value = note.value || "Enter valid dimensions first.";
    inputsHidden.value = false;
    return;
  }

  inputsHidden.value = true;
}

function clearAll() {
  result.value = null;
  note.value = "";
  inputsHidden.value = false;
}

function selectShape(value: TwoDShapeKey) {
  shape.value = value;
  clearAll();
}
</script>

<template>
  <AdminFormLayout :title="__('2D Geometry')" content-width="big">
    <div class="plane-geometry space-y-6">
      <section class="plane-hero">
        <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
          <div class="min-w-0">
            <div class="plane-badge">
              <Sparkles :size="14" />
              {{ __("Plane Geometry Toolkit") }}
            </div>
            <h2 class="mt-4 text-3xl font-bold tracking-tight text-foreground md:text-4xl">
              {{ __("2D Geometry") }}
            </h2>
            <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
              <span class="plane-pill">
                <component :is="activeShape.icon" :size="14" />
                {{ __(activeShape.label) }}
              </span>
              <span class="font-mono">{{ activeShape.formula }}</span>
            </div>
          </div>

          <div class="grid w-full gap-2 sm:grid-cols-2 lg:grid-cols-5 xl:w-auto xl:min-w-[44rem]">
            <button
              v-for="item in shapeOptions"
              :key="item.value"
              type="button"
              class="plane-shape-button"
              :class="{ 'is-active': shape === item.value }"
              :style="{ '--shape-accent': item.color }"
              :aria-pressed="shape === item.value"
              @click="selectShape(item.value)"
            >
              <component :is="item.icon" :size="18" />
              <span>{{ __(item.label) }}</span>
            </button>
          </div>
        </div>
      </section>

      <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="plane-preview-shell">
          <div class="plane-preview-header">
            <div>
              <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                {{ __("Geometry Preview") }}
              </p>
              <h3 class="mt-1 text-xl font-bold text-foreground">
                {{ __(activeShape.label) }}
              </h3>
            </div>
            <div
              class="plane-preview-icon"
              :style="{ '--shape-accent': activeShape.color }"
            >
              <component :is="activeShape.icon" :size="22" />
            </div>
          </div>

          <div class="plane-canvas-shell">
            <TwoDGeometryPreview
              :shape="shape"
              :inputs="inputs"
              :accent-color="activeShape.color"
            />
          </div>
        </div>

        <aside class="plane-side-panel">
          <div class="flex items-center gap-3">
            <div
              class="plane-side-icon"
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
              class="plane-summary-row"
              :class="{ 'is-active': shape === item.value }"
              :style="{ '--shape-accent': item.color }"
            >
              <span>{{ __(item.label) }}</span>
              <span class="font-mono text-xs">{{ item.metric }}</span>
            </div>
          </div>
        </aside>
      </section>

      <section class="plane-calculator-panel">
        <div class="mb-4 flex flex-wrap items-center justify-end gap-2">
          <Button variant="outline" @click="clearAll">
            {{ __("Clear all") }}
          </Button>
          <Button v-if="!inputsHidden" @click="calculateActiveShape">
            {{ __("Calculate") }}
          </Button>
        </div>

        <Alert
          v-if="note"
          class="mb-4 border-chart-3/30 bg-chart-3/10 text-chart-3 dark:border-chart-3 dark:bg-chart-3/20 dark:text-chart-3"
        >
          <AlertDescription>{{ __(note) }}</AlertDescription>
        </Alert>

        <div v-if="!inputsHidden" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <Card v-if="shape === 'rectangle'">
            <CardHeader>
              <CardTitle class="text-sm">{{ __("Rectangle") }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
              <FormInput
                v-model="inputs.rectangle.length"
                type="number"
                step="any"
                :label="__('Length')"
              />
              <FormInput
                v-model="inputs.rectangle.width"
                type="number"
                step="any"
                :label="__('Width')"
              />
            </CardContent>
          </Card>

          <Card v-else-if="shape === 'triangle'">
            <CardHeader>
              <CardTitle class="text-sm">{{ __("Triangle") }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
              <FormInput v-model="inputs.triangle.sideA" type="number" step="any" label="a" />
              <FormInput v-model="inputs.triangle.sideB" type="number" step="any" label="b" />
              <FormInput v-model="inputs.triangle.sideC" type="number" step="any" label="c" />
              <FormInput
                v-model="inputs.triangle.base"
                type="number"
                step="any"
                :label="__('Base')"
              />
              <FormInput
                v-model="inputs.triangle.height"
                type="number"
                step="any"
                :label="__('Height')"
              />
            </CardContent>
          </Card>

          <Card v-else-if="shape === 'circle'">
            <CardHeader>
              <CardTitle class="text-sm">{{ __("Circle") }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
              <FormInput
                v-model="inputs.circle.radius"
                type="number"
                step="any"
                :label="__('Radius')"
              />
              <FormInput
                v-model="inputs.circle.diameter"
                type="number"
                step="any"
                :label="__('Diameter')"
              />
            </CardContent>
          </Card>

          <Card v-else-if="shape === 'trapezoid'">
            <CardHeader>
              <CardTitle class="text-sm">{{ __("Trapezoid") }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
              <FormInput
                v-model="inputs.trapezoid.baseA"
                type="number"
                step="any"
                :label="__('Base A')"
              />
              <FormInput
                v-model="inputs.trapezoid.baseB"
                type="number"
                step="any"
                :label="__('Base B')"
              />
              <FormInput
                v-model="inputs.trapezoid.height"
                type="number"
                step="any"
                :label="__('Height')"
              />
              <FormInput v-model="inputs.trapezoid.sideC" type="number" step="any" label="c" />
              <FormInput v-model="inputs.trapezoid.sideD" type="number" step="any" label="d" />
            </CardContent>
          </Card>

          <Card v-else>
            <CardHeader>
              <CardTitle class="text-sm">{{ __("Parallelogram") }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
              <FormInput
                v-model="inputs.parallelogram.base"
                type="number"
                step="any"
                :label="__('Base')"
              />
              <FormInput
                v-model="inputs.parallelogram.side"
                type="number"
                step="any"
                :label="__('Side')"
              />
              <FormInput
                v-model="inputs.parallelogram.height"
                type="number"
                step="any"
                :label="__('Height')"
              />
            </CardContent>
          </Card>
        </div>

        <Card v-if="resultHasData">
          <CardHeader>
            <CardTitle class="flex items-center justify-between gap-4 text-base">
              <span>{{ __(result!.title) }} {{ __("Result") }}</span>
              <span class="font-mono text-xs text-muted-foreground">
                {{ result!.formula }}
              </span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <div
                v-for="item in result!.items"
                :key="item.label"
                class="rounded-xl border border-border bg-background/65 p-4"
                :class="{ 'opacity-40': item.value === null }"
              >
                <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                  {{ __(item.label) }}
                </p>
                <p class="mt-2 text-2xl font-bold text-foreground">
                  {{ item.value === null ? "-" : item.value }}
                </p>
                <p v-if="item.helper" class="mt-1 text-xs text-muted-foreground">
                  {{ __(item.helper) }}
                </p>
              </div>
            </div>
          </CardContent>
        </Card>
      </section>
    </div>
  </AdminFormLayout>
</template>

<style scoped>
.plane-geometry {
  --plane-soft-shadow: 0 24px 80px color-mix(in srgb, var(--ring) 12%, transparent);
}

.plane-hero,
.plane-preview-shell,
.plane-side-panel,
.plane-calculator-panel {
  border: 1px solid color-mix(in srgb, var(--border) 82%, transparent);
  background:
    radial-gradient(
      circle at top left,
      color-mix(in srgb, var(--chart-2) 10%, transparent),
      transparent 28rem
    ),
    color-mix(in srgb, var(--card) 92%, transparent);
  box-shadow: var(--plane-soft-shadow);
}

.plane-hero,
.plane-preview-shell,
.plane-side-panel,
.plane-calculator-panel {
  border-radius: 1.25rem;
  padding: 1.5rem;
}

.plane-badge,
.plane-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  border-radius: 999px;
  border: 1px solid color-mix(in srgb, var(--chart-2) 28%, transparent);
  background: color-mix(in srgb, var(--chart-2) 10%, transparent);
  color: var(--chart-2);
  font-size: 0.75rem;
  font-weight: 700;
}

.plane-badge {
  padding: 0.35rem 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.plane-pill {
  padding: 0.45rem 0.8rem;
}

.plane-shape-button {
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

.plane-shape-button:hover,
.plane-shape-button.is-active {
  border-color: color-mix(in srgb, var(--shape-accent) 55%, var(--border));
  background: color-mix(in srgb, var(--shape-accent) 12%, var(--card));
  color: var(--foreground);
  box-shadow: 0 12px 30px color-mix(in srgb, var(--shape-accent) 15%, transparent);
  transform: translateY(-1px);
}

.plane-shape-button svg,
.plane-preview-icon svg,
.plane-side-icon svg {
  color: var(--shape-accent);
}

.plane-preview-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding-bottom: 1rem;
}

.plane-preview-icon,
.plane-side-icon {
  display: flex;
  height: 3rem;
  width: 3rem;
  align-items: center;
  justify-content: center;
  border-radius: 0.875rem;
  border: 1px solid color-mix(in srgb, var(--shape-accent) 30%, var(--border));
  background: color-mix(in srgb, var(--shape-accent) 12%, transparent);
}

.plane-canvas-shell {
  height: clamp(22rem, 40vw, 30rem);
  overflow: hidden;
  border-radius: 1rem;
  border: 1px solid color-mix(in srgb, var(--border) 78%, transparent);
  background: color-mix(in srgb, var(--background) 96%, transparent);
}

.plane-side-panel {
  align-self: stretch;
}

.plane-summary-row {
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

.plane-summary-row.is-active {
  border-color: color-mix(in srgb, var(--shape-accent) 45%, var(--border));
  background: color-mix(in srgb, var(--shape-accent) 10%, var(--card));
  color: var(--foreground);
}

.plane-geometry :deep([data-slot="card"]) {
  overflow: hidden;
  border-color: color-mix(in srgb, var(--border) 82%, transparent);
  background: color-mix(in srgb, var(--card) 88%, transparent);
  box-shadow: 0 18px 46px color-mix(in srgb, var(--ring) 8%, transparent);
}

.plane-geometry :deep([data-slot="card-header"]) {
  border-bottom: 1px solid color-mix(in srgb, var(--border) 75%, transparent);
  background: color-mix(in srgb, var(--muted) 35%, transparent);
  padding-bottom: 1rem;
}

.plane-geometry :deep([data-slot="card-title"]) {
  color: var(--foreground);
  font-weight: 800;
  letter-spacing: 0;
}

.plane-geometry :deep(label) {
  color: var(--muted-foreground);
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.04em;
}

.plane-geometry :deep([data-slot="input-group"]) {
  min-height: 3rem;
  border-color: color-mix(in srgb, var(--border) 84%, transparent);
  background: color-mix(in srgb, var(--background) 70%, transparent);
}

.plane-geometry :deep([data-slot="input-group"]:focus-within) {
  border-color: color-mix(in srgb, var(--chart-2) 65%, var(--border));
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--chart-2) 14%, transparent);
}

.plane-geometry :deep(input) {
  font-family: var(--font-mono);
  color: var(--foreground);
}

.plane-geometry :deep([data-slot="button"]) {
  min-height: 2.75rem;
  border-radius: 0.75rem;
  font-weight: 800;
}

.plane-geometry :deep([data-slot="button"]:not([data-variant="outline"])) {
  border: 1px solid color-mix(in srgb, var(--chart-2) 35%, transparent);
  background: linear-gradient(135deg, var(--primary), var(--chart-2));
  color: var(--primary-foreground);
  box-shadow: 0 14px 30px color-mix(in srgb, var(--chart-2) 20%, transparent);
}

@media (max-width: 48rem) {
  .plane-hero,
  .plane-preview-shell,
  .plane-side-panel,
  .plane-calculator-panel {
    padding: 1rem;
  }

  .plane-canvas-shell {
    height: 22rem;
  }
}
</style>
