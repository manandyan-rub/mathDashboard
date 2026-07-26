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
import { resolveCalculatorFieldStatus } from "@/lib/geometry/calculatorFields";
import {
  buildCubeCalculationSteps,
  CUBE_FIELD_DEFINITIONS,
  cubeFieldDefinition,
  solveCube,
} from "@/lib/geometry/cube";
import {
  fromBaseUnit,
  LENGTH_UNITS,
  toBaseUnit,
  unitLabel,
} from "@/lib/geometry/units";
import { cmsTranslate as __ } from "@/lib/i18n";
import type {
  CalculatorFieldStatus,
  CubeEnteredValues,
  CubeFieldKey,
  LengthUnit,
} from "@/types/geometryCalculator";
import {
  AlertTriangle,
  Box,
  Calculator,
  RotateCcw,
  Sparkles,
} from "lucide-vue-next";

defineProps({
  side: {
    type: Number,
    default: null,
  },
});

const emit = defineEmits(["update:side"]);

const unit = ref<LengthUnit>("cm");
const precision = ref(4);
const entered = reactive(
  Object.fromEntries(
    CUBE_FIELD_DEFINITIONS.map(({ key }) => [key, false]),
  ) as Record<CubeFieldKey, boolean>,
);
const drafts = reactive(
  Object.fromEntries(
    CUBE_FIELD_DEFINITIONS.map(({ key }) => [key, ""]),
  ) as Record<CubeFieldKey, string>,
);

const enteredValues = computed<CubeEnteredValues>(() => {
  const values: CubeEnteredValues = {};

  for (const definition of CUBE_FIELD_DEFINITIONS) {
    if (!entered[definition.key]) continue;

    const parsed = Number(drafts[definition.key]);
    values[definition.key] = Number.isFinite(parsed)
      ? toBaseUnit(parsed, unit.value, definition.dimension)
      : Number.NaN;
  }

  return values;
});

const solution = computed(() => solveCube(enteredValues.value));
const conflictByField = computed(
  () =>
    new Map(
      solution.value.conflicts.map((conflict) => [conflict.field, conflict]),
    ),
);
const fieldRows = computed(() =>
  CUBE_FIELD_DEFINITIONS.map((definition) => ({
    ...definition,
    status: fieldStatus(definition.key),
    displayValue: fieldDisplayValue(definition.key),
    error: fieldError(definition.key),
    unit: unitLabel(unit.value, definition.dimension),
  })),
);
const calculationSteps = computed(() =>
  buildCubeCalculationSteps(solution.value, (value, key) =>
    formatDisplayValue(
      fromBaseUnit(
        value,
        unit.value,
        cubeFieldDefinition(key).dimension,
      ),
    ),
  ),
);
const hasEnteredValues = computed(() =>
  CUBE_FIELD_DEFINITIONS.some(({ key }) => entered[key]),
);

watch(
  () => solution.value.side,
  (side) => {
    emit("update:side", side);
  },
  { immediate: true },
);

watch(unit, (nextUnit, previousUnit) => {
  for (const definition of CUBE_FIELD_DEFINITIONS) {
    if (!entered[definition.key]) continue;

    const parsed = Number(drafts[definition.key]);
    if (!Number.isFinite(parsed)) continue;

    const baseValue = toBaseUnit(
      parsed,
      previousUnit,
      definition.dimension,
    );
    drafts[definition.key] = formatEditableValue(
      fromBaseUnit(baseValue, nextUnit, definition.dimension),
    );
  }
});

function updateField(key: CubeFieldKey, value: unknown) {
  const nextValue = String(value ?? "");
  drafts[key] = nextValue;
  entered[key] = nextValue.trim() !== "";
}

function fieldStatus(key: CubeFieldKey): CalculatorFieldStatus {
  return resolveCalculatorFieldStatus({
    isEntered: entered[key],
    isValid: !solution.value.invalidFields.includes(key),
    isConflicting: conflictByField.value.has(key),
    hasCalculatedValue:
      solution.value.calculatedFields.includes(key) &&
      solution.value.values[key] !== undefined,
  });
}

function fieldDisplayValue(key: CubeFieldKey): string {
  if (entered[key]) return drafts[key];

  const value = solution.value.values[key];
  if (value === undefined) return "";

  const definition = cubeFieldDefinition(key);
  return formatEditableValue(
    fromBaseUnit(value, unit.value, definition.dimension),
  );
}

function fieldError(key: CubeFieldKey): string {
  if (!entered[key]) return "";

  const parsed = Number(drafts[key]);
  if (!Number.isFinite(parsed)) return __("Enter a valid number.");
  if (parsed <= 0) return __("Enter a value greater than 0.");

  const definition = cubeFieldDefinition(key);
  const maximum = fromBaseUnit(
    definition.maximum,
    unit.value,
    definition.dimension,
  );
  if (parsed > maximum) {
    return __("Use a value no greater than :maximum.", {
      maximum: `${formatDisplayValue(maximum)} ${unitLabel(unit.value, definition.dimension)}`,
    });
  }

  const conflict = conflictByField.value.get(key);
  if (!conflict) return "";

  const expected = fromBaseUnit(
    conflict.expectedValue,
    unit.value,
    definition.dimension,
  );
  return __("Entered value conflicts with the solved cube. Expected :value.", {
    value: `${formatDisplayValue(expected)} ${unitLabel(unit.value, definition.dimension)}`,
  });
}

function loadExample() {
  reset();
  entered.side = true;
  drafts.side = "4";
}

function reset() {
  for (const definition of CUBE_FIELD_DEFINITIONS) {
    entered[definition.key] = false;
    drafts[definition.key] = "";
  }
}

function formatEditableValue(value: number): string {
  if (!Number.isFinite(value)) return "";
  const absolute = Math.abs(value);

  if ((absolute > 0 && absolute < 1e-8) || absolute >= 1e15) {
    return value.toExponential(precision.value);
  }

  return String(Number(value.toFixed(precision.value)));
}

function formatDisplayValue(value: number): string {
  if (!Number.isFinite(value)) return "-";
  const absolute = Math.abs(value);

  if ((absolute > 0 && absolute < 1e-6) || absolute >= 1e12) {
    return value.toExponential(precision.value);
  }

  return new Intl.NumberFormat(undefined, {
    maximumFractionDigits: precision.value,
  }).format(value);
}

function statusLabel(status: CalculatorFieldStatus): string {
  return {
    empty: "Empty",
    entered: "Entered",
    calculated: "Calculated",
    invalid: "Invalid",
    conflicting: "Conflicting",
  }[status];
}
</script>

<template>
  <div class="cube-calculator space-y-6">
    <div class="cube-toolbar">
      <div>
        <div class="cube-badge">
          <Sparkles :size="14" />
          {{ __("One dimension, every property") }}
        </div>
        <h3 class="mt-3 text-2xl font-bold text-foreground">
          {{ __("Cube Calculator") }}
        </h3>
        <p class="mt-1 text-sm text-muted-foreground">
          {{ __("Enter any one property. Additional entries are checked for consistency.") }}
        </p>
      </div>

      <div class="flex flex-wrap items-end gap-3">
        <label class="cube-control">
          <span>{{ __("Unit") }}</span>
          <select v-model="unit" class="cube-select">
            <option v-for="item in LENGTH_UNITS" :key="item" :value="item">
              {{ item }}
            </option>
          </select>
        </label>

        <label class="cube-control">
          <span>{{ __("Precision") }}</span>
          <select v-model.number="precision" class="cube-select">
            <option v-for="digits in [2, 4, 6, 8]" :key="digits" :value="digits">
              {{ digits }}
            </option>
          </select>
        </label>

        <Button variant="outline" @click="loadExample">
          <Calculator :size="16" />
          {{ __("Load example") }}
        </Button>
        <Button variant="outline" @click="reset">
          <RotateCcw :size="16" />
          {{ __("Reset") }}
        </Button>
      </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.65fr)]">
      <Card>
        <CardHeader>
          <CardTitle class="cube-card-title">
            {{ __("Editable properties") }}
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div class="grid gap-4 md:grid-cols-2">
            <div
              v-for="field in fieldRows"
              :key="field.key"
              class="cube-field"
              :data-status="field.status"
            >
              <div class="mb-1.5 flex items-center justify-between gap-3">
                <span class="cube-symbol">{{ field.symbol }}</span>
                <span class="cube-state">
                  {{ __(statusLabel(field.status)) }}
                </span>
              </div>

              <FormInput
                type="number"
                step="any"
                :model-value="field.displayValue"
                :label="__(field.label)"
                :error="field.error"
                :input-class="`cube-input is-${field.status}`"
                @update:model-value="updateField(field.key, $event)"
              >
                <span class="cube-unit">{{ field.unit }}</span>
              </FormInput>
              <p v-if="field.error" class="cube-field-error">
                {{ field.error }}
              </p>
            </div>
          </div>
        </CardContent>
      </Card>

      <div class="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle class="cube-card-title flex items-center justify-between gap-3">
              <span>{{ __("Solution status") }}</span>
              <span class="cube-icon-chip"><Box :size="18" /></span>
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="cube-summary">
              <div>
                <p class="cube-summary-label">{{ __("Solved side") }}</p>
                <p class="cube-summary-value">
                  {{
                    solution.side === null
                      ? "-"
                      : `${formatDisplayValue(fromBaseUnit(solution.side, unit, 1))} ${unit}`
                  }}
                </p>
              </div>
              <div>
                <p class="cube-summary-label">{{ __("Source") }}</p>
                <p class="cube-summary-source">
                  {{
                    solution.sourceField
                      ? __(cubeFieldDefinition(solution.sourceField).label)
                      : "-"
                  }}
                </p>
              </div>
            </div>

            <Alert
              v-if="solution.conflicts.length"
              class="border-destructive/35 bg-destructive/8 text-destructive"
            >
              <AlertTriangle :size="16" />
              <AlertDescription>
                <ul class="space-y-1">
                  <li
                    v-for="conflict in solution.conflicts"
                    :key="`${conflict.field}-${conflict.enteredValue}`"
                  >
                    {{
                      __("Entered :field does not describe the same cube.", {
                        field: __(cubeFieldDefinition(conflict.field).label),
                      })
                    }}
                  </li>
                </ul>
              </AlertDescription>
            </Alert>

            <Alert
              v-else-if="!hasEnteredValues"
              class="border-border bg-muted/45 text-muted-foreground"
            >
              <AlertDescription>
                {{ __("Enter any cube property or load the example.") }}
              </AlertDescription>
            </Alert>

            <div class="cube-legend">
              <span data-tone="entered">{{ __("Entered") }}</span>
              <span data-tone="calculated">{{ __("Calculated") }}</span>
              <span data-tone="conflicting">{{ __("Conflicting") }}</span>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle class="cube-card-title">
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
                  <div v-if="calculationSteps.length" class="space-y-3">
                    <div
                      v-for="step in calculationSteps"
                      :key="`${step.field}-${step.formula}`"
                      class="cube-step"
                    >
                      <div class="flex items-start justify-between gap-4">
                        <p class="text-sm font-semibold text-foreground">
                          {{ __(step.label) }}
                        </p>
                        <span class="font-mono text-xs text-muted-foreground">
                          {{ step.formula }}
                        </span>
                      </div>
                      <p class="mt-2 font-mono text-sm text-muted-foreground">
                        {{ step.substitution }}
                      </p>
                      <p class="mt-2 text-lg font-bold text-foreground">
                        {{
                          formatDisplayValue(
                            fromBaseUnit(step.result, unit, step.dimension),
                          )
                        }}
                        {{ unitLabel(unit, step.dimension) }}
                      </p>
                    </div>
                  </div>
                  <p v-else class="text-sm text-muted-foreground">
                    {{ __("Enter a valid property to see the calculation path.") }}
                  </p>
                </AccordionContent>
              </AccordionItem>
            </Accordion>
          </CardContent>
        </Card>
      </div>
    </div>
  </div>
</template>

<style scoped>
.cube-calculator {
  --cube-accent: var(--chart-1);
}

.cube-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
}

.cube-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  border: 1px solid color-mix(in srgb, var(--cube-accent) 35%, var(--border));
  border-radius: 999px;
  background: color-mix(in srgb, var(--cube-accent) 10%, var(--card));
  color: var(--cube-accent);
  padding: 0.4rem 0.75rem;
  font-size: 0.75rem;
  font-weight: 700;
}

.cube-control {
  display: grid;
  gap: 0.35rem;
  color: var(--muted-foreground);
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
}

.cube-select {
  min-width: 5rem;
  height: 2.75rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  background: var(--background);
  color: var(--foreground);
  padding: 0 2rem 0 0.75rem;
}

.cube-card-title {
  color: var(--foreground);
  font-size: 0.95rem;
  font-weight: 800;
}

.cube-field {
  border-left: 3px solid transparent;
  padding-left: 0.75rem;
}

.cube-field[data-status="entered"] {
  border-left-color: var(--primary);
}

.cube-field[data-status="calculated"] {
  border-left-color: var(--chart-4);
}

.cube-field[data-status="invalid"],
.cube-field[data-status="conflicting"] {
  border-left-color: var(--destructive);
}

.cube-symbol,
.cube-state,
.cube-summary-label {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
}

.cube-symbol {
  font-family: var(--font-mono);
  color: var(--muted-foreground);
}

.cube-state {
  color: var(--muted-foreground);
}

.cube-field[data-status="entered"] .cube-state {
  color: var(--primary);
}

.cube-field[data-status="calculated"] .cube-state {
  color: var(--chart-4);
}

.cube-field[data-status="invalid"] .cube-state,
.cube-field[data-status="conflicting"] .cube-state {
  color: var(--destructive);
}

.cube-unit {
  flex: none;
  padding-right: 0.75rem;
  color: var(--muted-foreground);
  font-family: var(--font-mono);
  font-size: 0.75rem;
}

.cube-field-error {
  margin-top: 0.35rem;
  color: var(--destructive);
  font-size: 0.75rem;
  font-weight: 600;
}

.cube-icon-chip {
  display: inline-flex;
  width: 2.25rem;
  height: 2.25rem;
  align-items: center;
  justify-content: center;
  border: 1px solid color-mix(in srgb, var(--cube-accent) 30%, var(--border));
  border-radius: var(--radius-sm);
  background: color-mix(in srgb, var(--cube-accent) 10%, var(--card));
  color: var(--cube-accent);
}

.cube-summary {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
}

.cube-summary > div,
.cube-step {
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  background: color-mix(in srgb, var(--muted) 45%, var(--card));
  padding: 0.9rem;
}

.cube-summary-label {
  color: var(--muted-foreground);
}

.cube-summary-value {
  margin-top: 0.4rem;
  color: var(--foreground);
  font-size: 1.3rem;
  font-weight: 800;
}

.cube-summary-source {
  margin-top: 0.45rem;
  color: var(--foreground);
  font-size: 0.9rem;
  font-weight: 700;
}

.cube-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.cube-legend span {
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 0.3rem 0.6rem;
  color: var(--muted-foreground);
  font-size: 0.7rem;
  font-weight: 700;
}

.cube-legend [data-tone="entered"] {
  border-color: color-mix(in srgb, var(--primary) 35%, var(--border));
  color: var(--primary);
}

.cube-legend [data-tone="calculated"] {
  border-color: color-mix(in srgb, var(--chart-4) 35%, var(--border));
  color: var(--chart-4);
}

.cube-legend [data-tone="conflicting"] {
  border-color: color-mix(in srgb, var(--destructive) 35%, var(--border));
  color: var(--destructive);
}

.cube-calculator :deep(.cube-input.is-calculated) {
  background: color-mix(in srgb, var(--chart-4) 8%, var(--background));
  color: var(--chart-4);
}

.cube-calculator :deep(.cube-input.is-conflicting),
.cube-calculator :deep(.cube-input.is-invalid) {
  border-color: var(--destructive);
}

@media (max-width: 40rem) {
  .cube-summary {
    grid-template-columns: 1fr;
  }
}
</style>
