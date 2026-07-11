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
import { AlertTriangle, Sparkles, Triangle } from "lucide-vue-next";

type NullableNumber = number | null;
type NumericLike = number | string | null | undefined;

type ConeResult = {
  radius: NullableNumber;
  height: NullableNumber;
  slantHeight: NullableNumber;
  volume: NullableNumber;
  lateralSurface: NullableNumber;
  baseSurface: NullableNumber;
  totalSurface: NullableNumber;
};

type ConeKnowns = {
  slantHeight: NumericLike;
  volume: NumericLike;
  lateralSurface: NumericLike;
  baseSurface: NumericLike;
  totalSurface: NumericLike;
};

type FieldState = {
  value: NullableNumber;
  error: string;
  present: boolean;
};

type ConeStep = {
  title: string;
  formula: string;
  detail: string;
  result: NullableNumber;
  helper?: string;
};

type SolverState = {
  radius: NullableNumber;
  height: NullableNumber;
  radiusSource:
    | "radius"
    | "baseSurface"
    | "slantHeight+height"
    | "volume+height"
    | "lateralSurface+height"
    | "totalSurface+height"
    | null;
  heightSource:
    | "height"
    | "slantHeight+radius"
    | "volume+radius"
    | "lateralSurface+radius"
    | "totalSurface+radius"
    | null;
};

type ConeAnalysis = {
  result: ConeResult;
  state: SolverState;
  fieldErrors: Record<string, string>;
  errors: string[];
  warnings: string[];
  steps: ConeStep[];
  hasAnyInput: boolean;
};

const emit = defineEmits<{
  (
    e: "update:cone",
    value: { radius: NullableNumber; height: NullableNumber },
  ): void;
}>();

const MAX_LINEAR = 1_000_000;
const MAX_RADIUS = MAX_LINEAR;
const MAX_HEIGHT = MAX_LINEAR;
const MAX_SLANT_HEIGHT = Math.sqrt(2) * MAX_LINEAR;
const MAX_BASE_SURFACE = Math.PI * MAX_RADIUS ** 2;
const MAX_LATERAL_SURFACE = Math.PI * MAX_RADIUS * MAX_SLANT_HEIGHT;
const MAX_TOTAL_SURFACE = MAX_BASE_SURFACE + MAX_LATERAL_SURFACE;
const MAX_VOLUME = (Math.PI * MAX_RADIUS ** 2 * MAX_HEIGHT) / 3;

const draft = reactive({
  radius: 1.2 as NumericLike,
  height: 2.4 as NumericLike,
});

const knowns = reactive<ConeKnowns>({
  slantHeight: null,
  volume: null,
  lateralSurface: null,
  baseSurface: null,
  totalSurface: null,
});

const lastEmittedCone = ref("");

const analysis = computed<ConeAnalysis>(() => {
  const radius = numericState(draft.radius, MAX_RADIUS);
  const height = numericState(draft.height, MAX_HEIGHT);
  const slantHeight = numericState(knowns.slantHeight, MAX_SLANT_HEIGHT);
  const volume = numericState(knowns.volume, MAX_VOLUME);
  const lateralSurface = numericState(knowns.lateralSurface, MAX_LATERAL_SURFACE);
  const baseSurface = numericState(knowns.baseSurface, MAX_BASE_SURFACE);
  const totalSurface = numericState(knowns.totalSurface, MAX_TOTAL_SURFACE);

  const fieldErrors = {
    radius: radius.error,
    height: height.error,
    slantHeight: slantHeight.error,
    volume: volume.error,
    lateralSurface: lateralSurface.error,
    baseSurface: baseSurface.error,
    totalSurface: totalSurface.error,
  };

  const state = solveCone({
    radius: radius.value,
    height: height.value,
    slantHeight: slantHeight.value,
    volume: volume.value,
    lateralSurface: lateralSurface.value,
    baseSurface: baseSurface.value,
    totalSurface: totalSurface.value,
  });

  const result: ConeResult = {
    radius: state.radius,
    height: state.height,
    slantHeight:
      state.radius !== null && state.height !== null
        ? Math.sqrt(state.radius ** 2 + state.height ** 2)
        : slantHeight.value,
    volume:
      state.radius !== null && state.height !== null
        ? (Math.PI * state.radius ** 2 * state.height) / 3
        : volume.value,
    lateralSurface:
      state.radius !== null &&
      state.height !== null
        ? Math.PI * state.radius * Math.sqrt(state.radius ** 2 + state.height ** 2)
        : lateralSurface.value,
    baseSurface:
      state.radius !== null
        ? Math.PI * state.radius ** 2
        : baseSurface.value,
    totalSurface:
      state.radius !== null &&
      state.height !== null
        ? Math.PI * state.radius * (state.radius + Math.sqrt(state.radius ** 2 + state.height ** 2))
        : totalSurface.value,
  };

  const errors = [
    ...collectMessages(fieldErrors),
    ...collectConstraintErrors({
      radius: radius.value,
      height: height.value,
      slantHeight: slantHeight.value,
      lateralSurface: lateralSurface.value,
      totalSurface: totalSurface.value,
    }),
  ];

  const warnings = collectWarnings(
    result,
    {
      radius: radius.value,
      height: height.value,
      slantHeight: slantHeight.value,
      volume: volume.value,
      lateralSurface: lateralSurface.value,
      baseSurface: baseSurface.value,
      totalSurface: totalSurface.value,
    },
    state,
  );

  const hasAnyInput = [
    radius.present,
    height.present,
    slantHeight.present,
    volume.present,
    lateralSurface.present,
    baseSurface.present,
    totalSurface.present,
  ].some(Boolean);

  if (
    hasAnyInput &&
    !errors.length &&
    (state.radius === null || state.height === null)
  ) {
    warnings.push(
      __(
        "Not enough compatible information is available to solve both radius and height yet. Any confirmed properties are still shown below.",
      ),
    );
  }

  return {
    result,
    state,
    fieldErrors,
    errors,
    warnings,
    steps: buildSteps(result, state),
    hasAnyInput,
  };
});

const resultHasData = computed(() =>
  Object.values(analysis.value.result).some((value) => value !== null),
);

watch(
  () => ({
    radius: analysis.value.result.radius,
    height: analysis.value.result.height,
  }),
  (value) => {
    const normalized = {
      radius: value.radius !== null ? roundTo(value.radius) : null,
      height: value.height !== null ? roundTo(value.height) : null,
    };
    const key = JSON.stringify(normalized);

    if (key === lastEmittedCone.value) {
      return;
    }

    lastEmittedCone.value = key;
    emit("update:cone", normalized);
  },
  { immediate: true, deep: true },
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
        { max: displayPlainNumber(max) },
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

function collectConstraintErrors(values: {
  radius: NullableNumber;
  height: NullableNumber;
  slantHeight: NullableNumber;
  lateralSurface: NullableNumber;
  totalSurface: NullableNumber;
}): string[] {
  const errors: string[] = [];

  if (
    values.slantHeight !== null &&
    values.height !== null &&
    values.slantHeight <= values.height
  ) {
    errors.push(
      __(
        "Slant height must be greater than height to form a positive-radius cone.",
      ),
    );
  }

  if (
    values.slantHeight !== null &&
    values.radius !== null &&
    values.slantHeight <= values.radius
  ) {
    errors.push(
      __(
        "Slant height must be greater than radius to form a positive-height cone.",
      ),
    );
  }

  if (
    values.lateralSurface !== null &&
    values.radius !== null &&
    values.lateralSurface <= Math.PI * values.radius ** 2
  ) {
    errors.push(
      __(
        "Lateral surface must be greater than pi r^2 when radius is fixed.",
      ),
    );
  }

  if (
    values.totalSurface !== null &&
    values.radius !== null &&
    values.totalSurface <= 2 * Math.PI * values.radius ** 2
  ) {
    errors.push(
      __(
        "Total surface must be greater than 2pi r^2 when radius is fixed.",
      ),
    );
  }

  return errors;
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

function positiveSqrt(value: number): number | null {
  if (!Number.isFinite(value) || value <= 0) {
    return null;
  }

  return Math.sqrt(value);
}

function solveCone(values: {
  radius: NullableNumber;
  height: NullableNumber;
  slantHeight: NullableNumber;
  volume: NullableNumber;
  lateralSurface: NullableNumber;
  baseSurface: NullableNumber;
  totalSurface: NullableNumber;
}): SolverState {
  let radius = values.radius;
  let height = values.height;
  let radiusSource: SolverState["radiusSource"] =
    radius !== null ? "radius" : null;
  let heightSource: SolverState["heightSource"] =
    height !== null ? "height" : null;

  if (radius === null) {
    if (values.baseSurface !== null) {
      radius = Math.sqrt(values.baseSurface / Math.PI);
      radiusSource = "baseSurface";
    } else if (height !== null && values.slantHeight !== null) {
      const candidate = positiveSqrt(values.slantHeight ** 2 - height ** 2);

      if (candidate !== null) {
        radius = candidate;
        radiusSource = "slantHeight+height";
      }
    } else if (height !== null && values.volume !== null) {
      radius = Math.sqrt((3 * values.volume) / (Math.PI * height));
      radiusSource = "volume+height";
    } else if (height !== null && values.lateralSurface !== null) {
      const lateralRatio = values.lateralSurface / Math.PI;
      const radiusSquared =
        (-(height ** 2) + Math.sqrt(height ** 4 + 4 * lateralRatio ** 2)) / 2;
      const candidate = positiveSqrt(radiusSquared);

      if (candidate !== null) {
        radius = candidate;
        radiusSource = "lateralSurface+height";
      }
    } else if (height !== null && values.totalSurface !== null) {
      const totalRatio = values.totalSurface / Math.PI;
      const denominator = 2 * totalRatio + height ** 2;
      const radiusSquared =
        denominator > 0 ? totalRatio ** 2 / denominator : Number.NaN;
      const candidate = positiveSqrt(radiusSquared);

      if (candidate !== null) {
        radius = candidate;
        radiusSource = "totalSurface+height";
      }
    }
  }

  if (height === null && radius !== null) {
    if (values.slantHeight !== null) {
      const candidate = positiveSqrt(values.slantHeight ** 2 - radius ** 2);

      if (candidate !== null) {
        height = candidate;
        heightSource = "slantHeight+radius";
      }
    } else if (values.volume !== null) {
      height = (3 * values.volume) / (Math.PI * radius ** 2);
      heightSource = "volume+radius";
    } else if (values.lateralSurface !== null) {
      const slantHeight = values.lateralSurface / (Math.PI * radius);
      const candidate = positiveSqrt(slantHeight ** 2 - radius ** 2);

      if (candidate !== null) {
        height = candidate;
        heightSource = "lateralSurface+radius";
      }
    } else if (values.totalSurface !== null) {
      const slantHeight = values.totalSurface / (Math.PI * radius) - radius;
      const candidate = positiveSqrt(slantHeight ** 2 - radius ** 2);

      if (candidate !== null) {
        height = candidate;
        heightSource = "totalSurface+radius";
      }
    }
  }

  if (radius !== null && (!(radius > 0) || !Number.isFinite(radius))) {
    radius = null;
    radiusSource = null;
  }

  if (height !== null && (!(height > 0) || !Number.isFinite(height))) {
    height = null;
    heightSource = null;
  }

  return {
    radius,
    height,
    radiusSource,
    heightSource,
  };
}

function collectWarnings(
  result: ConeResult,
  values: {
    radius: NullableNumber;
    height: NullableNumber;
    slantHeight: NullableNumber;
    volume: NullableNumber;
    lateralSurface: NullableNumber;
    baseSurface: NullableNumber;
    totalSurface: NullableNumber;
  },
  state: SolverState,
): string[] {
  const warnings: string[] = [];

  if (
    state.radiusSource !== "baseSurface" &&
    values.baseSurface !== null &&
    result.baseSurface !== null
  ) {
    pushMismatchWarning(
      warnings,
      __("Base surface"),
      values.baseSurface,
      result.baseSurface,
    );
  }

  if (
    state.heightSource !== "slantHeight+radius" &&
    state.radiusSource !== "slantHeight+height" &&
    values.slantHeight !== null &&
    result.slantHeight !== null
  ) {
    pushMismatchWarning(
      warnings,
      __("Slant height"),
      values.slantHeight,
      result.slantHeight,
    );
  }

  if (
    state.heightSource !== "volume+radius" &&
    state.radiusSource !== "volume+height" &&
    values.volume !== null &&
    result.volume !== null
  ) {
    pushMismatchWarning(warnings, __("Volume"), values.volume, result.volume);
  }

  if (
    state.heightSource !== "lateralSurface+radius" &&
    state.radiusSource !== "lateralSurface+height" &&
    values.lateralSurface !== null &&
    result.lateralSurface !== null
  ) {
    pushMismatchWarning(
      warnings,
      __("Lateral surface"),
      values.lateralSurface,
      result.lateralSurface,
    );
  }

  if (
    state.heightSource !== "totalSurface+radius" &&
    state.radiusSource !== "totalSurface+height" &&
    values.totalSurface !== null &&
    result.totalSurface !== null
  ) {
    pushMismatchWarning(
      warnings,
      __("Total surface"),
      values.totalSurface,
      result.totalSurface,
    );
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
        ":label does not match the solved cone exactly.",
        { label },
      ),
    );
  }
}

function buildSteps(result: ConeResult, state: SolverState): ConeStep[] {
  const steps: ConeStep[] = [];

  if (result.radius !== null) {
    if (state.radiusSource === "baseSurface" && result.baseSurface !== null) {
      steps.push({
        title: __("Radius"),
        formula: "r = sqrt(Sb / pi)",
        detail: `sqrt(${displayNumber(result.baseSurface)} / pi)`,
        result: result.radius,
        helper: "units",
      });
    } else if (
      state.radiusSource === "slantHeight+height" &&
      result.slantHeight !== null &&
      result.height !== null
    ) {
      steps.push({
        title: __("Radius"),
        formula: "r = sqrt(l^2 - h^2)",
        detail: `sqrt(${displayNumber(result.slantHeight)}^2 - ${displayNumber(result.height)}^2)`,
        result: result.radius,
        helper: "units",
      });
    } else if (
      state.radiusSource === "volume+height" &&
      result.volume !== null &&
      result.height !== null
    ) {
      steps.push({
        title: __("Radius"),
        formula: "r = sqrt(3V / (pi h))",
        detail: `sqrt(3 * ${displayNumber(result.volume)} / (pi * ${displayNumber(result.height)}))`,
        result: result.radius,
        helper: "units",
      });
    } else if (
      state.radiusSource === "lateralSurface+height" &&
      result.lateralSurface !== null &&
      result.height !== null
    ) {
      steps.push({
        title: __("Radius"),
        formula: "r = sqrt((-h^2 + sqrt(h^4 + 4(Sl/pi)^2)) / 2)",
        detail: `sqrt((-${displayNumber(result.height)}^2 + sqrt(${displayNumber(result.height)}^4 + 4(${displayNumber(result.lateralSurface)} / pi)^2)) / 2)`,
        result: result.radius,
        helper: "units",
      });
    } else if (
      state.radiusSource === "totalSurface+height" &&
      result.totalSurface !== null &&
      result.height !== null
    ) {
      steps.push({
        title: __("Radius"),
        formula: "r = (S/pi) / sqrt(2S/pi + h^2)",
        detail: `(${displayNumber(result.totalSurface)} / pi) / sqrt(2 * ${displayNumber(result.totalSurface)} / pi + ${displayNumber(result.height)}^2)`,
        result: result.radius,
        helper: "units",
      });
    }
  }

  if (result.height !== null) {
    if (
      state.heightSource === "slantHeight+radius" &&
      result.slantHeight !== null &&
      result.radius !== null
    ) {
      steps.push({
        title: __("Height"),
        formula: "h = sqrt(l^2 - r^2)",
        detail: `sqrt(${displayNumber(result.slantHeight)}^2 - ${displayNumber(result.radius)}^2)`,
        result: result.height,
        helper: "units",
      });
    } else if (
      state.heightSource === "volume+radius" &&
      result.volume !== null &&
      result.radius !== null
    ) {
      steps.push({
        title: __("Height"),
        formula: "h = 3V / (pi r^2)",
        detail: `3 * ${displayNumber(result.volume)} / (pi * ${displayNumber(result.radius)}^2)`,
        result: result.height,
        helper: "units",
      });
    } else if (
      state.heightSource === "lateralSurface+radius" &&
      result.lateralSurface !== null &&
      result.radius !== null
    ) {
      steps.push({
        title: __("Height"),
        formula: "h = sqrt((Sl / (pi r))^2 - r^2)",
        detail: `sqrt((${displayNumber(result.lateralSurface)} / (pi * ${displayNumber(result.radius)}))^2 - ${displayNumber(result.radius)}^2)`,
        result: result.height,
        helper: "units",
      });
    } else if (
      state.heightSource === "totalSurface+radius" &&
      result.totalSurface !== null &&
      result.radius !== null
    ) {
      steps.push({
        title: __("Height"),
        formula: "h = sqrt((S / (pi r) - r)^2 - r^2)",
        detail: `sqrt((${displayNumber(result.totalSurface)} / (pi * ${displayNumber(result.radius)}) - ${displayNumber(result.radius)})^2 - ${displayNumber(result.radius)}^2)`,
        result: result.height,
        helper: "units",
      });
    }
  }

  if (result.radius !== null && result.height !== null) {
    steps.push({
      title: __("Slant height"),
      formula: "l = sqrt(r^2 + h^2)",
      detail: `sqrt(${displayNumber(result.radius)}^2 + ${displayNumber(result.height)}^2)`,
      result: result.slantHeight,
      helper: "units",
    });
  }

  if (result.radius !== null) {
    steps.push({
      title: __("Base surface"),
      formula: "Sb = pi r^2",
      detail: `pi * ${displayNumber(result.radius)}^2`,
      result: result.baseSurface,
      helper: "square units",
    });
  }

  if (result.radius !== null && result.slantHeight !== null) {
    steps.push({
      title: __("Lateral surface"),
      formula: "Sl = pi r l",
      detail: `pi * ${displayNumber(result.radius)} * ${displayNumber(result.slantHeight)}`,
      result: result.lateralSurface,
      helper: "square units",
    });
  }

  if (result.baseSurface !== null && result.lateralSurface !== null) {
    steps.push({
      title: __("Total surface"),
      formula: "S = Sb + Sl",
      detail: `${displayNumber(result.baseSurface)} + ${displayNumber(result.lateralSurface)}`,
      result: result.totalSurface,
      helper: "square units",
    });
  }

  if (result.radius !== null && result.height !== null) {
    steps.push({
      title: __("Volume"),
      formula: "V = (1/3)pi r^2 h",
      detail: `(1/3) * pi * ${displayNumber(result.radius)}^2 * ${displayNumber(result.height)}`,
      result: result.volume,
      helper: "cubic units",
    });
  }

  return steps.filter((step) => step.result !== null || step.detail !== "");
}

function resetSample() {
  draft.radius = 1.2;
  draft.height = 2.4;
  knowns.slantHeight = null;
  knowns.volume = null;
  knowns.lateralSurface = null;
  knowns.baseSurface = null;
  knowns.totalSurface = null;
}

function clearAll() {
  draft.radius = null;
  draft.height = null;
  knowns.slantHeight = null;
  knowns.volume = null;
  knowns.lateralSurface = null;
  knowns.baseSurface = null;
  knowns.totalSurface = null;
}
</script>

<template>
  <div class="cone-calculator space-y-6">
    <div class="cone-toolbar">
      <div class="cone-badge">
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
            <CardTitle class="cone-card-title">
              {{ __("Primary dimensions") }}
            </CardTitle>
          </CardHeader>
          <CardContent class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1.5">
              <FormInput
                v-model="draft.radius"
                type="number"
                step="any"
                :label="__('Radius (r)')"
                :error="analysis.fieldErrors.radius"
              />
              <p v-if="analysis.fieldErrors.radius" class="cone-field-error">
                {{ analysis.fieldErrors.radius }}
              </p>
            </div>

            <div class="space-y-1.5">
              <FormInput
                v-model="draft.height"
                type="number"
                step="any"
                :label="__('Height (h)')"
                :error="analysis.fieldErrors.height"
              />
              <p v-if="analysis.fieldErrors.height" class="cone-field-error">
                {{ analysis.fieldErrors.height }}
              </p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle class="cone-card-title">
              {{ __("Known measurements") }}
            </CardTitle>
          </CardHeader>
          <CardContent class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1.5">
              <FormInput
                v-model="knowns.slantHeight"
                type="number"
                step="any"
                :label="__('Slant height (l)')"
                :error="analysis.fieldErrors.slantHeight"
              />
              <p v-if="analysis.fieldErrors.slantHeight" class="cone-field-error">
                {{ analysis.fieldErrors.slantHeight }}
              </p>
            </div>

            <div class="space-y-1.5">
              <FormInput
                v-model="knowns.baseSurface"
                type="number"
                step="any"
                :label="__('Base surface (Sb)')"
                :error="analysis.fieldErrors.baseSurface"
              />
              <p
                v-if="analysis.fieldErrors.baseSurface"
                class="cone-field-error"
              >
                {{ analysis.fieldErrors.baseSurface }}
              </p>
            </div>

            <div class="space-y-1.5">
              <FormInput
                v-model="knowns.lateralSurface"
                type="number"
                step="any"
                :label="__('Lateral surface (Sl)')"
                :error="analysis.fieldErrors.lateralSurface"
              />
              <p
                v-if="analysis.fieldErrors.lateralSurface"
                class="cone-field-error"
              >
                {{ analysis.fieldErrors.lateralSurface }}
              </p>
            </div>

            <div class="space-y-1.5">
              <FormInput
                v-model="knowns.totalSurface"
                type="number"
                step="any"
                :label="__('Total surface (S)')"
                :error="analysis.fieldErrors.totalSurface"
              />
              <p
                v-if="analysis.fieldErrors.totalSurface"
                class="cone-field-error"
              >
                {{ analysis.fieldErrors.totalSurface }}
              </p>
            </div>

            <div class="space-y-1.5 md:col-span-2">
              <FormInput
                v-model="knowns.volume"
                type="number"
                step="any"
                :label="__('Volume (V)')"
                :error="analysis.fieldErrors.volume"
              />
              <p v-if="analysis.fieldErrors.volume" class="cone-field-error">
                {{ analysis.fieldErrors.volume }}
              </p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle class="cone-card-title">
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
                      class="cone-step-card"
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
        <Card class="cone-result-shell">
          <CardHeader>
            <CardTitle class="cone-card-title flex items-center justify-between gap-3">
              <span>{{ __("Result") }}</span>
              <div class="cone-icon-chip">
                <Triangle :size="18" />
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
              <div class="cone-result-card" :class="{ 'is-muted': analysis.result.radius === null }">
                <p class="cone-result-label">{{ __("Radius (r)") }}</p>
                <p class="cone-result-value">{{ displayNumber(analysis.result.radius) }}</p>
                <p class="cone-result-helper">{{ __("units") }}</p>
              </div>

              <div class="cone-result-card" :class="{ 'is-muted': analysis.result.height === null }">
                <p class="cone-result-label">{{ __("Height (h)") }}</p>
                <p class="cone-result-value">{{ displayNumber(analysis.result.height) }}</p>
                <p class="cone-result-helper">{{ __("units") }}</p>
              </div>

              <div class="cone-result-card" :class="{ 'is-muted': analysis.result.slantHeight === null }">
                <p class="cone-result-label">{{ __("Slant height (l)") }}</p>
                <p class="cone-result-value">{{ displayNumber(analysis.result.slantHeight) }}</p>
                <p class="cone-result-helper">{{ __("units") }}</p>
              </div>

              <div class="cone-result-card" :class="{ 'is-muted': analysis.result.baseSurface === null }">
                <p class="cone-result-label">{{ __("Base surface (Sb)") }}</p>
                <p class="cone-result-value">{{ displayNumber(analysis.result.baseSurface) }}</p>
                <p class="cone-result-helper">{{ __("square units") }}</p>
              </div>

              <div class="cone-result-card" :class="{ 'is-muted': analysis.result.lateralSurface === null }">
                <p class="cone-result-label">{{ __("Lateral surface (Sl)") }}</p>
                <p class="cone-result-value">{{ displayNumber(analysis.result.lateralSurface) }}</p>
                <p class="cone-result-helper">{{ __("square units") }}</p>
              </div>

              <div class="cone-result-card" :class="{ 'is-muted': analysis.result.totalSurface === null }">
                <p class="cone-result-label">{{ __("Total surface (S)") }}</p>
                <p class="cone-result-value">{{ displayNumber(analysis.result.totalSurface) }}</p>
                <p class="cone-result-helper">{{ __("square units") }}</p>
              </div>

              <div class="cone-result-card" :class="{ 'is-muted': analysis.result.volume === null }">
                <p class="cone-result-label">{{ __("Volume (V)") }}</p>
                <p class="cone-result-value">{{ displayNumber(analysis.result.volume) }}</p>
                <p class="cone-result-helper">{{ __("cubic units") }}</p>
              </div>
            </div>
          </CardContent>
        </Card>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.cone-calculator {
  --cone-soft-shadow: 0 18px 56px color-mix(in srgb, var(--ring) 8%, transparent);
}

.cone-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

.cone-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  border-radius: 999px;
  border: 1px solid color-mix(in srgb, var(--chart-3) 26%, transparent);
  background: color-mix(in srgb, var(--chart-3) 10%, transparent);
  color: var(--chart-3);
  padding: 0.45rem 0.85rem;
  font-size: 0.75rem;
  font-weight: 700;
}

.cone-card-title {
  color: var(--foreground);
  font-size: 0.95rem;
  font-weight: 800;
  letter-spacing: 0;
}

.cone-field-error {
  color: var(--color-destructive);
  font-size: 0.75rem;
  font-weight: 600;
}

.cone-icon-chip {
  display: inline-flex;
  height: 2.5rem;
  width: 2.5rem;
  align-items: center;
  justify-content: center;
  border-radius: 0.875rem;
  border: 1px solid color-mix(in srgb, var(--chart-3) 28%, var(--border));
  background: color-mix(in srgb, var(--chart-3) 10%, transparent);
  color: var(--chart-3);
}

.cone-result-card,
.cone-step-card {
  border-radius: 0.875rem;
  border: 1px solid color-mix(in srgb, var(--border) 82%, transparent);
  background: color-mix(in srgb, var(--muted) 42%, transparent);
  box-shadow: var(--cone-soft-shadow);
  padding: 1rem;
}

.cone-result-label {
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--muted-foreground);
}

.cone-result-value {
  margin-top: 0.45rem;
  color: var(--foreground);
  font-size: 1.35rem;
  font-weight: 800;
  line-height: 1.2;
}

.cone-result-helper {
  margin-top: 0.35rem;
  font-size: 0.75rem;
  color: var(--muted-foreground);
}

.cone-result-card.is-muted {
  opacity: 0.5;
}

.cone-result-shell {
  background:
    radial-gradient(
      circle at top right,
      color-mix(in srgb, var(--chart-3) 10%, transparent),
      transparent 16rem
    ),
    color-mix(in srgb, var(--card) 90%, transparent);
}

.cone-calculator :deep([data-slot="card"]) {
  overflow: hidden;
  border-color: color-mix(in srgb, var(--border) 82%, transparent);
  background:
    radial-gradient(
      circle at top right,
      color-mix(in srgb, var(--chart-3) 8%, transparent),
      transparent 14rem
    ),
    color-mix(in srgb, var(--card) 88%, transparent);
  box-shadow: var(--cone-soft-shadow);
}

.cone-calculator :deep([data-slot="card-header"]) {
  border-bottom: 1px solid color-mix(in srgb, var(--border) 74%, transparent);
  background: color-mix(in srgb, var(--muted) 35%, transparent);
  padding-bottom: 1rem;
}

.cone-calculator :deep(label) {
  color: var(--muted-foreground);
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.04em;
}

.cone-calculator :deep([data-slot="input-group"]) {
  min-height: 3rem;
  border-color: color-mix(in srgb, var(--border) 84%, transparent);
  background: color-mix(in srgb, var(--background) 70%, transparent);
}

.cone-calculator :deep([data-slot="input-group"]:focus-within) {
  border-color: color-mix(in srgb, var(--chart-3) 65%, var(--border));
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--chart-3) 14%, transparent);
}

.cone-calculator :deep(input) {
  font-family: var(--font-mono);
  color: var(--foreground);
}

.cone-calculator :deep([data-slot="button"]) {
  min-height: 2.75rem;
  border-radius: 0.75rem;
  font-weight: 800;
}

.cone-calculator :deep([data-slot="button"][data-variant="outline"]) {
  border-color: color-mix(in srgb, var(--border) 88%, transparent);
  background: color-mix(in srgb, var(--muted) 45%, transparent);
  color: var(--foreground);
}
</style>
