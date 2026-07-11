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
import { AlertTriangle, Layers, Sparkles } from "lucide-vue-next";

type NullableNumber = number | null;
type NumericLike = number | string | null | undefined;

type StairInput = {
  steps: NullableNumber;
  stepWidth: NullableNumber;
  rise: NullableNumber;
  run: NullableNumber;
  depth: NullableNumber;
};

type StairDraft = {
  steps: NumericLike;
  stepWidth: NumericLike;
  rise: NumericLike;
  run: NumericLike;
  depth: NumericLike;
};

type FieldState = {
  value: NullableNumber;
  error: string;
  present: boolean;
};

type StairResult = {
  risers: NullableNumber;
  treads: NullableNumber;
  stepWidth: NullableNumber;
  rise: NullableNumber;
  run: NullableNumber;
  depth: NullableNumber;
  totalRise: NullableNumber;
  totalGoing: NullableNumber;
  stringerLength: NullableNumber;
  pitchAngle: NullableNumber;
  pitchPercent: NullableNumber;
  blondelValue: NullableNumber;
  treadArea: NullableNumber;
  totalTreadArea: NullableNumber;
  riserFaceArea: NullableNumber;
  totalRiserFaceArea: NullableNumber;
  treadMaterialVolume: NullableNumber;
  totalTreadMaterialVolume: NullableNumber;
};

type StairStep = {
  title: string;
  formula: string;
  detail: string;
  result: NullableNumber;
  helper?: string;
};

type StairAnalysis = {
  resolved: StairInput;
  result: StairResult;
  fieldErrors: Record<string, string>;
  errors: string[];
  warnings: string[];
  steps: StairStep[];
  hasAnyInput: boolean;
};

const emit = defineEmits<{
  "update:stair": [value: StairInput];
}>();

const MAX_RISERS = 500;
const MAX_LINEAR = 1_000;
const MAX_TREADS = MAX_RISERS - 1;
const MAX_TOTAL_RISE = MAX_RISERS * MAX_LINEAR;
const MAX_TOTAL_GOING = MAX_TREADS * MAX_LINEAR;
const MAX_STRINGER_LENGTH = Math.sqrt(MAX_TOTAL_RISE ** 2 + MAX_TOTAL_GOING ** 2);
const MAX_FACE_AREA = MAX_LINEAR ** 2;
const MAX_TOTAL_FACE_AREA = MAX_RISERS * MAX_FACE_AREA;
const MAX_TREAD_VOLUME = MAX_LINEAR ** 3;
const MAX_TOTAL_TREAD_VOLUME = MAX_TREADS * MAX_TREAD_VOLUME;

const draft = reactive<StairDraft>({
  steps: 8,
  stepWidth: 1.2,
  rise: 0.18,
  run: 0.28,
  depth: 0.05,
});

const lastEmittedStair = ref("");

const analysis = computed<StairAnalysis>(() => {
  const steps = integerState(draft.steps, 2, MAX_RISERS);
  const stepWidth = numericState(draft.stepWidth, MAX_LINEAR);
  const rise = numericState(draft.rise, MAX_LINEAR);
  const run = numericState(draft.run, MAX_LINEAR);
  const depth = numericState(draft.depth, MAX_LINEAR);

  const fieldErrors = {
    steps: steps.error,
    stepWidth: stepWidth.error,
    rise: rise.error,
    run: run.error,
    depth: depth.error,
  };

  const resolved: StairInput = {
    steps: steps.value,
    stepWidth: stepWidth.value,
    rise: rise.value,
    run: run.value,
    depth: depth.value,
  };

  const errors = [
    ...collectMessages(fieldErrors),
    ...collectConstraintErrors(resolved),
  ];

  const risers = resolved.steps;
  const treads =
    risers !== null && Number.isFinite(risers) && risers >= 2
      ? risers - 1
      : null;

  const totalRise =
    risers !== null && resolved.rise !== null
      ? risers * resolved.rise
      : null;
  const totalGoing =
    treads !== null && resolved.run !== null
      ? treads * resolved.run
      : null;
  const stringerLength =
    totalRise !== null && totalGoing !== null
      ? Math.sqrt(totalRise ** 2 + totalGoing ** 2)
      : null;
  const pitchAngle =
    totalRise !== null && totalGoing !== null
      ? (Math.atan2(totalRise, totalGoing) * 180) / Math.PI
      : null;
  const pitchPercent =
    resolved.rise !== null && resolved.run !== null
      ? (resolved.rise / resolved.run) * 100
      : null;
  const blondelValue =
    resolved.rise !== null && resolved.run !== null
      ? 2 * resolved.rise + resolved.run
      : null;
  const treadArea =
    resolved.run !== null && resolved.stepWidth !== null
      ? resolved.run * resolved.stepWidth
      : null;
  const totalTreadArea =
    treadArea !== null && treads !== null
      ? treadArea * treads
      : null;
  const riserFaceArea =
    resolved.rise !== null && resolved.stepWidth !== null
      ? resolved.rise * resolved.stepWidth
      : null;
  const totalRiserFaceArea =
    riserFaceArea !== null && risers !== null
      ? riserFaceArea * risers
      : null;
  const treadMaterialVolume =
    treadArea !== null && resolved.depth !== null
      ? treadArea * resolved.depth
      : null;
  const totalTreadMaterialVolume =
    treadMaterialVolume !== null && treads !== null
      ? treadMaterialVolume * treads
      : null;

  const result: StairResult = {
    risers,
    treads,
    stepWidth: resolved.stepWidth,
    rise: resolved.rise,
    run: resolved.run,
    depth: resolved.depth,
    totalRise,
    totalGoing,
    stringerLength,
    pitchAngle,
    pitchPercent,
    blondelValue,
    treadArea,
    totalTreadArea,
    riserFaceArea,
    totalRiserFaceArea,
    treadMaterialVolume,
    totalTreadMaterialVolume,
  };

  const warnings = collectWarnings(result);

  const hasAnyInput = [
    steps.present,
    stepWidth.present,
    rise.present,
    run.present,
    depth.present,
  ].some(Boolean);

  return {
    resolved,
    result,
    fieldErrors,
    errors,
    warnings,
    steps: buildSteps(result),
    hasAnyInput,
  };
});

const resultHasData = computed(() =>
  Object.values(analysis.value.result).some((value) => value !== null),
);

watch(
  () => analysis.value.resolved,
  (value) => {
    const normalized: StairInput = {
      steps: value.steps !== null ? Math.round(value.steps) : null,
      stepWidth: value.stepWidth !== null ? roundTo(value.stepWidth) : null,
      rise: value.rise !== null ? roundTo(value.rise) : null,
      run: value.run !== null ? roundTo(value.run) : null,
      depth: value.depth !== null ? roundTo(value.depth) : null,
    };
    const key = JSON.stringify(normalized);

    if (key === lastEmittedStair.value) {
      return;
    }

    lastEmittedStair.value = key;

    if (!analysis.value.errors.length) {
      emit("update:stair", normalized);
      return;
    }

    if (!analysis.value.hasAnyInput) {
      emit("update:stair", normalized);
    }
  },
  { immediate: true, deep: true },
);

function integerState(
  value: NumericLike,
  min: number,
  max: number,
): FieldState {
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

  if (!Number.isInteger(parsed)) {
    return {
      value: null,
      error: __("Enter a whole number."),
      present: true,
    };
  }

  if (parsed < min || parsed > max) {
    return {
      value: null,
      error: __(
        "Use a value between :min and :max.",
        {
          min: String(min),
          max: String(max),
        },
      ),
      present: true,
    };
  }

  return {
    value: parsed,
    error: "",
    present: true,
  };
}

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

  if (parsed <= 0) {
    return {
      value: null,
      error: __("Enter a value greater than 0."),
      present: true,
    };
  }

  if (parsed > max) {
    return {
      value: null,
      error: __(
        "Use a value below :max.",
        { max: displayPlainNumber(max) },
      ),
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

function collectConstraintErrors(values: StairInput): string[] {
  const errors: string[] = [];

  if (
    values.depth !== null &&
    values.rise !== null &&
    values.depth > values.rise
  ) {
    errors.push(
      __(
        "Tread thickness cannot exceed rise in this straight-flight model.",
      ),
    );
  }

  return errors;
}

function collectWarnings(result: StairResult): string[] {
  const warnings: string[] = [];

  if (
    result.rise !== null &&
    (result.rise < 0.15 || result.rise > 0.22)
  ) {
    warnings.push(
      __(
        "Rise sits outside a common comfort range of 0.15 m to 0.22 m.",
      ),
    );
  }

  if (
    result.run !== null &&
    (result.run < 0.22 || result.run > 0.30)
  ) {
    warnings.push(
      __(
        "Going sits outside a common comfort range of 0.22 m to 0.30 m.",
      ),
    );
  }

  if (
    result.blondelValue !== null &&
    (result.blondelValue < 0.55 || result.blondelValue > 0.70)
  ) {
    warnings.push(
      __(
        "2R + G sits outside a common comfort band of 0.55 m to 0.70 m.",
      ),
    );
  }

  if (result.pitchAngle !== null && result.pitchAngle > 42) {
    warnings.push(
      __(
        "Pitch exceeds 42 degrees, which is typically considered steep for a standard stair.",
      ),
    );
  }

  return warnings;
}

function buildSteps(result: StairResult): StairStep[] {
  const steps: StairStep[] = [];

  if (result.risers !== null) {
    steps.push({
      title: __("Treads"),
      formula: "T = N - 1",
      detail: `${displayNumber(result.risers)} - 1`,
      result: result.treads,
      helper: "count",
    });
  }

  if (result.risers !== null && result.rise !== null) {
    steps.push({
      title: __("Total rise"),
      formula: "H = N x R",
      detail: `${displayNumber(result.risers)} x ${displayNumber(result.rise)}`,
      result: result.totalRise,
      helper: "units",
    });
  }

  if (result.treads !== null && result.run !== null) {
    steps.push({
      title: __("Total going"),
      formula: "Gtotal = T x G",
      detail: `${displayNumber(result.treads)} x ${displayNumber(result.run)}`,
      result: result.totalGoing,
      helper: "units",
    });
  }

  if (result.totalRise !== null && result.totalGoing !== null) {
    steps.push(
      {
        title: __("Stringer length"),
        formula: "L = sqrt(H^2 + Gtotal^2)",
        detail: `sqrt(${displayNumber(result.totalRise)}^2 + ${displayNumber(result.totalGoing)}^2)`,
        result: result.stringerLength,
        helper: "units",
      },
      {
        title: __("Pitch angle"),
        formula: "theta = atan(H / Gtotal)",
        detail: `atan(${displayNumber(result.totalRise)} / ${displayNumber(result.totalGoing)})`,
        result: result.pitchAngle,
        helper: "degrees",
      },
    );
  }

  if (result.rise !== null && result.run !== null) {
    steps.push(
      {
        title: __("Pitch percent"),
        formula: "P = (R / G) x 100",
        detail: `(${displayNumber(result.rise)} / ${displayNumber(result.run)}) x 100`,
        result: result.pitchPercent,
        helper: "percent",
      },
      {
        title: __("Comfort rule"),
        formula: "2R + G",
        detail: `2 x ${displayNumber(result.rise)} + ${displayNumber(result.run)}`,
        result: result.blondelValue,
        helper: "units",
      },
    );
  }

  if (result.run !== null && result.stepWidth !== null) {
    steps.push({
      title: __("Tread area"),
      formula: "Atread = G x W",
      detail: `${displayNumber(result.run)} x ${displayNumber(result.stepWidth)}`,
      result: result.treadArea,
      helper: "square units",
    });
  }

  if (result.treadArea !== null && result.treads !== null) {
    steps.push({
      title: __("Total tread area"),
      formula: "Atotal = T x Atread",
      detail: `${displayNumber(result.treads)} x ${displayNumber(result.treadArea)}`,
      result: result.totalTreadArea,
      helper: "square units",
    });
  }

  if (result.rise !== null && result.stepWidth !== null) {
    steps.push({
      title: __("Riser face area"),
      formula: "Ariser = R x W",
      detail: `${displayNumber(result.rise)} x ${displayNumber(result.stepWidth)}`,
      result: result.riserFaceArea,
      helper: "square units",
    });
  }

  if (result.riserFaceArea !== null && result.risers !== null) {
    steps.push({
      title: __("Total riser face area"),
      formula: "Afaces = N x Ariser",
      detail: `${displayNumber(result.risers)} x ${displayNumber(result.riserFaceArea)}`,
      result: result.totalRiserFaceArea,
      helper: "square units",
    });
  }

  if (
    result.treadArea !== null &&
    result.depth !== null
  ) {
    steps.push({
      title: __("Tread material volume"),
      formula: "Vtread = Atread x Tthickness",
      detail: `${displayNumber(result.treadArea)} x ${displayNumber(result.depth)}`,
      result: result.treadMaterialVolume,
      helper: "cubic units",
    });
  }

  if (
    result.treadMaterialVolume !== null &&
    result.treads !== null
  ) {
    steps.push({
      title: __("Total tread material volume"),
      formula: "Vtotal = T x Vtread",
      detail: `${displayNumber(result.treads)} x ${displayNumber(result.treadMaterialVolume)}`,
      result: result.totalTreadMaterialVolume,
      helper: "cubic units",
    });
  }

  return steps.filter((step) => step.result !== null || step.detail !== "");
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

function resetSample() {
  draft.steps = 8;
  draft.stepWidth = 1.2;
  draft.rise = 0.18;
  draft.run = 0.28;
  draft.depth = 0.05;
}

function clearAll() {
  draft.steps = null;
  draft.stepWidth = null;
  draft.rise = null;
  draft.run = null;
  draft.depth = null;
}
</script>

<template>
  <div class="stair-calculator space-y-6">
    <div class="stair-toolbar">
      <div class="stair-badge">
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
            <CardTitle class="stair-card-title">
              {{ __("Stair inputs") }}
            </CardTitle>
          </CardHeader>
          <CardContent class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div class="space-y-1.5">
              <FormInput
                v-model="draft.steps"
                type="number"
                step="1"
                :label="__('Number of risers (N)')"
                :error="analysis.fieldErrors.steps"
              />
              <p v-if="analysis.fieldErrors.steps" class="stair-field-error">
                {{ analysis.fieldErrors.steps }}
              </p>
            </div>

            <div class="space-y-1.5">
              <FormInput
                v-model="draft.stepWidth"
                type="number"
                step="any"
                :label="__('Stair width (W)')"
                :error="analysis.fieldErrors.stepWidth"
              />
              <p
                v-if="analysis.fieldErrors.stepWidth"
                class="stair-field-error"
              >
                {{ analysis.fieldErrors.stepWidth }}
              </p>
            </div>

            <div class="space-y-1.5">
              <FormInput
                v-model="draft.rise"
                type="number"
                step="any"
                :label="__('Rise (R)')"
                :error="analysis.fieldErrors.rise"
              />
              <p v-if="analysis.fieldErrors.rise" class="stair-field-error">
                {{ analysis.fieldErrors.rise }}
              </p>
            </div>

            <div class="space-y-1.5">
              <FormInput
                v-model="draft.run"
                type="number"
                step="any"
                :label="__('Going / run (G)')"
                :error="analysis.fieldErrors.run"
              />
              <p v-if="analysis.fieldErrors.run" class="stair-field-error">
                {{ analysis.fieldErrors.run }}
              </p>
            </div>

            <div class="space-y-1.5">
              <FormInput
                v-model="draft.depth"
                type="number"
                step="any"
                :label="__('Tread thickness (T)')"
                :error="analysis.fieldErrors.depth"
              />
              <p v-if="analysis.fieldErrors.depth" class="stair-field-error">
                {{ analysis.fieldErrors.depth }}
              </p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle class="stair-card-title">
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
                      class="stair-step-card"
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
        <Card class="stair-result-shell">
          <CardHeader>
            <CardTitle class="stair-card-title flex items-center justify-between gap-3">
              <span>{{ __("Result") }}</span>
              <div class="stair-icon-chip">
                <Layers :size="18" />
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

            <div class="grid gap-3" :class="{ 'opacity-80': !resultHasData }">
              <div class="stair-result-card" :class="{ 'is-muted': analysis.result.risers === null }">
                <p class="stair-result-label">{{ __("Risers") }}</p>
                <p class="stair-result-value">{{ displayNumber(analysis.result.risers) }}</p>
                <p class="stair-result-helper">{{ __("count") }}</p>
              </div>

              <div class="stair-result-card" :class="{ 'is-muted': analysis.result.treads === null }">
                <p class="stair-result-label">{{ __("Treads") }}</p>
                <p class="stair-result-value">{{ displayNumber(analysis.result.treads) }}</p>
                <p class="stair-result-helper">{{ __("count") }}</p>
              </div>

              <div class="stair-result-card" :class="{ 'is-muted': analysis.result.totalRise === null }">
                <p class="stair-result-label">{{ __("Total rise") }}</p>
                <p class="stair-result-value">{{ displayNumber(analysis.result.totalRise) }}</p>
                <p class="stair-result-helper">{{ __("units") }}</p>
              </div>

              <div class="stair-result-card" :class="{ 'is-muted': analysis.result.totalGoing === null }">
                <p class="stair-result-label">{{ __("Total going") }}</p>
                <p class="stair-result-value">{{ displayNumber(analysis.result.totalGoing) }}</p>
                <p class="stair-result-helper">{{ __("units") }}</p>
              </div>

              <div class="stair-result-card" :class="{ 'is-muted': analysis.result.stringerLength === null }">
                <p class="stair-result-label">{{ __("Stringer length") }}</p>
                <p class="stair-result-value">{{ displayNumber(analysis.result.stringerLength) }}</p>
                <p class="stair-result-helper">{{ __("units") }}</p>
              </div>

              <div class="stair-result-card" :class="{ 'is-muted': analysis.result.pitchAngle === null }">
                <p class="stair-result-label">{{ __("Pitch angle") }}</p>
                <p class="stair-result-value">{{ displayNumber(analysis.result.pitchAngle) }}</p>
                <p class="stair-result-helper">{{ __("degrees") }}</p>
              </div>

              <div class="stair-result-card" :class="{ 'is-muted': analysis.result.blondelValue === null }">
                <p class="stair-result-label">{{ __("2R + G") }}</p>
                <p class="stair-result-value">{{ displayNumber(analysis.result.blondelValue) }}</p>
                <p class="stair-result-helper">{{ __("units") }}</p>
              </div>

              <div class="stair-result-card" :class="{ 'is-muted': analysis.result.totalTreadMaterialVolume === null }">
                <p class="stair-result-label">{{ __("Tread material volume") }}</p>
                <p class="stair-result-value">{{ displayNumber(analysis.result.totalTreadMaterialVolume) }}</p>
                <p class="stair-result-helper">{{ __("cubic units") }}</p>
              </div>

              <div class="stair-result-card" :class="{ 'is-muted': analysis.result.totalTreadArea === null }">
                <p class="stair-result-label">{{ __("Total tread area") }}</p>
                <p class="stair-result-value">{{ displayNumber(analysis.result.totalTreadArea) }}</p>
                <p class="stair-result-helper">{{ __("square units") }}</p>
              </div>

              <div class="stair-result-card" :class="{ 'is-muted': analysis.result.totalRiserFaceArea === null }">
                <p class="stair-result-label">{{ __("Total riser face area") }}</p>
                <p class="stair-result-value">{{ displayNumber(analysis.result.totalRiserFaceArea) }}</p>
                <p class="stair-result-helper">{{ __("square units") }}</p>
              </div>
            </div>
          </CardContent>
        </Card>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.stair-calculator {
  --stair-soft-shadow: 0 18px 56px color-mix(in srgb, var(--ring) 8%, transparent);
}

.stair-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

.stair-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  border-radius: 999px;
  border: 1px solid color-mix(in srgb, var(--primary) 26%, transparent);
  background: color-mix(in srgb, var(--primary) 10%, transparent);
  color: var(--primary);
  padding: 0.45rem 0.85rem;
  font-size: 0.75rem;
  font-weight: 700;
}

.stair-card-title {
  color: var(--foreground);
  font-size: 0.95rem;
  font-weight: 800;
  letter-spacing: 0;
}

.stair-field-error {
  color: var(--color-destructive);
  font-size: 0.75rem;
  font-weight: 600;
}

.stair-icon-chip {
  display: inline-flex;
  height: 2.5rem;
  width: 2.5rem;
  align-items: center;
  justify-content: center;
  border-radius: 0.875rem;
  border: 1px solid color-mix(in srgb, var(--primary) 28%, var(--border));
  background: color-mix(in srgb, var(--primary) 10%, transparent);
  color: var(--primary);
}

.stair-result-card,
.stair-step-card {
  border-radius: 0.875rem;
  border: 1px solid color-mix(in srgb, var(--border) 82%, transparent);
  background: color-mix(in srgb, var(--muted) 42%, transparent);
  box-shadow: var(--stair-soft-shadow);
  padding: 1rem;
}

.stair-result-label {
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--muted-foreground);
}

.stair-result-value {
  margin-top: 0.45rem;
  color: var(--foreground);
  font-size: 1.35rem;
  font-weight: 800;
  line-height: 1.2;
}

.stair-result-helper {
  margin-top: 0.35rem;
  font-size: 0.75rem;
  color: var(--muted-foreground);
}

.stair-result-card.is-muted {
  opacity: 0.5;
}

.stair-result-shell {
  background:
    radial-gradient(
      circle at top right,
      color-mix(in srgb, var(--primary) 10%, transparent),
      transparent 16rem
    ),
    color-mix(in srgb, var(--card) 90%, transparent);
}

.stair-calculator :deep([data-slot="card"]) {
  overflow: hidden;
  border-color: color-mix(in srgb, var(--border) 82%, transparent);
  background:
    radial-gradient(
      circle at top right,
      color-mix(in srgb, var(--primary) 8%, transparent),
      transparent 14rem
    ),
    color-mix(in srgb, var(--card) 88%, transparent);
  box-shadow: var(--stair-soft-shadow);
}

.stair-calculator :deep([data-slot="card-header"]) {
  border-bottom: 1px solid color-mix(in srgb, var(--border) 74%, transparent);
  background: color-mix(in srgb, var(--muted) 35%, transparent);
  padding-bottom: 1rem;
}

.stair-calculator :deep(label) {
  color: var(--muted-foreground);
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.04em;
}

.stair-calculator :deep([data-slot="input-group"]) {
  min-height: 3rem;
  border-color: color-mix(in srgb, var(--border) 84%, transparent);
  background: color-mix(in srgb, var(--background) 70%, transparent);
}

.stair-calculator :deep([data-slot="input-group"]:focus-within) {
  border-color: color-mix(in srgb, var(--primary) 65%, var(--border));
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 14%, transparent);
}

.stair-calculator :deep(input) {
  font-family: var(--font-mono);
  color: var(--foreground);
}

.stair-calculator :deep([data-slot="button"]) {
  min-height: 2.75rem;
  border-radius: 0.75rem;
  font-weight: 800;
}

.stair-calculator :deep([data-slot="button"][data-variant="outline"]) {
  border-color: color-mix(in srgb, var(--border) 88%, transparent);
  background: color-mix(in srgb, var(--muted) 45%, transparent);
  color: var(--foreground);
}
</style>
