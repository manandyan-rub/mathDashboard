<script setup lang="ts">
import { computed, reactive, ref, type Component } from "vue";
import AdminFormLayout from "@/Layouts/AdminFormLayout.vue";
import FormInput from "@/Forms/FormInput.vue";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion";
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
  AlertTriangle,
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

const MAX_DIMENSION = 1_000_000;
const TRIANGLE_MISMATCH_TOLERANCE = 0.01;

const shape = ref<TwoDShapeKey>("rectangle");

const shapeOptions: ShapeOption[] = [
  {
    value: "rectangle",
    label: "Rectangle",
    metric: "l, w",
    formula: "A = l * w",
    icon: Square,
    color: "var(--primary)",
  },
  {
    value: "triangle",
    label: "Triangle",
    metric: "a, b, c / b, h",
    formula: "A = (b * h) / 2",
    icon: Triangle,
    color: "var(--chart-2)",
  },
  {
    value: "circle",
    label: "Circle",
    metric: "r, d",
    formula: "A = pi * r^2",
    icon: Circle,
    color: "var(--chart-5)",
  },
  {
    value: "trapezoid",
    label: "Trapezoid",
    metric: "a, b, h",
    formula: "A = ((a + b) * h) / 2",
    icon: Box,
    color: "var(--chart-3)",
  },
  {
    value: "parallelogram",
    label: "Parallelogram",
    metric: "b, s, h",
    formula: "A = b * h",
    icon: Ruler,
    color: "var(--destructive)",
  },
];

const inputs = reactive(createDefaultInputs());

const activeShape = computed(
  () =>
    shapeOptions.find((item) => item.value === shape.value) ?? shapeOptions[0],
);

const activeAnalysis = computed(() =>
  shape.value === "rectangle"
    ? rectangleAnalysis()
    : shape.value === "triangle"
      ? triangleAnalysis()
      : shape.value === "circle"
        ? circleAnalysis()
        : shape.value === "trapezoid"
          ? trapezoidAnalysis()
          : parallelogramAnalysis(),
);

const currentResult = computed(() => activeAnalysis.value.result);
const currentSteps = computed(() =>
  activeAnalysis.value.steps.filter(
    (step) => step.result !== null || Boolean(step.detail),
  ),
);
const fieldErrors = computed(() => activeAnalysis.value.fieldErrors);
const activeErrors = computed(() => activeAnalysis.value.errors);
const activeWarnings = computed(() => activeAnalysis.value.warnings);
const resultHasData = computed(() =>
  currentResult.value.items.some((item) => item.value !== null),
);

function createDefaultInputs(): TwoDInputs {
  return {
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
  };
}

function resetShape() {
  const defaults = createDefaultInputs();

  if (shape.value === "rectangle") {
    Object.assign(inputs.rectangle, defaults.rectangle);
    return;
  }

  if (shape.value === "triangle") {
    Object.assign(inputs.triangle, defaults.triangle);
    return;
  }

  if (shape.value === "circle") {
    Object.assign(inputs.circle, defaults.circle);
    return;
  }

  if (shape.value === "trapezoid") {
    Object.assign(inputs.trapezoid, defaults.trapezoid);
    return;
  }

  Object.assign(inputs.parallelogram, defaults.parallelogram);
}

function selectShape(value: TwoDShapeKey) {
  shape.value = value;
}

function numericState(value: NumericLike) {
  if (value === "" || value === null || value === undefined) {
    return {
      value: null as NullableNumber,
      error: "",
      present: false,
    };
  }

  const parsed = Number(value);

  if (!Number.isFinite(parsed)) {
    return {
      value: null as NullableNumber,
      error: __("Enter a valid number."),
      present: true,
    };
  }

  if (Math.abs(parsed) > MAX_DIMENSION) {
    return {
      value: null as NullableNumber,
      error: __("Use a value below 1,000,000."),
      present: true,
    };
  }

  if (parsed <= 0) {
    return {
      value: null as NullableNumber,
      error: __("Enter a value greater than 0."),
      present: true,
    };
  }

  return {
    value: parsed,
    error: "",
    present: true,
  };
}

function makeResult(
  title: string,
  formula: string,
  items: TwoDCalculation["items"],
): TwoDCalculation {
  return {
    title,
    formula,
    items,
  };
}

function displayNumber(value: NullableNumber): string {
  if (value === null || !Number.isFinite(value)) {
    return "-";
  }

  const abs = Math.abs(value);
  const rounded =
    abs >= 10000
      ? Number(value.toFixed(2))
      : abs >= 100
        ? Number(value.toFixed(3))
        : Number(value.toFixed(4));

  return new Intl.NumberFormat(undefined, {
    maximumFractionDigits: abs >= 10000 ? 2 : abs >= 100 ? 3 : 4,
  }).format(rounded);
}

function fieldError(key: string): string {
  return fieldErrors.value[key] ?? "";
}

function collectErrors(fieldMap: Record<string, string>): string[] {
  return Object.values(fieldMap).filter(Boolean);
}

function rectangleAnalysis() {
  const length = numericState(inputs.rectangle.length);
  const width = numericState(inputs.rectangle.width);
  const fieldMap = {
    "rectangle.length": length.error,
    "rectangle.width": width.error,
  };
  const errors = collectErrors(fieldMap);
  const area =
    length.value !== null && width.value !== null
      ? length.value * width.value
      : null;
  const perimeter =
    length.value !== null && width.value !== null
      ? 2 * (length.value + width.value)
      : null;
  const diagonal =
    length.value !== null && width.value !== null
      ? Math.sqrt(length.value ** 2 + width.value ** 2)
      : null;

  return {
    result: makeResult("Rectangle", "A = l * w", [
      { label: "Area", value: area, helper: "square units" },
      { label: "Perimeter", value: perimeter, helper: "units" },
      { label: "Diagonal", value: diagonal, helper: "units" },
      { label: "Length", value: length.value, helper: "units" },
      { label: "Width", value: width.value, helper: "units" },
    ]),
    fieldErrors: fieldMap,
    errors,
    warnings: [],
    steps: [
      {
        title: __("Area"),
        formula: "A = l * w",
        detail:
          length.value !== null && width.value !== null
            ? `${displayNumber(length.value)} * ${displayNumber(width.value)}`
            : "",
        result: area,
      },
      {
        title: __("Perimeter"),
        formula: "P = 2 * (l + w)",
        detail:
          length.value !== null && width.value !== null
            ? `2 * (${displayNumber(length.value)} + ${displayNumber(width.value)})`
            : "",
        result: perimeter,
      },
      {
        title: __("Diagonal"),
        formula: "d = sqrt(l^2 + w^2)",
        detail:
          length.value !== null && width.value !== null
            ? `sqrt(${displayNumber(length.value)}^2 + ${displayNumber(width.value)}^2)`
            : "",
        result: diagonal,
      },
    ],
  };
}

function triangleAnalysis() {
  const sideA = numericState(inputs.triangle.sideA);
  const sideB = numericState(inputs.triangle.sideB);
  const sideC = numericState(inputs.triangle.sideC);
  const base = numericState(inputs.triangle.base);
  const height = numericState(inputs.triangle.height);
  const fieldMap = {
    "triangle.sideA": sideA.error,
    "triangle.sideB": sideB.error,
    "triangle.sideC": sideC.error,
    "triangle.base": base.error,
    "triangle.height": height.error,
  };
  const errors = collectErrors(fieldMap);
  const warnings: string[] = [];

  const hasAllSides =
    sideA.value !== null && sideB.value !== null && sideC.value !== null;
  const validSideTriangle =
    hasAllSides &&
    sideA.value + sideB.value > sideC.value &&
    sideA.value + sideC.value > sideB.value &&
    sideB.value + sideC.value > sideA.value;

  if (hasAllSides && !validSideTriangle) {
    errors.push(__("Those side lengths cannot form a triangle."));
  }

  const hasBaseHeight = base.value !== null && height.value !== null;
  const semiperimeter = validSideTriangle
    ? (sideA.value + sideB.value + sideC.value) / 2
    : null;
  const heronArea =
    validSideTriangle && semiperimeter !== null
      ? Math.sqrt(
          semiperimeter *
            (semiperimeter - sideA.value) *
            (semiperimeter - sideB.value) *
            (semiperimeter - sideC.value),
        )
      : null;
  const baseHeightArea =
    hasBaseHeight && base.value !== null && height.value !== null
      ? (base.value * height.value) / 2
      : null;

  if (
    heronArea !== null &&
    baseHeightArea !== null &&
    Math.abs(heronArea - baseHeightArea) > TRIANGLE_MISMATCH_TOLERANCE
  ) {
    warnings.push(
      __(
        "Base-height area and side-length area do not match exactly. Check whether these measurements describe the same triangle.",
      ),
    );
  }

  const area = baseHeightArea ?? heronArea;
  const formula =
    baseHeightArea !== null ? "A = (b * h) / 2" : "A = sqrt(s(s-a)(s-b)(s-c))";
  const perimeter = validSideTriangle
    ? sideA.value + sideB.value + sideC.value
    : null;
  const effectiveBase = base.value ?? sideC.value;
  const derivedHeight =
    area !== null && effectiveBase !== null ? (2 * area) / effectiveBase : null;
  const effectiveHeight = height.value ?? derivedHeight;

  return {
    result: makeResult("Triangle", formula, [
      { label: "Area", value: area, helper: "square units" },
      { label: "Perimeter", value: perimeter, helper: "units" },
      { label: "Semiperimeter", value: semiperimeter, helper: "units" },
      { label: "Base", value: effectiveBase, helper: "units" },
      { label: "Height", value: effectiveHeight, helper: "units" },
    ]),
    fieldErrors: fieldMap,
    errors,
    warnings,
    steps: [
      {
        title: __("Semiperimeter"),
        formula: "s = (a + b + c) / 2",
        detail:
          validSideTriangle && sideA.value !== null && sideB.value !== null && sideC.value !== null
            ? `(${displayNumber(sideA.value)} + ${displayNumber(sideB.value)} + ${displayNumber(sideC.value)}) / 2`
            : "",
        result: semiperimeter,
      },
      {
        title: __("Area"),
        formula,
        detail:
          baseHeightArea !== null && base.value !== null && height.value !== null
            ? `(${displayNumber(base.value)} * ${displayNumber(height.value)}) / 2`
            : heronArea !== null && semiperimeter !== null
              ? `sqrt(${displayNumber(semiperimeter)}(${displayNumber(semiperimeter)}-${displayNumber(sideA.value)})(${displayNumber(semiperimeter)}-${displayNumber(sideB.value)})(${displayNumber(semiperimeter)}-${displayNumber(sideC.value)}))`
              : "",
        result: area,
      },
      {
        title: __("Perimeter"),
        formula: "P = a + b + c",
        detail:
          validSideTriangle && sideA.value !== null && sideB.value !== null && sideC.value !== null
            ? `${displayNumber(sideA.value)} + ${displayNumber(sideB.value)} + ${displayNumber(sideC.value)}`
            : "",
        result: perimeter,
      },
    ],
  };
}

function circleAnalysis() {
  const radius = numericState(inputs.circle.radius);
  const diameter = numericState(inputs.circle.diameter);
  const fieldMap = {
    "circle.radius": radius.error,
    "circle.diameter": diameter.error,
  };
  const errors = collectErrors(fieldMap);
  const warnings: string[] = [];

  if (
    radius.value !== null &&
    diameter.value !== null &&
    Math.abs(diameter.value - radius.value * 2) > TRIANGLE_MISMATCH_TOLERANCE
  ) {
    warnings.push(
      __(
        "Radius and diameter do not match. The calculator is using the radius value as the source of truth.",
      ),
    );
  }

  const resolvedRadius =
    radius.value ?? (diameter.value !== null ? diameter.value / 2 : null);
  const resolvedDiameter =
    diameter.value ?? (resolvedRadius !== null ? resolvedRadius * 2 : null);
  const area =
    resolvedRadius !== null ? Math.PI * resolvedRadius ** 2 : null;
  const circumference =
    resolvedRadius !== null ? 2 * Math.PI * resolvedRadius : null;

  return {
    result: makeResult("Circle", "A = pi * r^2", [
      { label: "Area", value: area, helper: "square units" },
      { label: "Circumference", value: circumference, helper: "units" },
      { label: "Radius", value: resolvedRadius, helper: "units" },
      { label: "Diameter", value: resolvedDiameter, helper: "units" },
    ]),
    fieldErrors: fieldMap,
    errors,
    warnings,
    steps: [
      {
        title: __("Radius"),
        formula: "r = d / 2",
        detail:
          radius.value === null && diameter.value !== null
            ? `${displayNumber(diameter.value)} / 2`
            : "",
        result: resolvedRadius,
      },
      {
        title: __("Area"),
        formula: "A = pi * r^2",
        detail:
          resolvedRadius !== null
            ? `pi * ${displayNumber(resolvedRadius)}^2`
            : "",
        result: area,
      },
      {
        title: __("Circumference"),
        formula: "C = 2 * pi * r",
        detail:
          resolvedRadius !== null
            ? `2 * pi * ${displayNumber(resolvedRadius)}`
            : "",
        result: circumference,
      },
    ],
  };
}

function trapezoidAnalysis() {
  const baseA = numericState(inputs.trapezoid.baseA);
  const baseB = numericState(inputs.trapezoid.baseB);
  const height = numericState(inputs.trapezoid.height);
  const sideC = numericState(inputs.trapezoid.sideC);
  const sideD = numericState(inputs.trapezoid.sideD);
  const fieldMap = {
    "trapezoid.baseA": baseA.error,
    "trapezoid.baseB": baseB.error,
    "trapezoid.height": height.error,
    "trapezoid.sideC": sideC.error,
    "trapezoid.sideD": sideD.error,
  };
  const errors = collectErrors(fieldMap);
  const warnings: string[] = [];

  if (
    height.value !== null &&
    sideC.value !== null &&
    sideC.value < height.value
  ) {
    warnings.push(
      __(
        "Side c is shorter than the height, so this cannot form a consistent trapezoid.",
      ),
    );
  }

  if (
    height.value !== null &&
    sideD.value !== null &&
    sideD.value < height.value
  ) {
    warnings.push(
      __(
        "Side d is shorter than the height, so this cannot form a consistent trapezoid.",
      ),
    );
  }

  const area =
    baseA.value !== null && baseB.value !== null && height.value !== null
      ? ((baseA.value + baseB.value) * height.value) / 2
      : null;
  const perimeter =
    baseA.value !== null &&
    baseB.value !== null &&
    sideC.value !== null &&
    sideD.value !== null
      ? baseA.value + baseB.value + sideC.value + sideD.value
      : null;
  const midline =
    baseA.value !== null && baseB.value !== null
      ? (baseA.value + baseB.value) / 2
      : null;

  return {
    result: makeResult("Trapezoid", "A = ((a + b) * h) / 2", [
      { label: "Area", value: area, helper: "square units" },
      { label: "Perimeter", value: perimeter, helper: "units" },
      { label: "Midline", value: midline, helper: "units" },
      { label: "Base A", value: baseA.value, helper: "units" },
      { label: "Base B", value: baseB.value, helper: "units" },
      { label: "Height", value: height.value, helper: "units" },
    ]),
    fieldErrors: fieldMap,
    errors,
    warnings,
    steps: [
      {
        title: __("Midline"),
        formula: "m = (a + b) / 2",
        detail:
          baseA.value !== null && baseB.value !== null
            ? `(${displayNumber(baseA.value)} + ${displayNumber(baseB.value)}) / 2`
            : "",
        result: midline,
      },
      {
        title: __("Area"),
        formula: "A = m * h",
        detail:
          midline !== null && height.value !== null
            ? `${displayNumber(midline)} * ${displayNumber(height.value)}`
            : "",
        result: area,
      },
      {
        title: __("Perimeter"),
        formula: "P = a + b + c + d",
        detail:
          baseA.value !== null &&
          baseB.value !== null &&
          sideC.value !== null &&
          sideD.value !== null
            ? `${displayNumber(baseA.value)} + ${displayNumber(baseB.value)} + ${displayNumber(sideC.value)} + ${displayNumber(sideD.value)}`
            : "",
        result: perimeter,
      },
    ],
  };
}

function parallelogramAnalysis() {
  const base = numericState(inputs.parallelogram.base);
  const side = numericState(inputs.parallelogram.side);
  const height = numericState(inputs.parallelogram.height);
  const fieldMap = {
    "parallelogram.base": base.error,
    "parallelogram.side": side.error,
    "parallelogram.height": height.error,
  };
  const errors = collectErrors(fieldMap);
  const warnings: string[] = [];

  if (
    side.value !== null &&
    height.value !== null &&
    height.value > side.value
  ) {
    warnings.push(
      __(
        "Height is larger than the side length. That makes the side-height pair geometrically inconsistent for this parallelogram.",
      ),
    );
  }

  const area =
    base.value !== null && height.value !== null
      ? base.value * height.value
      : null;
  const perimeter =
    base.value !== null && side.value !== null
      ? 2 * (base.value + side.value)
      : null;

  return {
    result: makeResult("Parallelogram", "A = b * h", [
      { label: "Area", value: area, helper: "square units" },
      { label: "Perimeter", value: perimeter, helper: "units" },
      { label: "Base", value: base.value, helper: "units" },
      { label: "Side", value: side.value, helper: "units" },
      { label: "Height", value: height.value, helper: "units" },
    ]),
    fieldErrors: fieldMap,
    errors,
    warnings,
    steps: [
      {
        title: __("Area"),
        formula: "A = b * h",
        detail:
          base.value !== null && height.value !== null
            ? `${displayNumber(base.value)} * ${displayNumber(height.value)}`
            : "",
        result: area,
      },
      {
        title: __("Perimeter"),
        formula: "P = 2 * (b + s)",
        detail:
          base.value !== null && side.value !== null
            ? `2 * (${displayNumber(base.value)} + ${displayNumber(side.value)})`
            : "",
        result: perimeter,
      },
    ],
  };
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

          <div class="plane-side-note mt-6">
            <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
              {{ __("Formula") }}
            </p>
            <p class="mt-2 font-mono text-sm text-foreground">
              {{ currentResult.formula }}
            </p>
            <p class="mt-3 text-sm text-muted-foreground">
              {{ __("Results update as you type.") }}
            </p>
          </div>
        </aside>
      </section>

      <section class="plane-calculator-panel">
        <div class="plane-panel-header">
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
              {{ __("Active Calculator") }}
            </p>
            <h3 class="mt-1 text-xl font-bold text-foreground">
              {{ __(activeShape.label) }}
            </h3>
          </div>

          <Button variant="outline" @click="resetShape">
            {{ __("Clear all") }}
          </Button>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.05fr)_minmax(320px,0.95fr)]">
          <Card>
            <CardHeader>
              <CardTitle class="text-sm">{{ __("Dimensions") }}</CardTitle>
            </CardHeader>
            <CardContent>
              <div
                v-if="shape === 'rectangle'"
                class="grid gap-4 md:grid-cols-2"
              >
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.rectangle.length"
                    type="number"
                    step="any"
                    :label="__('Length')"
                    :error="fieldError('rectangle.length')"
                  />
                  <p v-if="fieldError('rectangle.length')" class="plane-field-error">
                    {{ fieldError("rectangle.length") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.rectangle.width"
                    type="number"
                    step="any"
                    :label="__('Width')"
                    :error="fieldError('rectangle.width')"
                  />
                  <p v-if="fieldError('rectangle.width')" class="plane-field-error">
                    {{ fieldError("rectangle.width") }}
                  </p>
                </div>
              </div>

              <div
                v-else-if="shape === 'triangle'"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
              >
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.triangle.sideA"
                    type="number"
                    step="any"
                    label="a"
                    :error="fieldError('triangle.sideA')"
                  />
                  <p v-if="fieldError('triangle.sideA')" class="plane-field-error">
                    {{ fieldError("triangle.sideA") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.triangle.sideB"
                    type="number"
                    step="any"
                    label="b"
                    :error="fieldError('triangle.sideB')"
                  />
                  <p v-if="fieldError('triangle.sideB')" class="plane-field-error">
                    {{ fieldError("triangle.sideB") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.triangle.sideC"
                    type="number"
                    step="any"
                    label="c"
                    :error="fieldError('triangle.sideC')"
                  />
                  <p v-if="fieldError('triangle.sideC')" class="plane-field-error">
                    {{ fieldError("triangle.sideC") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.triangle.base"
                    type="number"
                    step="any"
                    :label="__('Base')"
                    :error="fieldError('triangle.base')"
                  />
                  <p v-if="fieldError('triangle.base')" class="plane-field-error">
                    {{ fieldError("triangle.base") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.triangle.height"
                    type="number"
                    step="any"
                    :label="__('Height')"
                    :error="fieldError('triangle.height')"
                  />
                  <p v-if="fieldError('triangle.height')" class="plane-field-error">
                    {{ fieldError("triangle.height") }}
                  </p>
                </div>
              </div>

              <div
                v-else-if="shape === 'circle'"
                class="grid gap-4 md:grid-cols-2"
              >
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.circle.radius"
                    type="number"
                    step="any"
                    :label="__('Radius')"
                    :error="fieldError('circle.radius')"
                  />
                  <p v-if="fieldError('circle.radius')" class="plane-field-error">
                    {{ fieldError("circle.radius") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.circle.diameter"
                    type="number"
                    step="any"
                    :label="__('Diameter')"
                    :error="fieldError('circle.diameter')"
                  />
                  <p v-if="fieldError('circle.diameter')" class="plane-field-error">
                    {{ fieldError("circle.diameter") }}
                  </p>
                </div>
              </div>

              <div
                v-else-if="shape === 'trapezoid'"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
              >
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.trapezoid.baseA"
                    type="number"
                    step="any"
                    :label="__('Base A')"
                    :error="fieldError('trapezoid.baseA')"
                  />
                  <p v-if="fieldError('trapezoid.baseA')" class="plane-field-error">
                    {{ fieldError("trapezoid.baseA") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.trapezoid.baseB"
                    type="number"
                    step="any"
                    :label="__('Base B')"
                    :error="fieldError('trapezoid.baseB')"
                  />
                  <p v-if="fieldError('trapezoid.baseB')" class="plane-field-error">
                    {{ fieldError("trapezoid.baseB") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.trapezoid.height"
                    type="number"
                    step="any"
                    :label="__('Height')"
                    :error="fieldError('trapezoid.height')"
                  />
                  <p v-if="fieldError('trapezoid.height')" class="plane-field-error">
                    {{ fieldError("trapezoid.height") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.trapezoid.sideC"
                    type="number"
                    step="any"
                    label="c"
                    :error="fieldError('trapezoid.sideC')"
                  />
                  <p v-if="fieldError('trapezoid.sideC')" class="plane-field-error">
                    {{ fieldError("trapezoid.sideC") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.trapezoid.sideD"
                    type="number"
                    step="any"
                    label="d"
                    :error="fieldError('trapezoid.sideD')"
                  />
                  <p v-if="fieldError('trapezoid.sideD')" class="plane-field-error">
                    {{ fieldError("trapezoid.sideD") }}
                  </p>
                </div>
              </div>

              <div v-else class="grid gap-4 md:grid-cols-3">
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.parallelogram.base"
                    type="number"
                    step="any"
                    :label="__('Base')"
                    :error="fieldError('parallelogram.base')"
                  />
                  <p v-if="fieldError('parallelogram.base')" class="plane-field-error">
                    {{ fieldError("parallelogram.base") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.parallelogram.side"
                    type="number"
                    step="any"
                    :label="__('Side')"
                    :error="fieldError('parallelogram.side')"
                  />
                  <p v-if="fieldError('parallelogram.side')" class="plane-field-error">
                    {{ fieldError("parallelogram.side") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.parallelogram.height"
                    type="number"
                    step="any"
                    :label="__('Height')"
                    :error="fieldError('parallelogram.height')"
                  />
                  <p
                    v-if="fieldError('parallelogram.height')"
                    class="plane-field-error"
                  >
                    {{ fieldError("parallelogram.height") }}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle class="text-sm">{{ __("Result") }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="plane-result-heading">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                    {{ __("Formula") }}
                  </p>
                  <p class="mt-1 font-mono text-sm text-foreground">
                    {{ currentResult.formula }}
                  </p>
                </div>
                <div
                  class="plane-side-icon"
                  :style="{ '--shape-accent': activeShape.color }"
                >
                  <component :is="activeShape.icon" :size="20" />
                </div>
              </div>

              <div
                v-if="activeErrors.length"
                class="rounded-xl border border-destructive/30 bg-destructive/8 px-4 py-3"
              >
                <div class="mb-2 flex items-center gap-2 text-sm font-semibold text-destructive">
                  <AlertTriangle :size="16" />
                  <span>{{ __("Check your dimensions") }}</span>
                </div>
                <ul class="space-y-1 text-sm text-destructive">
                  <li v-for="message in activeErrors" :key="message">
                    {{ message }}
                  </li>
                </ul>
              </div>

              <Alert
                v-if="activeWarnings.length"
                class="border-chart-3/30 bg-chart-3/10 text-chart-3 dark:border-chart-3 dark:bg-chart-3/20 dark:text-chart-3"
              >
                <AlertDescription>
                  <ul class="space-y-1">
                    <li v-for="message in activeWarnings" :key="message">
                      {{ message }}
                    </li>
                  </ul>
                </AlertDescription>
              </Alert>

              <div
                class="grid gap-3 sm:grid-cols-2"
                :class="{ 'opacity-80': !resultHasData }"
              >
                <div
                  v-for="item in currentResult.items"
                  :key="item.label"
                  class="plane-result-card"
                  :class="{ 'is-muted': item.value === null }"
                >
                  <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                    {{ __(item.label) }}
                  </p>
                  <p class="mt-2 text-2xl font-bold text-foreground">
                    {{ displayNumber(item.value) }}
                  </p>
                  <p v-if="item.helper" class="mt-1 text-xs text-muted-foreground">
                    {{ __(item.helper) }}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <Card class="mt-6">
          <CardHeader>
            <CardTitle class="text-sm">{{ __("How it's calculated") }}</CardTitle>
          </CardHeader>
          <CardContent>
            <Accordion type="single" collapsible default-value="steps">
              <AccordionItem value="steps" class="border-none">
                <AccordionTrigger class="py-0 text-left text-sm font-semibold hover:no-underline">
                  {{ __("Show calculation steps") }}
                </AccordionTrigger>
                <AccordionContent class="pt-4">
                  <div class="space-y-3">
                    <div
                      v-for="step in currentSteps"
                      :key="`${step.title}-${step.formula}`"
                      class="plane-step-card"
                    >
                      <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-foreground">
                          {{ step.title }}
                        </p>
                        <span class="font-mono text-xs text-muted-foreground">
                          {{ step.formula }}
                        </span>
                      </div>
                      <p
                        v-if="step.detail"
                        class="mt-2 font-mono text-sm text-muted-foreground"
                      >
                        {{ step.detail }}
                      </p>
                      <p class="mt-2 text-lg font-bold text-foreground">
                        {{ displayNumber(step.result) }}
                      </p>
                    </div>
                    <p
                      v-if="!currentSteps.length"
                      class="text-sm text-muted-foreground"
                    >
                      {{ __("Enter valid dimensions first.") }}
                    </p>
                  </div>
                </AccordionContent>
              </AccordionItem>
            </Accordion>
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

.plane-preview-header,
.plane-panel-header,
.plane-result-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.plane-preview-header {
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

.plane-summary-row,
.plane-side-note,
.plane-result-card,
.plane-step-card {
  border-radius: 0.875rem;
  border: 1px solid color-mix(in srgb, var(--border) 82%, transparent);
  background: color-mix(in srgb, var(--muted) 42%, transparent);
}

.plane-summary-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.85rem 1rem;
  color: var(--muted-foreground);
  font-size: 0.875rem;
}

.plane-summary-row.is-active {
  border-color: color-mix(in srgb, var(--shape-accent) 45%, var(--border));
  background: color-mix(in srgb, var(--shape-accent) 10%, var(--card));
  color: var(--foreground);
}

.plane-side-note {
  padding: 1rem;
}

.plane-result-card,
.plane-step-card {
  padding: 1rem;
}

.plane-result-card.is-muted {
  opacity: 0.48;
}

.plane-field-error {
  color: var(--color-destructive);
  font-size: 0.75rem;
  font-weight: 600;
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

.plane-geometry :deep([data-slot="button"][data-variant="outline"]) {
  border-color: color-mix(in srgb, var(--border) 88%, transparent);
  background: color-mix(in srgb, var(--muted) 45%, transparent);
  color: var(--foreground);
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

  .plane-preview-header,
  .plane-panel-header,
  .plane-result-heading {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
