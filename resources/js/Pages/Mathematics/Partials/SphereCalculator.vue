<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import FormInput from "@/Forms/FormInput.vue";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { cmsTranslate as __ } from "@/lib/i18n";
import { AlertTriangle, Circle, Sparkles } from "lucide-vue-next";

type NullableNumber = number | null;
type NumericLike = number | string | null | undefined;

type SphereResult = {
  radius: NullableNumber;
  diameter: NullableNumber;
  volume: NullableNumber;
  surfaceArea: NullableNumber;
  greatCircleCircumference: NullableNumber;
};

type SphereKnowns = {
  diameter: NumericLike;
  volume: NumericLike;
  surfaceArea: NumericLike;
  greatCircleCircumference: NumericLike;
};

type FieldState = {
  value: NullableNumber;
  error: string;
  present: boolean;
};

type SphereStep = {
  title: string;
  formula: string;
  detail: string;
  result: NullableNumber;
  helper?: string;
};

type SphereAnalysis = {
  result: SphereResult;
  source: "radius" | "diameter" | "greatCircleCircumference" | "surfaceArea" | "volume" | null;
  fieldErrors: Record<string, string>;
  errors: string[];
  warnings: string[];
  steps: SphereStep[];
  hasAnyInput: boolean;
};

const emit = defineEmits<{
  (e: "update:radius", value: NullableNumber): void;
}>();

const MAX_RADIUS = 1_000_000;
const MAX_DIAMETER = MAX_RADIUS * 2;
const MAX_GREAT_CIRCLE_CIRCUMFERENCE = 2 * Math.PI * MAX_RADIUS;
const MAX_SURFACE_AREA = 4 * Math.PI * MAX_RADIUS ** 2;
const MAX_VOLUME = (4 / 3) * Math.PI * MAX_RADIUS ** 3;
const sampleRadius = 1.4;

const draftRadius = ref<NumericLike>(sampleRadius);
const knowns = reactive<SphereKnowns>({
  diameter: null,
  volume: null,
  surfaceArea: null,
  greatCircleCircumference: null,
});

const lastEmittedRadius = ref<string>("");

const analysis = computed<SphereAnalysis>(() => {
  const radius = numericState(draftRadius.value, MAX_RADIUS);
  const diameter = numericState(knowns.diameter, MAX_DIAMETER);
  const volume = numericState(knowns.volume, MAX_VOLUME);
  const surfaceArea = numericState(knowns.surfaceArea, MAX_SURFACE_AREA);
  const greatCircleCircumference = numericState(
    knowns.greatCircleCircumference,
    MAX_GREAT_CIRCLE_CIRCUMFERENCE,
  );

  const fieldErrors = {
    radius: radius.error,
    diameter: diameter.error,
    volume: volume.error,
    surfaceArea: surfaceArea.error,
    greatCircleCircumference: greatCircleCircumference.error,
  };

  const errors = collectMessages(fieldErrors);
  const source = resolveRadiusSource({
    radius: radius.value,
    diameter: diameter.value,
    volume: volume.value,
    surfaceArea: surfaceArea.value,
    greatCircleCircumference: greatCircleCircumference.value,
  });

  const resolvedRadius = sourceRadiusValue(source, {
    radius: radius.value,
    diameter: diameter.value,
    volume: volume.value,
    surfaceArea: surfaceArea.value,
    greatCircleCircumference: greatCircleCircumference.value,
  });

  const result: SphereResult = {
    radius: resolvedRadius,
    diameter:
      resolvedRadius !== null ? resolvedRadius * 2 : diameter.value,
    volume:
      resolvedRadius !== null
        ? (4 / 3) * Math.PI * resolvedRadius ** 3
        : volume.value,
    surfaceArea:
      resolvedRadius !== null
        ? 4 * Math.PI * resolvedRadius ** 2
        : surfaceArea.value,
    greatCircleCircumference:
      resolvedRadius !== null
        ? 2 * Math.PI * resolvedRadius
        : greatCircleCircumference.value,
  };

  const warnings = collectWarnings(
    result,
    {
      radius: radius.value,
      diameter: diameter.value,
      volume: volume.value,
      surfaceArea: surfaceArea.value,
      greatCircleCircumference: greatCircleCircumference.value,
    },
    source,
  );

  const hasAnyInput = [
    radius.present,
    diameter.present,
    volume.present,
    surfaceArea.present,
    greatCircleCircumference.present,
  ].some(Boolean);

  return {
    result,
    source,
    fieldErrors,
    errors,
    warnings,
    steps: buildSteps(result, source),
    hasAnyInput,
  };
});

const resultHasData = computed(() =>
  Object.values(analysis.value.result).some((value) => value !== null),
);

watch(
  () => analysis.value.result.radius,
  (radius) => {
    const normalized = radius !== null ? roundTo(radius) : null;
    const key = String(normalized);

    if (key === lastEmittedRadius.value) {
      return;
    }

    lastEmittedRadius.value = key;
    emit("update:radius", normalized);
  },
  { immediate: true },
);

function numericState(value: NumericLike, max: number): FieldState {
  if (value === "" || value === null || value === undefined) {
    return {
      value: null,
      error: "",
      present: false,
    };
  }

  const parsed = Number(value);

  if (!Number.isFinite(parsed)) {
    return {
      value: null,
      error: __("Enter a valid number."),
      present: true,
    };
  }

  if (Math.abs(parsed) > max) {
    return {
      value: null,
      error: __(
        "Use a value below :max.",
        {
          max: displayPlainNumber(max),
        },
      ),
      present: true,
    };
  }

  if (parsed <= 0) {
    return {
      value: null,
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

function collectMessages(fieldErrors: Record<string, string>): string[] {
  return Object.values(fieldErrors).filter(Boolean);
}

function roundTo(value: number, digits = 4): number {
  return Number(value.toFixed(digits));
}

function displayNumber(value: NullableNumber): string {
  if (value === null || !Number.isFinite(value)) {
    return "-";
  }

  const abs = Math.abs(value);

  if (abs >= 1_000_000_000) {
    return value.toExponential(4);
  }

  const rounded =
    abs >= 10_000
      ? Number(value.toFixed(2))
      : abs >= 100
        ? Number(value.toFixed(3))
        : Number(value.toFixed(4));

  return new Intl.NumberFormat(undefined, {
    maximumFractionDigits: abs >= 10_000 ? 2 : abs >= 100 ? 3 : 4,
  }).format(rounded);
}

function displayPlainNumber(value: number): string {
  return new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 4,
  }).format(Number(value.toFixed(4)));
}

function resolveRadiusSource(values: {
  radius: NullableNumber;
  diameter: NullableNumber;
  volume: NullableNumber;
  surfaceArea: NullableNumber;
  greatCircleCircumference: NullableNumber;
}): SphereAnalysis["source"] {
  if (values.radius !== null) {
    return "radius";
  }

  if (values.diameter !== null) {
    return "diameter";
  }

  if (values.greatCircleCircumference !== null) {
    return "greatCircleCircumference";
  }

  if (values.surfaceArea !== null) {
    return "surfaceArea";
  }

  if (values.volume !== null) {
    return "volume";
  }

  return null;
}

function sourceRadiusValue(
  source: SphereAnalysis["source"],
  values: {
    radius: NullableNumber;
    diameter: NullableNumber;
    volume: NullableNumber;
    surfaceArea: NullableNumber;
    greatCircleCircumference: NullableNumber;
  },
): NullableNumber {
  if (source === "radius") {
    return values.radius;
  }

  if (source === "diameter" && values.diameter !== null) {
    return values.diameter / 2;
  }

  if (
    source === "greatCircleCircumference" &&
    values.greatCircleCircumference !== null
  ) {
    return values.greatCircleCircumference / (2 * Math.PI);
  }

  if (source === "surfaceArea" && values.surfaceArea !== null) {
    return Math.sqrt(values.surfaceArea / (4 * Math.PI));
  }

  if (source === "volume" && values.volume !== null) {
    return Math.cbrt((3 * values.volume) / (4 * Math.PI));
  }

  return null;
}

function collectWarnings(
  result: SphereResult,
  values: {
    radius: NullableNumber;
    diameter: NullableNumber;
    volume: NullableNumber;
    surfaceArea: NullableNumber;
    greatCircleCircumference: NullableNumber;
  },
  source: SphereAnalysis["source"],
): string[] {
  const warnings: string[] = [];

  if (
    source !== "diameter" &&
    values.diameter !== null &&
    result.diameter !== null
  ) {
    pushMismatchWarning(
      warnings,
      __("Diameter"),
      values.diameter,
      result.diameter,
    );
  }

  if (
    source !== "greatCircleCircumference" &&
    values.greatCircleCircumference !== null &&
    result.greatCircleCircumference !== null
  ) {
    pushMismatchWarning(
      warnings,
      __("Great-circle circumference"),
      values.greatCircleCircumference,
      result.greatCircleCircumference,
    );
  }

  if (
    source !== "surfaceArea" &&
    values.surfaceArea !== null &&
    result.surfaceArea !== null
  ) {
    pushMismatchWarning(
      warnings,
      __("Surface area"),
      values.surfaceArea,
      result.surfaceArea,
    );
  }

  if (source !== "volume" && values.volume !== null && result.volume !== null) {
    pushMismatchWarning(warnings, __("Volume"), values.volume, result.volume);
  }

  return warnings;
}

function pushMismatchWarning(
  warnings: string[],
  label: string,
  givenValue: number,
  derivedValue: number,
) {
  const tolerance = Math.max(0.01, Math.max(givenValue, derivedValue) * 0.0001);

  if (Math.abs(givenValue - derivedValue) > tolerance) {
    warnings.push(
      __(
        ":label does not match the solved sphere exactly.",
        { label },
      ),
    );
  }
}

function buildSteps(
  result: SphereResult,
  source: SphereAnalysis["source"],
): SphereStep[] {
  const steps: SphereStep[] = [];

  if (result.radius !== null) {
    if (source === "diameter" && result.diameter !== null) {
      steps.push({
        title: __("Radius"),
        formula: "r = D / 2",
        detail: `${displayNumber(result.diameter)} / 2`,
        result: result.radius,
        helper: "units",
      });
    } else if (
      source === "greatCircleCircumference" &&
      result.greatCircleCircumference !== null
    ) {
      steps.push({
        title: __("Radius"),
        formula: "r = C / (2pi)",
        detail: `${displayNumber(result.greatCircleCircumference)} / (2pi)`,
        result: result.radius,
        helper: "units",
      });
    } else if (source === "surfaceArea" && result.surfaceArea !== null) {
      steps.push({
        title: __("Radius"),
        formula: "r = sqrt(S / (4pi))",
        detail: `sqrt(${displayNumber(result.surfaceArea)} / (4pi))`,
        result: result.radius,
        helper: "units",
      });
    } else if (source === "volume" && result.volume !== null) {
      steps.push({
        title: __("Radius"),
        formula: "r = cbrt(3V / (4pi))",
        detail: `cbrt((3 * ${displayNumber(result.volume)}) / (4pi))`,
        result: result.radius,
        helper: "units",
      });
    }

    steps.push(
      {
        title: __("Diameter"),
        formula: "D = 2r",
        detail: `2 * ${displayNumber(result.radius)}`,
        result: result.diameter,
        helper: "units",
      },
      {
        title: __("Surface area"),
        formula: "S = 4pi r^2",
        detail: `4pi * ${displayNumber(result.radius)}^2`,
        result: result.surfaceArea,
        helper: "square units",
      },
      {
        title: __("Volume"),
        formula: "V = (4/3)pi r^3",
        detail: `(4/3)pi * ${displayNumber(result.radius)}^3`,
        result: result.volume,
        helper: "cubic units",
      },
      {
        title: __("Great-circle circumference"),
        formula: "C = 2pi r",
        detail: `2pi * ${displayNumber(result.radius)}`,
        result: result.greatCircleCircumference,
        helper: "units",
      },
    );
  }

  return steps;
}

function resetSample() {
  draftRadius.value = sampleRadius;
  knowns.diameter = null;
  knowns.volume = null;
  knowns.surfaceArea = null;
  knowns.greatCircleCircumference = null;
}

function clearAll() {
  draftRadius.value = null;
  knowns.diameter = null;
  knowns.volume = null;
  knowns.surfaceArea = null;
  knowns.greatCircleCircumference = null;
}
</script>

<template>
  <div class="sphere-calculator space-y-6">
    <div class="sphere-toolbar">
      <div class="sphere-badge">
        <Sparkles :size="14" />
        {{ __("Results update live as you type.") }}
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <Button variant="outline" @click="resetSample">
          {{ __("Reset sample") }}
        </Button>
        <Button variant="outline" @click="clearAll">
          {{ __("Clear all") }}
        </Button>
      </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_22rem]">
      <div class="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle class="sphere-card-title">
              {{ __("Primary radius") }}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div class="space-y-1.5">
              <FormInput
                v-model="draftRadius"
                type="number"
                step="any"
                :label="__('Radius (r)')"
                :error="analysis.fieldErrors.radius"
              />
              <p v-if="analysis.fieldErrors.radius" class="sphere-field-error">
                {{ analysis.fieldErrors.radius }}
              </p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle class="sphere-card-title">
              {{ __("Known measurements") }}
            </CardTitle>
          </CardHeader>
          <CardContent class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1.5">
              <FormInput
                v-model="knowns.diameter"
                type="number"
                step="any"
                :label="__('Diameter (D)')"
                :error="analysis.fieldErrors.diameter"
              />
              <p v-if="analysis.fieldErrors.diameter" class="sphere-field-error">
                {{ analysis.fieldErrors.diameter }}
              </p>
            </div>

            <div class="space-y-1.5">
              <FormInput
                v-model="knowns.greatCircleCircumference"
                type="number"
                step="any"
                :label="__('Great-circle circumference (C)')"
                :error="analysis.fieldErrors.greatCircleCircumference"
              />
              <p
                v-if="analysis.fieldErrors.greatCircleCircumference"
                class="sphere-field-error"
              >
                {{ analysis.fieldErrors.greatCircleCircumference }}
              </p>
            </div>

            <div class="space-y-1.5">
              <FormInput
                v-model="knowns.surfaceArea"
                type="number"
                step="any"
                :label="__('Surface area (S)')"
                :error="analysis.fieldErrors.surfaceArea"
              />
              <p v-if="analysis.fieldErrors.surfaceArea" class="sphere-field-error">
                {{ analysis.fieldErrors.surfaceArea }}
              </p>
            </div>

            <div class="space-y-1.5">
              <FormInput
                v-model="knowns.volume"
                type="number"
                step="any"
                :label="__('Volume (V)')"
                :error="analysis.fieldErrors.volume"
              />
              <p v-if="analysis.fieldErrors.volume" class="sphere-field-error">
                {{ analysis.fieldErrors.volume }}
              </p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle class="sphere-card-title">
              {{ __("How it's calculated") }}
            </CardTitle>
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
                      v-for="step in analysis.steps"
                      :key="`${step.title}-${step.formula}`"
                      class="sphere-step-card"
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
                      <p v-if="step.helper" class="mt-1 text-xs text-muted-foreground">
                        {{ __(step.helper) }}
                      </p>
                    </div>
                    <p
                      v-if="!analysis.steps.length"
                      class="text-sm text-muted-foreground"
                    >
                      {{ __("Enter valid values first.") }}
                    </p>
                  </div>
                </AccordionContent>
              </AccordionItem>
            </Accordion>
          </CardContent>
        </Card>
      </div>

      <aside class="space-y-6">
        <Card class="sphere-result-shell">
          <CardHeader>
            <CardTitle class="sphere-card-title flex items-center justify-between gap-3">
              <span>{{ __("Result") }}</span>
              <div class="sphere-icon-chip">
                <Circle :size="18" />
              </div>
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div
              v-if="analysis.errors.length"
              class="rounded-xl border border-destructive/30 bg-destructive/8 px-4 py-3"
            >
              <div class="mb-2 flex items-center gap-2 text-sm font-semibold text-destructive">
                <AlertTriangle :size="16" />
                <span>{{ __("Check your values") }}</span>
              </div>
              <ul class="space-y-1 text-sm text-destructive">
                <li v-for="message in analysis.errors" :key="message">
                  {{ message }}
                </li>
              </ul>
            </div>

            <Alert
              v-if="analysis.warnings.length"
              class="border-chart-3/30 bg-chart-3/10 text-chart-3 dark:border-chart-3 dark:bg-chart-3/20 dark:text-chart-3"
            >
              <AlertDescription>
                <ul class="space-y-1">
                  <li v-for="message in analysis.warnings" :key="message">
                    {{ message }}
                  </li>
                </ul>
              </AlertDescription>
            </Alert>

            <div
              class="grid gap-3"
              :class="{ 'opacity-80': !resultHasData }"
            >
              <div class="sphere-result-card" :class="{ 'is-muted': analysis.result.radius === null }">
                <p class="sphere-result-label">{{ __("Radius (r)") }}</p>
                <p class="sphere-result-value">{{ displayNumber(analysis.result.radius) }}</p>
                <p class="sphere-result-helper">{{ __("units") }}</p>
              </div>

              <div class="sphere-result-card" :class="{ 'is-muted': analysis.result.diameter === null }">
                <p class="sphere-result-label">{{ __("Diameter (D)") }}</p>
                <p class="sphere-result-value">{{ displayNumber(analysis.result.diameter) }}</p>
                <p class="sphere-result-helper">{{ __("units") }}</p>
              </div>

              <div class="sphere-result-card" :class="{ 'is-muted': analysis.result.greatCircleCircumference === null }">
                <p class="sphere-result-label">{{ __("Great-circle circumference (C)") }}</p>
                <p class="sphere-result-value">
                  {{ displayNumber(analysis.result.greatCircleCircumference) }}
                </p>
                <p class="sphere-result-helper">{{ __("units") }}</p>
              </div>

              <div class="sphere-result-card" :class="{ 'is-muted': analysis.result.surfaceArea === null }">
                <p class="sphere-result-label">{{ __("Surface area (S)") }}</p>
                <p class="sphere-result-value">{{ displayNumber(analysis.result.surfaceArea) }}</p>
                <p class="sphere-result-helper">{{ __("square units") }}</p>
              </div>

              <div class="sphere-result-card" :class="{ 'is-muted': analysis.result.volume === null }">
                <p class="sphere-result-label">{{ __("Volume (V)") }}</p>
                <p class="sphere-result-value">{{ displayNumber(analysis.result.volume) }}</p>
                <p class="sphere-result-helper">{{ __("cubic units") }}</p>
              </div>
            </div>
          </CardContent>
        </Card>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.sphere-calculator {
  --sphere-soft-shadow: 0 18px 56px color-mix(in srgb, var(--ring) 8%, transparent);
}

.sphere-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

.sphere-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  border-radius: 999px;
  border: 1px solid color-mix(in srgb, var(--chart-2) 26%, transparent);
  background: color-mix(in srgb, var(--chart-2) 10%, transparent);
  color: var(--chart-2);
  padding: 0.45rem 0.85rem;
  font-size: 0.75rem;
  font-weight: 700;
}

.sphere-card-title {
  color: var(--foreground);
  font-size: 0.95rem;
  font-weight: 800;
  letter-spacing: 0;
}

.sphere-field-error {
  color: var(--color-destructive);
  font-size: 0.75rem;
  font-weight: 600;
}

.sphere-icon-chip {
  display: inline-flex;
  height: 2.5rem;
  width: 2.5rem;
  align-items: center;
  justify-content: center;
  border-radius: 0.875rem;
  border: 1px solid color-mix(in srgb, var(--chart-2) 28%, var(--border));
  background: color-mix(in srgb, var(--chart-2) 10%, transparent);
  color: var(--chart-2);
}

.sphere-result-card,
.sphere-step-card {
  border-radius: 0.875rem;
  border: 1px solid color-mix(in srgb, var(--border) 82%, transparent);
  background: color-mix(in srgb, var(--muted) 42%, transparent);
  box-shadow: var(--sphere-soft-shadow);
}

.sphere-result-card,
.sphere-step-card {
  padding: 1rem;
}

.sphere-result-label {
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--muted-foreground);
}

.sphere-result-value {
  margin-top: 0.45rem;
  color: var(--foreground);
  font-size: 1.35rem;
  font-weight: 800;
  line-height: 1.2;
}

.sphere-result-helper {
  margin-top: 0.35rem;
  font-size: 0.75rem;
  color: var(--muted-foreground);
}

.sphere-result-card.is-muted {
  opacity: 0.5;
}

.sphere-result-shell {
  background:
    radial-gradient(
      circle at top right,
      color-mix(in srgb, var(--chart-2) 10%, transparent),
      transparent 16rem
    ),
    color-mix(in srgb, var(--card) 90%, transparent);
}

.sphere-calculator :deep([data-slot="card"]) {
  overflow: hidden;
  border-color: color-mix(in srgb, var(--border) 82%, transparent);
  background:
    radial-gradient(
      circle at top right,
      color-mix(in srgb, var(--chart-2) 8%, transparent),
      transparent 14rem
    ),
    color-mix(in srgb, var(--card) 88%, transparent);
  box-shadow: var(--sphere-soft-shadow);
}

.sphere-calculator :deep([data-slot="card-header"]) {
  border-bottom: 1px solid color-mix(in srgb, var(--border) 74%, transparent);
  background: color-mix(in srgb, var(--muted) 35%, transparent);
  padding-bottom: 1rem;
}

.sphere-calculator :deep(label) {
  color: var(--muted-foreground);
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.04em;
}

.sphere-calculator :deep([data-slot="input-group"]) {
  min-height: 3rem;
  border-color: color-mix(in srgb, var(--border) 84%, transparent);
  background: color-mix(in srgb, var(--background) 70%, transparent);
}

.sphere-calculator :deep([data-slot="input-group"]:focus-within) {
  border-color: color-mix(in srgb, var(--chart-2) 65%, var(--border));
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--chart-2) 14%, transparent);
}

.sphere-calculator :deep(input) {
  font-family: var(--font-mono);
  color: var(--foreground);
}

.sphere-calculator :deep([data-slot="button"]) {
  min-height: 2.75rem;
  border-radius: 0.75rem;
  font-weight: 800;
}

.sphere-calculator :deep([data-slot="button"][data-variant="outline"]) {
  border-color: color-mix(in srgb, var(--border) 88%, transparent);
  background: color-mix(in srgb, var(--muted) 45%, transparent);
  color: var(--foreground);
}
</style>
