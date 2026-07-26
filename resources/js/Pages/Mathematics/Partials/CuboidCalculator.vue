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
  buildCuboidCalculationSteps,
  CUBOID_EDITABLE_FIELD_DEFINITIONS,
  CUBOID_FIELD_DEFINITIONS,
  cuboidFieldDefinition,
  solveCuboid,
} from "@/lib/geometry/cuboid";
import {
  fromBaseUnit,
  LENGTH_UNITS,
  toBaseUnit,
  unitLabel,
} from "@/lib/geometry/units";
import { cmsTranslate as __ } from "@/lib/i18n";
import type {
  CalculatorFieldStatus,
  CuboidCandidate,
  CuboidEnteredValues,
  CuboidFieldDefinition,
  CuboidFieldKey,
  CuboidPartialCandidate,
  LengthUnit,
} from "@/types/geometryCalculator";
import {
  AlertTriangle,
  Box,
  Calculator,
  RotateCcw,
  Sparkles,
  X,
} from "lucide-vue-next";

type NullableNumber = number | null;

type Dimensions = {
  length: NullableNumber;
  width: NullableNumber;
  height: NullableNumber;
};

const props = defineProps<{ dimensions: Dimensions }>();

const emit = defineEmits<{
  (event: "update:dimensions", value: Dimensions): void;
  (event: "submit", value: Dimensions): void;
}>();

const unit = ref<LengthUnit>("cm");
const precision = ref(4);
const selectedCandidateIndex = ref(-1);
const lastEmittedKey = ref("");
const entered = reactive(
  Object.fromEntries(
    CUBOID_EDITABLE_FIELD_DEFINITIONS.map(({ key }) => [key, false]),
  ) as Record<CuboidFieldKey, boolean>,
);
const drafts = reactive(
  Object.fromEntries(
    CUBOID_EDITABLE_FIELD_DEFINITIONS.map(({ key }) => [key, ""]),
  ) as Record<CuboidFieldKey, string>,
);

for (const key of ["length", "width", "height"] as const) {
  const value = props.dimensions[key];
  if (value === null || !Number.isFinite(value) || value <= 0) continue;
  entered[key] = true;
  drafts[key] = String(value);
}

const enteredValues = computed<CuboidEnteredValues>(() => {
  const values: CuboidEnteredValues = {};

  for (const definition of CUBOID_EDITABLE_FIELD_DEFINITIONS) {
    if (!entered[definition.key]) continue;
    const parsed = Number(drafts[definition.key]);
    values[definition.key] = Number.isFinite(parsed)
      ? toBaseUnit(parsed, unit.value, definition.dimension)
      : Number.NaN;
  }

  return values;
});

const solution = computed(() => solveCuboid(enteredValues.value));
const candidateOptions = computed<
  Array<CuboidCandidate | CuboidPartialCandidate>
>(() =>
  solution.value.candidates.length
    ? solution.value.candidates
    : solution.value.partialCandidates,
);
const activeSource = computed<CuboidCandidate | CuboidPartialCandidate | null>(
  () => {
    if (selectedCandidateIndex.value >= 0) {
      return candidateOptions.value[selectedCandidateIndex.value] ?? null;
    }

    return solution.value.selectedCandidate;
  },
);
const activeValues = computed(() =>
  activeSource.value?.values ?? solution.value.values,
);
const activeConflicts = computed(
  () => activeSource.value?.conflicts ?? solution.value.conflicts,
);
const conflictByField = computed(
  () =>
    new Map(
      activeConflicts.value.map((conflict) => [conflict.field, conflict]),
    ),
);
const fieldGroups = computed(() => {
  const groups: Array<{
    key: CuboidFieldDefinition["group"];
    label: string;
    fields: ReturnType<typeof fieldRow>[];
  }> = [
    { key: "dimensions", label: "Dimensions", fields: [] },
    { key: "core", label: "Core properties", fields: [] },
    { key: "face-areas", label: "Face areas", fields: [] },
    { key: "face-diagonals", label: "Face diagonals", fields: [] },
    { key: "face-perimeters", label: "Face perimeters", fields: [] },
  ];

  for (const definition of CUBOID_EDITABLE_FIELD_DEFINITIONS) {
    groups
      .find((group) => group.key === definition.group)
      ?.fields.push(fieldRow(definition));
  }

  return groups.filter(({ fields }) => fields.length);
});
const resultRows = computed(() =>
  CUBOID_FIELD_DEFINITIONS.flatMap((definition) => {
    const value = activeValues.value[definition.key];
    if (value === undefined) return [];

    return [
      {
        ...definition,
        value: formatDisplayValue(
          fromBaseUnit(value, unit.value, definition.dimension),
        ),
        unit: unitLabel(unit.value, definition.dimension),
      },
    ];
  }),
);
const calculationSteps = computed(() =>
  buildCuboidCalculationSteps(activeSource.value, (value, key) => {
    const definition = cuboidFieldDefinition(key);
    return `${formatDisplayValue(
      fromBaseUnit(value, unit.value, definition.dimension),
    )} ${unitLabel(unit.value, definition.dimension)}`;
  }),
);
const hasEnteredValues = computed(() =>
  CUBOID_EDITABLE_FIELD_DEFINITIONS.some(({ key }) => entered[key]),
);
const resolvedDimensions = computed<Dimensions>(() => {
  const values = activeValues.value;
  return {
    length: values.length ?? null,
    width: values.width ?? null,
    height: values.height ?? null,
  };
});
const hasCompleteDimensions = computed(
  () =>
    resolvedDimensions.value.length !== null &&
    resolvedDimensions.value.width !== null &&
    resolvedDimensions.value.height !== null,
);

watch(
  () =>
    candidateOptions.value.map((candidate) =>
      "length" in candidate
        ? [candidate.length, candidate.width, candidate.height]
        : [
            candidate.dimensions.length,
            candidate.dimensions.width,
            candidate.dimensions.height,
          ],
    ),
  () => {
    selectedCandidateIndex.value =
      candidateOptions.value.length === 1 ? 0 : -1;
  },
);

watch(
  resolvedDimensions,
  (dimensions) => {
    const displayDimensions: Dimensions = {
      length:
        dimensions.length === null
          ? null
          : fromBaseUnit(dimensions.length, unit.value, 1),
      width:
        dimensions.width === null
          ? null
          : fromBaseUnit(dimensions.width, unit.value, 1),
      height:
        dimensions.height === null
          ? null
          : fromBaseUnit(dimensions.height, unit.value, 1),
    };
    const key = JSON.stringify(displayDimensions);
    if (key === lastEmittedKey.value) return;

    lastEmittedKey.value = key;
    emit("update:dimensions", displayDimensions);

    if (
      hasCompleteDimensions.value &&
      activeConflicts.value.length === 0 &&
      solution.value.invalidFields.length === 0
    ) {
      emit("submit", displayDimensions);
    }
  },
  { deep: true, immediate: true },
);

watch(unit, (nextUnit, previousUnit) => {
  for (const definition of CUBOID_EDITABLE_FIELD_DEFINITIONS) {
    if (!entered[definition.key]) continue;
    const parsed = Number(drafts[definition.key]);
    if (!Number.isFinite(parsed)) continue;

    const baseValue = toBaseUnit(
      parsed,
      previousUnit,
      definition.dimension,
    );
    drafts[definition.key] = formatUnitConversion(
      fromBaseUnit(baseValue, nextUnit, definition.dimension),
    );
  }
});

function fieldRow(definition: CuboidFieldDefinition) {
  return {
    ...definition,
    status: fieldStatus(definition.key),
    displayValue: fieldDisplayValue(definition.key),
    error: fieldError(definition.key),
    unit: unitLabel(unit.value, definition.dimension),
  };
}

function updateField(key: CuboidFieldKey, value: unknown) {
  const nextValue = String(value ?? "");
  drafts[key] = nextValue;
  entered[key] = nextValue.trim() !== "";
}

function clearField(key: CuboidFieldKey) {
  if (!(key in drafts)) return;
  drafts[key] = "";
  entered[key] = false;
}

function fieldStatus(key: CuboidFieldKey): CalculatorFieldStatus {
  return resolveCalculatorFieldStatus({
    isEntered: entered[key],
    isValid: !solution.value.invalidFields.includes(key),
    isConflicting: conflictByField.value.has(key),
    hasCalculatedValue:
      !entered[key] && activeValues.value[key] !== undefined,
  });
}

function fieldDisplayValue(key: CuboidFieldKey): string {
  if (entered[key]) return drafts[key];
  const value = activeValues.value[key];
  if (value === undefined) return "";

  const definition = cuboidFieldDefinition(key);
  return formatEditableValue(
    fromBaseUnit(value, unit.value, definition.dimension),
  );
}

function fieldError(key: CuboidFieldKey): string {
  if (!entered[key]) return "";

  const parsed = Number(drafts[key]);
  if (!Number.isFinite(parsed)) return __("Enter a valid number.");
  if (parsed <= 0) return __("Enter a value greater than 0.");

  const definition = cuboidFieldDefinition(key);
  const maximum = fromBaseUnit(
    definition.maximum,
    unit.value,
    definition.dimension,
  );
  if (parsed > maximum) {
    return __("Use a value no greater than :maximum.", {
      maximum: `${formatDisplayValue(maximum)} ${unitLabel(
        unit.value,
        definition.dimension,
      )}`,
    });
  }

  const conflict = conflictByField.value.get(key);
  if (!conflict) return "";

  const expected = fromBaseUnit(
    conflict.expectedValue,
    unit.value,
    definition.dimension,
  );
  return __(
    "The entered :field value conflicts with the solved cuboid. Expected :expected.",
    {
      field: __(definition.label),
      expected: `${formatDisplayValue(expected)} ${unitLabel(
        unit.value,
        definition.dimension,
      )}`,
    },
  );
}

function loadExample() {
  reset();
  for (const [key, value] of Object.entries({
    length: "5",
    width: "6",
    height: "4",
  }) as Array<[CuboidFieldKey, string]>) {
    entered[key] = true;
    drafts[key] = value;
  }
}

function reset() {
  selectedCandidateIndex.value = -1;
  for (const definition of CUBOID_EDITABLE_FIELD_DEFINITIONS) {
    entered[definition.key] = false;
    drafts[definition.key] = "";
  }
}

function candidateLabel(
  candidate: CuboidCandidate | CuboidPartialCandidate,
  index: number,
): string {
  const dimensions =
    "length" in candidate
      ? candidate
      : candidate.dimensions;
  const value = (key: "length" | "width" | "height") => {
    const dimension = dimensions[key];
    return dimension === null
      ? "?"
      : formatDisplayValue(fromBaseUnit(dimension, unit.value, 1));
  };

  return `${__("Candidate :number", { number: String(index + 1) })}: ${value(
    "length",
  )} × ${value("width")} × ${value("height")} ${unit.value}`;
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

function formatEditableValue(value: number): string {
  if (!Number.isFinite(value)) return "";
  const absolute = Math.abs(value);
  if ((absolute > 0 && absolute < 1e-8) || absolute >= 1e15) {
    return value.toExponential(precision.value);
  }
  return String(Number(value.toFixed(precision.value)));
}

function formatUnitConversion(value: number): string {
  if (!Number.isFinite(value)) return "";
  const absolute = Math.abs(value);
  if ((absolute > 0 && absolute < 1e-10) || absolute >= 1e15) {
    return value.toExponential(12);
  }
  return String(Number(value.toPrecision(15)));
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
</script>

<template>
  <div class="cuboid-calculator space-y-6">
    <div class="cuboid-toolbar">
      <div>
        <div class="cuboid-badge">
          <Sparkles :size="14" />
          {{ __("Conflict-aware solver") }}
        </div>
        <h3 class="mt-3 text-2xl font-bold text-foreground">
          {{ __("Cuboid Calculator") }}
        </h3>
        <p class="mt-1 text-sm text-muted-foreground">
          {{
            __(
              "Enter any sufficient combination. Extra values are checked for consistency.",
            )
          }}
        </p>
      </div>

      <div class="flex flex-wrap items-end gap-3">
        <label class="cuboid-control">
          <span>{{ __("Unit") }}</span>
          <select v-model="unit" class="cuboid-select">
            <option v-for="item in LENGTH_UNITS" :key="item" :value="item">
              {{ item }}
            </option>
          </select>
        </label>

        <label class="cuboid-control">
          <span>{{ __("Precision") }}</span>
          <select v-model.number="precision" class="cuboid-select">
            <option
              v-for="digits in [2, 4, 6, 8]"
              :key="digits"
              :value="digits"
            >
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

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(21rem,0.65fr)]">
      <div class="space-y-6">
        <Card v-for="group in fieldGroups" :key="group.key">
          <CardHeader>
            <CardTitle class="cuboid-card-title">
              {{ __(group.label) }}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div class="grid gap-4 md:grid-cols-2">
              <div
                v-for="field in group.fields"
                :key="field.key"
                class="cuboid-field"
                :data-status="field.status"
              >
                <div class="mb-1.5 flex items-center justify-between gap-3">
                  <span class="cuboid-symbol">{{ field.symbol }}</span>
                  <span class="cuboid-state">
                    {{ __(statusLabel(field.status)) }}
                  </span>
                </div>

                <FormInput
                  type="number"
                  step="any"
                  :model-value="field.displayValue"
                  :label="__(field.label)"
                  :error="field.error"
                  :input-class="`cuboid-input is-${field.status}`"
                  @update:model-value="updateField(field.key, $event)"
                >
                  <span class="cuboid-unit">{{ field.unit }}</span>
                </FormInput>
                <p v-if="field.error" class="cuboid-field-error">
                  {{ field.error }}
                </p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <div class="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle
              class="cuboid-card-title flex items-center justify-between gap-3"
            >
              <span>{{ __("Solution status") }}</span>
              <span class="cuboid-icon-chip"><Box :size="18" /></span>
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <label
              v-if="candidateOptions.length > 1"
              class="cuboid-candidate-control"
            >
              <span>{{ __("Choose solution") }}</span>
              <select v-model.number="selectedCandidateIndex">
                <option :value="-1" disabled>
                  {{ __("Select a candidate") }}
                </option>
                <option
                  v-for="(candidate, index) in candidateOptions"
                  :key="candidateLabel(candidate, index)"
                  :value="index"
                >
                  {{ candidateLabel(candidate, index) }}
                </option>
              </select>
            </label>

            <div class="cuboid-summary">
              <div>
                <span>{{ __("Length") }}</span>
                <strong>{{
                  resolvedDimensions.length === null
                    ? "-"
                    : `${formatDisplayValue(
                        fromBaseUnit(resolvedDimensions.length, unit, 1),
                      )} ${unit}`
                }}</strong>
              </div>
              <div>
                <span>{{ __("Width") }}</span>
                <strong>{{
                  resolvedDimensions.width === null
                    ? "-"
                    : `${formatDisplayValue(
                        fromBaseUnit(resolvedDimensions.width, unit, 1),
                      )} ${unit}`
                }}</strong>
              </div>
              <div>
                <span>{{ __("Height") }}</span>
                <strong>{{
                  resolvedDimensions.height === null
                    ? "-"
                    : `${formatDisplayValue(
                        fromBaseUnit(resolvedDimensions.height, unit, 1),
                      )} ${unit}`
                }}</strong>
              </div>
            </div>

            <Alert
              v-if="activeConflicts.length"
              class="border-destructive/35 bg-destructive/8 text-destructive"
            >
              <AlertTriangle :size="16" />
              <AlertDescription>
                <ul class="space-y-2">
                  <li
                    v-for="conflict in activeConflicts"
                    :key="`${conflict.field}-${conflict.enteredValue}`"
                    class="cuboid-conflict"
                  >
                    <span>
                      {{
                        __(
                          "The entered :field value conflicts with the solved cuboid. Expected :expected.",
                          {
                            field: __(
                              cuboidFieldDefinition(conflict.field).label,
                            ),
                            expected: `${formatDisplayValue(
                              fromBaseUnit(
                                conflict.expectedValue,
                                unit,
                                cuboidFieldDefinition(conflict.field).dimension,
                              ),
                            )} ${unitLabel(
                              unit,
                              cuboidFieldDefinition(conflict.field).dimension,
                            )}`,
                          },
                        )
                      }}
                    </span>
                    <button
                      type="button"
                      :aria-label="__('Clear conflicting field')"
                      @click="clearField(conflict.field)"
                    >
                      <X :size="14" />
                    </button>
                  </li>
                </ul>
              </AlertDescription>
            </Alert>

            <Alert
              v-if="solution.warnings.length"
              class="border-chart-3/35 bg-chart-3/8 text-foreground"
            >
              <AlertTriangle :size="16" />
              <AlertDescription>
                <ul class="space-y-1">
                  <li v-for="warning in solution.warnings" :key="warning">
                    {{ __(warning) }}
                  </li>
                </ul>
              </AlertDescription>
            </Alert>

            <Alert
              v-else-if="!hasEnteredValues"
              class="border-border bg-muted/45 text-muted-foreground"
            >
              <AlertDescription>
                {{ __("Enter any cuboid property or load the example.") }}
              </AlertDescription>
            </Alert>

            <div class="cuboid-legend">
              <span data-tone="entered">{{ __("Entered") }}</span>
              <span data-tone="calculated">{{ __("Calculated") }}</span>
              <span data-tone="conflicting">{{ __("Conflicting") }}</span>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle class="cuboid-card-title">
              {{ __("How it is calculated") }}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <Accordion type="single" collapsible default-value="steps">
              <AccordionItem value="steps" class="border-none">
                <AccordionTrigger
                  class="py-0 text-left text-sm font-semibold hover:no-underline"
                >
                  {{ __("Show calculation steps") }}
                </AccordionTrigger>
                <AccordionContent class="pt-4">
                  <div v-if="calculationSteps.length" class="cuboid-steps">
                    <div
                      v-for="step in calculationSteps"
                      :key="`${step.field}-${step.formula}`"
                      class="cuboid-step"
                    >
                      <div>
                        <strong>{{ __(step.label) }}</strong>
                        <code>{{ step.formula }}</code>
                      </div>
                      <p>{{ step.substitution }}</p>
                      <b>
                        {{
                          formatDisplayValue(
                            fromBaseUnit(
                              step.result,
                              unit,
                              step.dimension,
                            ),
                          )
                        }}
                        {{ unitLabel(unit, step.dimension) }}
                      </b>
                    </div>
                  </div>
                  <p v-else class="text-sm text-muted-foreground">
                    {{
                      __(
                        "Enter enough compatible information to see the calculation path.",
                      )
                    }}
                  </p>
                </AccordionContent>
              </AccordionItem>
            </Accordion>
          </CardContent>
        </Card>
      </div>
    </div>

    <Card v-if="resultRows.length">
      <CardHeader>
        <CardTitle class="cuboid-card-title">
          {{ __("Calculated properties") }}
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div class="cuboid-result-grid">
          <div v-for="row in resultRows" :key="row.key">
            <span>{{ __(row.label) }}</span>
            <code>{{ row.symbol }}</code>
            <strong>{{ row.value }}</strong>
            <small>{{ row.unit }}</small>
          </div>
        </div>
      </CardContent>
    </Card>
  </div>
</template>

<style scoped>
.cuboid-calculator {
  --cuboid-accent: var(--chart-4);
}

.cuboid-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
}

.cuboid-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  border: 1px solid color-mix(in srgb, var(--cuboid-accent) 35%, var(--border));
  border-radius: 999px;
  background: color-mix(in srgb, var(--cuboid-accent) 10%, var(--card));
  color: var(--cuboid-accent);
  padding: 0.4rem 0.75rem;
  font-size: 0.75rem;
  font-weight: 700;
}

.cuboid-control,
.cuboid-candidate-control {
  display: grid;
  gap: 0.35rem;
  color: var(--muted-foreground);
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
}

.cuboid-select,
.cuboid-candidate-control select {
  min-width: 5rem;
  height: 2.75rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  background: var(--background);
  color: var(--foreground);
  padding: 0 2rem 0 0.75rem;
}

.cuboid-candidate-control select {
  width: 100%;
  text-transform: none;
}

.cuboid-card-title {
  color: var(--foreground);
  font-size: 0.95rem;
  font-weight: 800;
}

.cuboid-field {
  border-left: 3px solid transparent;
  padding-left: 0.75rem;
}

.cuboid-field[data-status="entered"] {
  border-left-color: var(--primary);
}

.cuboid-field[data-status="calculated"] {
  border-left-color: var(--cuboid-accent);
}

.cuboid-field[data-status="invalid"],
.cuboid-field[data-status="conflicting"] {
  border-left-color: var(--destructive);
}

.cuboid-symbol,
.cuboid-state,
.cuboid-summary span {
  color: var(--muted-foreground);
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
}

.cuboid-symbol {
  font-family: var(--font-mono);
}

.cuboid-field[data-status="entered"] .cuboid-state {
  color: var(--primary);
}

.cuboid-field[data-status="calculated"] .cuboid-state {
  color: var(--cuboid-accent);
}

.cuboid-field[data-status="invalid"] .cuboid-state,
.cuboid-field[data-status="conflicting"] .cuboid-state {
  color: var(--destructive);
}

.cuboid-unit {
  flex: none;
  padding-right: 0.75rem;
  color: var(--muted-foreground);
  font-family: var(--font-mono);
  font-size: 0.75rem;
}

.cuboid-field-error {
  margin-top: 0.35rem;
  color: var(--destructive);
  font-size: 0.75rem;
  font-weight: 600;
}

.cuboid-icon-chip {
  display: inline-flex;
  width: 2.25rem;
  height: 2.25rem;
  align-items: center;
  justify-content: center;
  border: 1px solid color-mix(in srgb, var(--cuboid-accent) 30%, var(--border));
  border-radius: var(--radius-sm);
  background: color-mix(in srgb, var(--cuboid-accent) 10%, var(--card));
  color: var(--cuboid-accent);
}

.cuboid-summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  overflow: hidden;
}

.cuboid-summary > div {
  min-width: 0;
  border-right: 1px solid var(--border);
  padding: 0.8rem;
}

.cuboid-summary > div:last-child {
  border-right: 0;
}

.cuboid-summary strong {
  display: block;
  overflow-wrap: anywhere;
  margin-top: 0.35rem;
  color: var(--foreground);
  font-size: 0.95rem;
}

.cuboid-conflict {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
}

.cuboid-conflict button {
  display: inline-flex;
  width: 1.8rem;
  height: 1.8rem;
  flex: none;
  align-items: center;
  justify-content: center;
  border: 1px solid currentColor;
  border-radius: var(--radius-xs);
}

.cuboid-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}

.cuboid-legend span {
  border-left: 3px solid var(--border);
  color: var(--muted-foreground);
  padding-left: 0.45rem;
  font-size: 0.68rem;
  font-weight: 700;
}

.cuboid-legend span[data-tone="entered"] {
  border-left-color: var(--primary);
}

.cuboid-legend span[data-tone="calculated"] {
  border-left-color: var(--cuboid-accent);
}

.cuboid-legend span[data-tone="conflicting"] {
  border-left-color: var(--destructive);
}

.cuboid-steps {
  display: grid;
  max-height: 28rem;
  overflow-y: auto;
}

.cuboid-step {
  display: grid;
  gap: 0.45rem;
  border-bottom: 1px solid var(--border);
  padding: 0.8rem 0;
}

.cuboid-step:last-child {
  border-bottom: 0;
}

.cuboid-step > div {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
}

.cuboid-step strong,
.cuboid-step b {
  color: var(--foreground);
  font-size: 0.8rem;
}

.cuboid-step code,
.cuboid-step p {
  color: var(--muted-foreground);
  font-size: 0.72rem;
}

.cuboid-result-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  overflow: hidden;
}

.cuboid-result-grid > div {
  display: grid;
  min-width: 0;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 0.2rem 0.5rem;
  border-right: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
  padding: 0.8rem;
}

.cuboid-result-grid > div:nth-child(4n) {
  border-right: 0;
}

.cuboid-result-grid span,
.cuboid-result-grid small,
.cuboid-result-grid code {
  color: var(--muted-foreground);
  font-size: 0.68rem;
}

.cuboid-result-grid code,
.cuboid-result-grid small {
  text-align: right;
}

.cuboid-result-grid strong {
  overflow-wrap: anywhere;
  color: var(--foreground);
  font-size: 0.95rem;
}

@media (max-width: 64rem) {
  .cuboid-result-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .cuboid-result-grid > div:nth-child(4n) {
    border-right: 1px solid var(--border);
  }

  .cuboid-result-grid > div:nth-child(2n) {
    border-right: 0;
  }
}

@media (max-width: 36rem) {
  .cuboid-summary,
  .cuboid-result-grid {
    grid-template-columns: 1fr;
  }

  .cuboid-summary > div,
  .cuboid-result-grid > div {
    border-right: 0;
  }
}
</style>
