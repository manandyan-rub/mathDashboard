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
import { AlertTriangle, Box, Sparkles } from "lucide-vue-next";

type NullableNumber = number | null;
type NumericLike = number | string | null | undefined;

type Dimensions = {
  length: NullableNumber;
  width: NullableNumber;
  height: NullableNumber;
};

type CubeDraftDimensions = {
  length: NumericLike;
  width: NumericLike;
  height: NumericLike;
};

type CubeKnowns = {
  volume: NumericLike;
  surfaceArea: NumericLike;
  spaceDiagonal: NumericLike;
  radiusOfCircumscribedSphere: NumericLike;
  faceAreas: {
    lw: NumericLike;
    lh: NumericLike;
    wh: NumericLike;
  };
  faceDiagonals: {
    lw: NumericLike;
    lh: NumericLike;
    wh: NumericLike;
  };
  facePerimeters: {
    lw: NumericLike;
    lh: NumericLike;
    wh: NumericLike;
  };
};

type PartialResult = {
  length: NullableNumber;
  width: NullableNumber;
  height: NullableNumber;
  volume: NullableNumber;
  surfaceArea: NullableNumber;
  lateralSurface: NullableNumber;
  edgeSum: NullableNumber;
  spaceDiagonal: NullableNumber;
  radiusOfCircumscribedSphere: NullableNumber;
  faceAreas: {
    lw: NullableNumber;
    lh: NullableNumber;
    wh: NullableNumber;
  };
  faceDiagonals: {
    lw: NullableNumber;
    lh: NullableNumber;
    wh: NullableNumber;
  };
  facePerimeters: {
    lw: NullableNumber;
    lh: NullableNumber;
    wh: NullableNumber;
  };
};

type FieldState = {
  value: NullableNumber;
  error: string;
  present: boolean;
};

type CubeStep = {
  title: string;
  formula: string;
  detail: string;
  result: NullableNumber;
  helper?: string;
};

type CubeAnalysis = {
  resolved: Dimensions;
  result: PartialResult;
  fieldErrors: Record<string, string>;
  errors: string[];
  warnings: string[];
  steps: CubeStep[];
  hasAnyInput: boolean;
};

type SolverInputs = {
  length: NullableNumber;
  width: NullableNumber;
  height: NullableNumber;
  volume: NullableNumber;
  surfaceArea: NullableNumber;
  spaceDiagonal: NullableNumber;
  radiusOfCircumscribedSphere: NullableNumber;
  faceAreas: {
    lw: NullableNumber;
    lh: NullableNumber;
    wh: NullableNumber;
  };
  faceDiagonals: {
    lw: NullableNumber;
    lh: NullableNumber;
    wh: NullableNumber;
  };
  facePerimeters: {
    lw: NullableNumber;
    lh: NullableNumber;
    wh: NullableNumber;
  };
};

const props = defineProps<{ dimensions: Dimensions }>();

const emit = defineEmits<{
  (e: "update:dimensions", value: Dimensions): void;
  (e: "submit", value: Dimensions): void;
}>();

const MAX_SIDE_LENGTH = 1_000_000;
const MAX_FACE_AREA = MAX_SIDE_LENGTH ** 2;
const MAX_TOTAL_SURFACE = 6 * MAX_FACE_AREA;
const MAX_FACE_DIAGONAL = Math.sqrt(2) * MAX_SIDE_LENGTH;
const MAX_SPACE_DIAGONAL = Math.sqrt(3) * MAX_SIDE_LENGTH;
const MAX_FACE_PERIMETER = 4 * MAX_SIDE_LENGTH;
const MAX_CIRCUMSPHERE_RADIUS = MAX_SPACE_DIAGONAL / 2;
const MAX_VOLUME = MAX_SIDE_LENGTH ** 3;
const sampleDimensions: Dimensions = {
  length: props.dimensions.length ?? 4,
  width: props.dimensions.width ?? 3,
  height: props.dimensions.height ?? 2,
};

const draftDimensions = reactive<CubeDraftDimensions>({
  length: props.dimensions.length,
  width: props.dimensions.width,
  height: props.dimensions.height,
});

const knowns = reactive<CubeKnowns>({
  volume: null,
  surfaceArea: null,
  spaceDiagonal: null,
  radiusOfCircumscribedSphere: null,
  faceAreas: {
    lw: null,
    lh: null,
    wh: null,
  },
  faceDiagonals: {
    lw: null,
    lh: null,
    wh: null,
  },
  facePerimeters: {
    lw: null,
    lh: null,
    wh: null,
  },
});

const lastSubmittedKey = ref("");

const analysis = computed<CubeAnalysis>(() => {
  const length = numericState(draftDimensions.length, MAX_SIDE_LENGTH);
  const width = numericState(draftDimensions.width, MAX_SIDE_LENGTH);
  const height = numericState(draftDimensions.height, MAX_SIDE_LENGTH);
  const volume = numericState(knowns.volume, MAX_VOLUME);
  const surfaceArea = numericState(knowns.surfaceArea, MAX_TOTAL_SURFACE);
  const spaceDiagonal = numericState(knowns.spaceDiagonal, MAX_SPACE_DIAGONAL);
  const radiusOfCircumscribedSphere = numericState(
    knowns.radiusOfCircumscribedSphere,
    MAX_CIRCUMSPHERE_RADIUS,
  );
  const areaLw = numericState(knowns.faceAreas.lw, MAX_FACE_AREA);
  const areaLh = numericState(knowns.faceAreas.lh, MAX_FACE_AREA);
  const areaWh = numericState(knowns.faceAreas.wh, MAX_FACE_AREA);
  const diagonalLw = numericState(knowns.faceDiagonals.lw, MAX_FACE_DIAGONAL);
  const diagonalLh = numericState(knowns.faceDiagonals.lh, MAX_FACE_DIAGONAL);
  const diagonalWh = numericState(knowns.faceDiagonals.wh, MAX_FACE_DIAGONAL);
  const perimeterLw = numericState(knowns.facePerimeters.lw, MAX_FACE_PERIMETER);
  const perimeterLh = numericState(knowns.facePerimeters.lh, MAX_FACE_PERIMETER);
  const perimeterWh = numericState(knowns.facePerimeters.wh, MAX_FACE_PERIMETER);

  const fieldErrors = {
    "dimensions.length": length.error,
    "dimensions.width": width.error,
    "dimensions.height": height.error,
    "knowns.volume": volume.error,
    "knowns.surfaceArea": surfaceArea.error,
    "knowns.spaceDiagonal": spaceDiagonal.error,
    "knowns.radiusOfCircumscribedSphere": radiusOfCircumscribedSphere.error,
    "knowns.faceAreas.lw": areaLw.error,
    "knowns.faceAreas.lh": areaLh.error,
    "knowns.faceAreas.wh": areaWh.error,
    "knowns.faceDiagonals.lw": diagonalLw.error,
    "knowns.faceDiagonals.lh": diagonalLh.error,
    "knowns.faceDiagonals.wh": diagonalWh.error,
    "knowns.facePerimeters.lw": perimeterLw.error,
    "knowns.facePerimeters.lh": perimeterLh.error,
    "knowns.facePerimeters.wh": perimeterWh.error,
  };

  const errors = collectMessages(fieldErrors);
  const solverInputs: SolverInputs = {
    length: length.value,
    width: width.value,
    height: height.value,
    volume: volume.value,
    surfaceArea: surfaceArea.value,
    spaceDiagonal: spaceDiagonal.value,
    radiusOfCircumscribedSphere: radiusOfCircumscribedSphere.value,
    faceAreas: {
      lw: areaLw.value,
      lh: areaLh.value,
      wh: areaWh.value,
    },
    faceDiagonals: {
      lw: diagonalLw.value,
      lh: diagonalLh.value,
      wh: diagonalWh.value,
    },
    facePerimeters: {
      lw: perimeterLw.value,
      lh: perimeterLh.value,
      wh: perimeterWh.value,
    },
  };

  const resolved = runSolver(solverInputs);
  const result = buildPartialResult(resolved.length, resolved.width, resolved.height, solverInputs);
  const warnings = collectWarnings(result, solverInputs);
  const hasAnyInput = [
    length.present,
    width.present,
    height.present,
    volume.present,
    surfaceArea.present,
    spaceDiagonal.present,
    radiusOfCircumscribedSphere.present,
    areaLw.present,
    areaLh.present,
    areaWh.present,
    diagonalLw.present,
    diagonalLh.present,
    diagonalWh.present,
    perimeterLw.present,
    perimeterLh.present,
    perimeterWh.present,
  ].some(Boolean);

  if (
    hasAnyInput &&
    !errors.length &&
    (resolved.length === null || resolved.width === null || resolved.height === null)
  ) {
    warnings.push(
      __(
        "Not enough compatible information is available to solve all three dimensions yet. Any confirmed properties are still shown below.",
      ),
    );
  }

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

const resultHasData = computed(() => {
  const result = analysis.value.result;

  return [
    result.length,
    result.width,
    result.height,
    result.volume,
    result.surfaceArea,
    result.lateralSurface,
    result.edgeSum,
    result.spaceDiagonal,
    result.radiusOfCircumscribedSphere,
    result.faceAreas.lw,
    result.faceAreas.lh,
    result.faceAreas.wh,
    result.faceDiagonals.lw,
    result.faceDiagonals.lh,
    result.faceDiagonals.wh,
    result.facePerimeters.lw,
    result.facePerimeters.lh,
    result.facePerimeters.wh,
  ].some((value) => value !== null);
});

const resolvedCount = computed(
  () =>
    [
      analysis.value.resolved.length,
      analysis.value.resolved.width,
      analysis.value.resolved.height,
    ].filter((value) => value !== null).length,
);

const coreResultCards = computed(() => [
  {
    label: "Volume",
    value: analysis.value.result.volume,
    helper: "cubic units",
  },
  {
    label: "Total surface",
    value: analysis.value.result.surfaceArea,
    helper: "square units",
  },
  {
    label: "Lateral surface (lw base)",
    value: analysis.value.result.lateralSurface,
    helper: "square units",
  },
  {
    label: "Edge sum",
    value: analysis.value.result.edgeSum,
    helper: "units",
  },
  {
    label: "Space diagonal",
    value: analysis.value.result.spaceDiagonal,
    helper: "units",
  },
  {
    label: "Circumscribed sphere radius",
    value: analysis.value.result.radiusOfCircumscribedSphere,
    helper: "units",
  },
]);

const faceAreaCards = computed(() => [
  {
    label: "lw face area",
    value: analysis.value.result.faceAreas.lw,
    helper: "square units",
  },
  {
    label: "lh face area",
    value: analysis.value.result.faceAreas.lh,
    helper: "square units",
  },
  {
    label: "wh face area",
    value: analysis.value.result.faceAreas.wh,
    helper: "square units",
  },
]);

const faceDiagonalCards = computed(() => [
  {
    label: "lw face diagonal",
    value: analysis.value.result.faceDiagonals.lw,
    helper: "units",
  },
  {
    label: "lh face diagonal",
    value: analysis.value.result.faceDiagonals.lh,
    helper: "units",
  },
  {
    label: "wh face diagonal",
    value: analysis.value.result.faceDiagonals.wh,
    helper: "units",
  },
]);

const facePerimeterCards = computed(() => [
  {
    label: "lw face perimeter",
    value: analysis.value.result.facePerimeters.lw,
    helper: "units",
  },
  {
    label: "lh face perimeter",
    value: analysis.value.result.facePerimeters.lh,
    helper: "units",
  },
  {
    label: "wh face perimeter",
    value: analysis.value.result.facePerimeters.wh,
    helper: "units",
  },
]);

watch(
  () => props.dimensions,
  (value) => {
    draftDimensions.length = value.length;
    draftDimensions.width = value.width;
    draftDimensions.height = value.height;
  },
  { deep: true },
);

watch(
  () => analysis.value.resolved,
  (resolved) => {
    const next = normalizeDimensions(resolved);
    const key = JSON.stringify(next);

    if (key === lastSubmittedKey.value) {
      return;
    }

    lastSubmittedKey.value = key;
    emit("update:dimensions", next);

    if (
      next.length !== null &&
      next.width !== null &&
      next.height !== null &&
      !analysis.value.errors.length
    ) {
      emit("submit", next);
    }
  },
  { deep: true, immediate: true },
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
          max: new Intl.NumberFormat().format(max),
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

function isPositiveNumber(value: NullableNumber): value is number {
  return value !== null && Number.isFinite(value) && value > 0;
}

function firstPositive(
  ...values: Array<number | null | undefined>
): number | null {
  for (const value of values) {
    if (value !== null && value !== undefined && Number.isFinite(value) && value > 0) {
      return value;
    }
  }

  return null;
}

function sqrtPositive(value: number): NullableNumber {
  return value >= 0 ? Math.sqrt(value) : null;
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

function normalizeDimensions(value: Dimensions): Dimensions {
  return {
    length: value.length !== null ? roundTo(value.length) : null,
    width: value.width !== null ? roundTo(value.width) : null,
    height: value.height !== null ? roundTo(value.height) : null,
  };
}

function resetSample() {
  draftDimensions.length = sampleDimensions.length;
  draftDimensions.width = sampleDimensions.width;
  draftDimensions.height = sampleDimensions.height;

  knowns.volume = null;
  knowns.surfaceArea = null;
  knowns.spaceDiagonal = null;
  knowns.radiusOfCircumscribedSphere = null;
  knowns.faceAreas.lw = null;
  knowns.faceAreas.lh = null;
  knowns.faceAreas.wh = null;
  knowns.faceDiagonals.lw = null;
  knowns.faceDiagonals.lh = null;
  knowns.faceDiagonals.wh = null;
  knowns.facePerimeters.lw = null;
  knowns.facePerimeters.lh = null;
  knowns.facePerimeters.wh = null;
}

function clearAll() {
  draftDimensions.length = null;
  draftDimensions.width = null;
  draftDimensions.height = null;

  knowns.volume = null;
  knowns.surfaceArea = null;
  knowns.spaceDiagonal = null;
  knowns.radiusOfCircumscribedSphere = null;
  knowns.faceAreas.lw = null;
  knowns.faceAreas.lh = null;
  knowns.faceAreas.wh = null;
  knowns.faceDiagonals.lw = null;
  knowns.faceDiagonals.lh = null;
  knowns.faceDiagonals.wh = null;
  knowns.facePerimeters.lw = null;
  knowns.facePerimeters.lh = null;
  knowns.facePerimeters.wh = null;
}

function runSolver(inputs: SolverInputs): Dimensions {
  let length = inputs.length;
  let width = inputs.width;
  let height = inputs.height;

  const volume = inputs.volume;
  const surfaceArea = inputs.surfaceArea;
  const spaceDiagonal =
    inputs.spaceDiagonal ??
    (isPositiveNumber(inputs.radiusOfCircumscribedSphere)
      ? inputs.radiusOfCircumscribedSphere * 2
      : null);

  for (let iteration = 0; iteration < 20; iteration += 1) {
    let changed = false;

    if (length === null) {
      const candidate = firstPositive(
        isPositiveNumber(width) && isPositiveNumber(height) && isPositiveNumber(volume)
          ? volume / (width * height)
          : null,
        isPositiveNumber(width) &&
          isPositiveNumber(height) &&
          isPositiveNumber(surfaceArea)
          ? (surfaceArea / 2 - width * height) / (width + height)
          : null,
        isPositiveNumber(width) &&
          isPositiveNumber(height) &&
          isPositiveNumber(spaceDiagonal)
          ? sqrtPositive(spaceDiagonal ** 2 - width ** 2 - height ** 2)
          : null,
        isPositiveNumber(width) && isPositiveNumber(inputs.faceAreas.lw)
          ? inputs.faceAreas.lw / width
          : null,
        isPositiveNumber(height) && isPositiveNumber(inputs.faceAreas.lh)
          ? inputs.faceAreas.lh / height
          : null,
        isPositiveNumber(width) && isPositiveNumber(inputs.faceDiagonals.lw)
          ? sqrtPositive(inputs.faceDiagonals.lw ** 2 - width ** 2)
          : null,
        isPositiveNumber(height) && isPositiveNumber(inputs.faceDiagonals.lh)
          ? sqrtPositive(inputs.faceDiagonals.lh ** 2 - height ** 2)
          : null,
        isPositiveNumber(width) && isPositiveNumber(inputs.facePerimeters.lw)
          ? inputs.facePerimeters.lw / 2 - width
          : null,
        isPositiveNumber(height) && isPositiveNumber(inputs.facePerimeters.lh)
          ? inputs.facePerimeters.lh / 2 - height
          : null,
      );

      if (candidate !== null) {
        length = candidate;
        changed = true;
      }
    }

    if (width === null) {
      const candidate = firstPositive(
        isPositiveNumber(length) && isPositiveNumber(height) && isPositiveNumber(volume)
          ? volume / (length * height)
          : null,
        isPositiveNumber(length) &&
          isPositiveNumber(height) &&
          isPositiveNumber(surfaceArea)
          ? (surfaceArea / 2 - length * height) / (length + height)
          : null,
        isPositiveNumber(length) &&
          isPositiveNumber(height) &&
          isPositiveNumber(spaceDiagonal)
          ? sqrtPositive(spaceDiagonal ** 2 - length ** 2 - height ** 2)
          : null,
        isPositiveNumber(length) && isPositiveNumber(inputs.faceAreas.lw)
          ? inputs.faceAreas.lw / length
          : null,
        isPositiveNumber(height) && isPositiveNumber(inputs.faceAreas.wh)
          ? inputs.faceAreas.wh / height
          : null,
        isPositiveNumber(length) && isPositiveNumber(inputs.faceDiagonals.lw)
          ? sqrtPositive(inputs.faceDiagonals.lw ** 2 - length ** 2)
          : null,
        isPositiveNumber(height) && isPositiveNumber(inputs.faceDiagonals.wh)
          ? sqrtPositive(inputs.faceDiagonals.wh ** 2 - height ** 2)
          : null,
        isPositiveNumber(length) && isPositiveNumber(inputs.facePerimeters.lw)
          ? inputs.facePerimeters.lw / 2 - length
          : null,
        isPositiveNumber(height) && isPositiveNumber(inputs.facePerimeters.wh)
          ? inputs.facePerimeters.wh / 2 - height
          : null,
      );

      if (candidate !== null) {
        width = candidate;
        changed = true;
      }
    }

    if (height === null) {
      const candidate = firstPositive(
        isPositiveNumber(length) && isPositiveNumber(width) && isPositiveNumber(volume)
          ? volume / (length * width)
          : null,
        isPositiveNumber(length) &&
          isPositiveNumber(width) &&
          isPositiveNumber(surfaceArea)
          ? (surfaceArea / 2 - length * width) / (length + width)
          : null,
        isPositiveNumber(length) &&
          isPositiveNumber(width) &&
          isPositiveNumber(spaceDiagonal)
          ? sqrtPositive(spaceDiagonal ** 2 - length ** 2 - width ** 2)
          : null,
        isPositiveNumber(length) && isPositiveNumber(inputs.faceAreas.lh)
          ? inputs.faceAreas.lh / length
          : null,
        isPositiveNumber(width) && isPositiveNumber(inputs.faceAreas.wh)
          ? inputs.faceAreas.wh / width
          : null,
        isPositiveNumber(length) && isPositiveNumber(inputs.faceDiagonals.lh)
          ? sqrtPositive(inputs.faceDiagonals.lh ** 2 - length ** 2)
          : null,
        isPositiveNumber(width) && isPositiveNumber(inputs.faceDiagonals.wh)
          ? sqrtPositive(inputs.faceDiagonals.wh ** 2 - width ** 2)
          : null,
        isPositiveNumber(length) && isPositiveNumber(inputs.facePerimeters.lh)
          ? inputs.facePerimeters.lh / 2 - length
          : null,
        isPositiveNumber(width) && isPositiveNumber(inputs.facePerimeters.wh)
          ? inputs.facePerimeters.wh / 2 - width
          : null,
      );

      if (candidate !== null) {
        height = candidate;
        changed = true;
      }
    }

    if (
      isPositiveNumber(length) &&
      width === null &&
      height === null
    ) {
      if (isPositiveNumber(inputs.faceAreas.lw) && isPositiveNumber(inputs.faceAreas.lh)) {
        width = inputs.faceAreas.lw / length;
        height = inputs.faceAreas.lh / length;
        changed = true;
      } else if (
        isPositiveNumber(inputs.faceAreas.lw) &&
        isPositiveNumber(volume)
      ) {
        width = inputs.faceAreas.lw / length;
        height = volume / inputs.faceAreas.lw;
        changed = true;
      } else if (
        isPositiveNumber(inputs.faceAreas.lh) &&
        isPositiveNumber(volume)
      ) {
        width = volume / inputs.faceAreas.lh;
        height = inputs.faceAreas.lh / length;
        changed = true;
      } else if (
        isPositiveNumber(inputs.faceDiagonals.lw) &&
        isPositiveNumber(inputs.faceDiagonals.lh)
      ) {
        const nextWidth = sqrtPositive(inputs.faceDiagonals.lw ** 2 - length ** 2);
        const nextHeight = sqrtPositive(inputs.faceDiagonals.lh ** 2 - length ** 2);

        if (isPositiveNumber(nextWidth) && isPositiveNumber(nextHeight)) {
          width = nextWidth;
          height = nextHeight;
          changed = true;
        }
      } else if (
        isPositiveNumber(inputs.facePerimeters.lw) &&
        isPositiveNumber(inputs.facePerimeters.lh)
      ) {
        const nextWidth = inputs.facePerimeters.lw / 2 - length;
        const nextHeight = inputs.facePerimeters.lh / 2 - length;

        if (nextWidth > 0 && nextHeight > 0) {
          width = nextWidth;
          height = nextHeight;
          changed = true;
        }
      }
    }

    if (
      isPositiveNumber(width) &&
      length === null &&
      height === null
    ) {
      if (isPositiveNumber(inputs.faceAreas.lw) && isPositiveNumber(inputs.faceAreas.wh)) {
        length = inputs.faceAreas.lw / width;
        height = inputs.faceAreas.wh / width;
        changed = true;
      } else if (
        isPositiveNumber(inputs.faceAreas.lw) &&
        isPositiveNumber(volume)
      ) {
        length = inputs.faceAreas.lw / width;
        height = volume / inputs.faceAreas.lw;
        changed = true;
      } else if (
        isPositiveNumber(inputs.faceAreas.wh) &&
        isPositiveNumber(volume)
      ) {
        length = volume / inputs.faceAreas.wh;
        height = inputs.faceAreas.wh / width;
        changed = true;
      } else if (
        isPositiveNumber(inputs.faceDiagonals.lw) &&
        isPositiveNumber(inputs.faceDiagonals.wh)
      ) {
        const nextLength = sqrtPositive(inputs.faceDiagonals.lw ** 2 - width ** 2);
        const nextHeight = sqrtPositive(inputs.faceDiagonals.wh ** 2 - width ** 2);

        if (isPositiveNumber(nextLength) && isPositiveNumber(nextHeight)) {
          length = nextLength;
          height = nextHeight;
          changed = true;
        }
      } else if (
        isPositiveNumber(inputs.facePerimeters.lw) &&
        isPositiveNumber(inputs.facePerimeters.wh)
      ) {
        const nextLength = inputs.facePerimeters.lw / 2 - width;
        const nextHeight = inputs.facePerimeters.wh / 2 - width;

        if (nextLength > 0 && nextHeight > 0) {
          length = nextLength;
          height = nextHeight;
          changed = true;
        }
      }
    }

    if (
      isPositiveNumber(height) &&
      length === null &&
      width === null
    ) {
      if (isPositiveNumber(inputs.faceAreas.lh) && isPositiveNumber(inputs.faceAreas.wh)) {
        length = inputs.faceAreas.lh / height;
        width = inputs.faceAreas.wh / height;
        changed = true;
      } else if (
        isPositiveNumber(inputs.faceAreas.lh) &&
        isPositiveNumber(volume)
      ) {
        length = inputs.faceAreas.lh / height;
        width = volume / inputs.faceAreas.lh;
        changed = true;
      } else if (
        isPositiveNumber(inputs.faceAreas.wh) &&
        isPositiveNumber(volume)
      ) {
        length = volume / inputs.faceAreas.wh;
        width = inputs.faceAreas.wh / height;
        changed = true;
      } else if (
        isPositiveNumber(inputs.faceDiagonals.lh) &&
        isPositiveNumber(inputs.faceDiagonals.wh)
      ) {
        const nextLength = sqrtPositive(inputs.faceDiagonals.lh ** 2 - height ** 2);
        const nextWidth = sqrtPositive(inputs.faceDiagonals.wh ** 2 - height ** 2);

        if (isPositiveNumber(nextLength) && isPositiveNumber(nextWidth)) {
          length = nextLength;
          width = nextWidth;
          changed = true;
        }
      } else if (
        isPositiveNumber(inputs.facePerimeters.lh) &&
        isPositiveNumber(inputs.facePerimeters.wh)
      ) {
        const nextLength = inputs.facePerimeters.lh / 2 - height;
        const nextWidth = inputs.facePerimeters.wh / 2 - height;

        if (nextLength > 0 && nextWidth > 0) {
          length = nextLength;
          width = nextWidth;
          changed = true;
        }
      }
    }

    if (length === null && width === null && height === null) {
      if (
        isPositiveNumber(inputs.faceAreas.lw) &&
        isPositiveNumber(inputs.faceAreas.lh) &&
        isPositiveNumber(inputs.faceAreas.wh)
      ) {
        const nextLength = sqrtPositive(
          (inputs.faceAreas.lw * inputs.faceAreas.lh) / inputs.faceAreas.wh,
        );
        const nextWidth = sqrtPositive(
          (inputs.faceAreas.lw * inputs.faceAreas.wh) / inputs.faceAreas.lh,
        );
        const nextHeight = sqrtPositive(
          (inputs.faceAreas.lh * inputs.faceAreas.wh) / inputs.faceAreas.lw,
        );

        if (
          isPositiveNumber(nextLength) &&
          isPositiveNumber(nextWidth) &&
          isPositiveNumber(nextHeight)
        ) {
          length = nextLength;
          width = nextWidth;
          height = nextHeight;
          changed = true;
        }
      } else if (
        isPositiveNumber(volume) &&
        isPositiveNumber(inputs.faceAreas.lw) &&
        isPositiveNumber(inputs.faceAreas.lh)
      ) {
        length = (inputs.faceAreas.lw * inputs.faceAreas.lh) / volume;
        width = volume / inputs.faceAreas.lh;
        height = volume / inputs.faceAreas.lw;
        changed = true;
      } else if (
        isPositiveNumber(volume) &&
        isPositiveNumber(inputs.faceAreas.lw) &&
        isPositiveNumber(inputs.faceAreas.wh)
      ) {
        length = volume / inputs.faceAreas.wh;
        width = (inputs.faceAreas.lw * inputs.faceAreas.wh) / volume;
        height = volume / inputs.faceAreas.lw;
        changed = true;
      } else if (
        isPositiveNumber(volume) &&
        isPositiveNumber(inputs.faceAreas.lh) &&
        isPositiveNumber(inputs.faceAreas.wh)
      ) {
        length = volume / inputs.faceAreas.wh;
        width = volume / inputs.faceAreas.lh;
        height = (inputs.faceAreas.lh * inputs.faceAreas.wh) / volume;
        changed = true;
      }
    }

    if (!changed) {
      break;
    }
  }

  return {
    length,
    width,
    height,
  };
}

function buildPartialResult(
  length: NullableNumber,
  width: NullableNumber,
  height: NullableNumber,
  inputs: SolverInputs,
): PartialResult {
  const faceAreaLw =
    isPositiveNumber(length) && isPositiveNumber(width)
      ? length * width
      : inputs.faceAreas.lw;
  const faceAreaLh =
    isPositiveNumber(length) && isPositiveNumber(height)
      ? length * height
      : inputs.faceAreas.lh;
  const faceAreaWh =
    isPositiveNumber(width) && isPositiveNumber(height)
      ? width * height
      : inputs.faceAreas.wh;

  const volume =
    isPositiveNumber(length) &&
    isPositiveNumber(width) &&
    isPositiveNumber(height)
      ? length * width * height
      : isPositiveNumber(faceAreaLw) && isPositiveNumber(height)
        ? faceAreaLw * height
        : isPositiveNumber(faceAreaLh) && isPositiveNumber(width)
          ? faceAreaLh * width
          : isPositiveNumber(faceAreaWh) && isPositiveNumber(length)
            ? faceAreaWh * length
            : inputs.volume;

  const surfaceArea =
    isPositiveNumber(faceAreaLw) &&
    isPositiveNumber(faceAreaLh) &&
    isPositiveNumber(faceAreaWh)
      ? 2 * (faceAreaLw + faceAreaLh + faceAreaWh)
      : inputs.surfaceArea;

  const lateralSurface =
    isPositiveNumber(length) &&
    isPositiveNumber(width) &&
    isPositiveNumber(height)
      ? 2 * height * (length + width)
      : null;

  const edgeSum =
    isPositiveNumber(length) &&
    isPositiveNumber(width) &&
    isPositiveNumber(height)
      ? 4 * (length + width + height)
      : null;

  const spaceDiagonal =
    isPositiveNumber(length) &&
    isPositiveNumber(width) &&
    isPositiveNumber(height)
      ? Math.sqrt(length ** 2 + width ** 2 + height ** 2)
      : inputs.spaceDiagonal ??
        (isPositiveNumber(inputs.radiusOfCircumscribedSphere)
          ? inputs.radiusOfCircumscribedSphere * 2
          : null);

  const radiusOfCircumscribedSphere = isPositiveNumber(spaceDiagonal)
    ? spaceDiagonal / 2
    : inputs.radiusOfCircumscribedSphere;

  const faceDiagonalLw =
    isPositiveNumber(length) && isPositiveNumber(width)
      ? Math.sqrt(length ** 2 + width ** 2)
      : inputs.faceDiagonals.lw;
  const faceDiagonalLh =
    isPositiveNumber(length) && isPositiveNumber(height)
      ? Math.sqrt(length ** 2 + height ** 2)
      : inputs.faceDiagonals.lh;
  const faceDiagonalWh =
    isPositiveNumber(width) && isPositiveNumber(height)
      ? Math.sqrt(width ** 2 + height ** 2)
      : inputs.faceDiagonals.wh;

  const facePerimeterLw =
    isPositiveNumber(length) && isPositiveNumber(width)
      ? 2 * (length + width)
      : inputs.facePerimeters.lw;
  const facePerimeterLh =
    isPositiveNumber(length) && isPositiveNumber(height)
      ? 2 * (length + height)
      : inputs.facePerimeters.lh;
  const facePerimeterWh =
    isPositiveNumber(width) && isPositiveNumber(height)
      ? 2 * (width + height)
      : inputs.facePerimeters.wh;

  return {
    length,
    width,
    height,
    volume,
    surfaceArea,
    lateralSurface,
    edgeSum,
    spaceDiagonal,
    radiusOfCircumscribedSphere,
    faceAreas: {
      lw: faceAreaLw,
      lh: faceAreaLh,
      wh: faceAreaWh,
    },
    faceDiagonals: {
      lw: faceDiagonalLw,
      lh: faceDiagonalLh,
      wh: faceDiagonalWh,
    },
    facePerimeters: {
      lw: facePerimeterLw,
      lh: facePerimeterLh,
      wh: facePerimeterWh,
    },
  };
}

function collectWarnings(
  result: PartialResult,
  inputs: SolverInputs,
): string[] {
  const warnings: string[] = [];

  pushMismatchWarning(
    warnings,
    __("Volume"),
    inputs.volume,
    result.volume,
  );
  pushMismatchWarning(
    warnings,
    __("Total surface"),
    inputs.surfaceArea,
    result.surfaceArea,
  );
  pushMismatchWarning(
    warnings,
    __("Space diagonal"),
    inputs.spaceDiagonal,
    result.spaceDiagonal,
  );
  pushMismatchWarning(
    warnings,
    __("Circumscribed sphere radius"),
    inputs.radiusOfCircumscribedSphere,
    result.radiusOfCircumscribedSphere,
  );
  pushMismatchWarning(
    warnings,
    __("lw face area"),
    inputs.faceAreas.lw,
    result.faceAreas.lw,
  );
  pushMismatchWarning(
    warnings,
    __("lh face area"),
    inputs.faceAreas.lh,
    result.faceAreas.lh,
  );
  pushMismatchWarning(
    warnings,
    __("wh face area"),
    inputs.faceAreas.wh,
    result.faceAreas.wh,
  );
  pushMismatchWarning(
    warnings,
    __("lw face diagonal"),
    inputs.faceDiagonals.lw,
    result.faceDiagonals.lw,
  );
  pushMismatchWarning(
    warnings,
    __("lh face diagonal"),
    inputs.faceDiagonals.lh,
    result.faceDiagonals.lh,
  );
  pushMismatchWarning(
    warnings,
    __("wh face diagonal"),
    inputs.faceDiagonals.wh,
    result.faceDiagonals.wh,
  );
  pushMismatchWarning(
    warnings,
    __("lw face perimeter"),
    inputs.facePerimeters.lw,
    result.facePerimeters.lw,
  );
  pushMismatchWarning(
    warnings,
    __("lh face perimeter"),
    inputs.facePerimeters.lh,
    result.facePerimeters.lh,
  );
  pushMismatchWarning(
    warnings,
    __("wh face perimeter"),
    inputs.facePerimeters.wh,
    result.facePerimeters.wh,
  );

  return warnings;
}

function pushMismatchWarning(
  warnings: string[],
  label: string,
  givenValue: NullableNumber,
  derivedValue: NullableNumber,
) {
  if (!isPositiveNumber(givenValue) || !isPositiveNumber(derivedValue)) {
    return;
  }

  const tolerance = Math.max(0.01, Math.max(givenValue, derivedValue) * 0.0001);

  if (Math.abs(givenValue - derivedValue) > tolerance) {
    warnings.push(
      __(
        ":label does not match the solved cuboid dimensions exactly.",
        { label },
      ),
    );
  }
}

function buildSteps(result: PartialResult): CubeStep[] {
  return [
    {
      title: __("Volume"),
      formula: "V = l * w * h",
      detail:
        isPositiveNumber(result.length) &&
        isPositiveNumber(result.width) &&
        isPositiveNumber(result.height)
          ? `${displayNumber(result.length)} * ${displayNumber(result.width)} * ${displayNumber(result.height)}`
          : "",
      result: result.volume,
      helper: "cubic units",
    },
    {
      title: __("Total surface"),
      formula: "S = 2(lw + lh + wh)",
      detail:
        isPositiveNumber(result.length) &&
        isPositiveNumber(result.width) &&
        isPositiveNumber(result.height)
          ? `2(${displayNumber(result.length)} * ${displayNumber(result.width)} + ${displayNumber(result.length)} * ${displayNumber(result.height)} + ${displayNumber(result.width)} * ${displayNumber(result.height)})`
          : "",
      result: result.surfaceArea,
      helper: "square units",
    },
    {
      title: __("Lateral surface (lw base)"),
      formula: "Sl = 2h(l + w)",
      detail:
        isPositiveNumber(result.length) &&
        isPositiveNumber(result.width) &&
        isPositiveNumber(result.height)
          ? `2 * ${displayNumber(result.height)} * (${displayNumber(result.length)} + ${displayNumber(result.width)})`
          : "",
      result: result.lateralSurface,
      helper: "square units",
    },
    {
      title: __("Space diagonal"),
      formula: "d = sqrt(l^2 + w^2 + h^2)",
      detail:
        isPositiveNumber(result.length) &&
        isPositiveNumber(result.width) &&
        isPositiveNumber(result.height)
          ? `sqrt(${displayNumber(result.length)}^2 + ${displayNumber(result.width)}^2 + ${displayNumber(result.height)}^2)`
          : "",
      result: result.spaceDiagonal,
      helper: "units",
    },
    {
      title: __("Circumscribed sphere radius"),
      formula: "R = d / 2",
      detail: isPositiveNumber(result.spaceDiagonal)
        ? `${displayNumber(result.spaceDiagonal)} / 2`
        : "",
      result: result.radiusOfCircumscribedSphere,
      helper: "units",
    },
  ].filter((step) => step.result !== null || step.detail !== "");
}
</script>

<template>
  <div class="cube-calculator space-y-6">
    <div class="cube-toolbar">
      <div class="cube-badge">
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

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_22rem]">
      <div class="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle class="cube-card-title">
              {{ __("Dimensions") }}
            </CardTitle>
          </CardHeader>
          <CardContent class="grid gap-4 md:grid-cols-3">
            <div class="space-y-1.5">
              <FormInput
                v-model="draftDimensions.length"
                type="number"
                step="any"
                :label="__('Length (l)')"
                :error="analysis.fieldErrors['dimensions.length']"
              />
              <p
                v-if="analysis.fieldErrors['dimensions.length']"
                class="cube-field-error"
              >
                {{ analysis.fieldErrors["dimensions.length"] }}
              </p>
            </div>

            <div class="space-y-1.5">
              <FormInput
                v-model="draftDimensions.width"
                type="number"
                step="any"
                :label="__('Width (w)')"
                :error="analysis.fieldErrors['dimensions.width']"
              />
              <p
                v-if="analysis.fieldErrors['dimensions.width']"
                class="cube-field-error"
              >
                {{ analysis.fieldErrors["dimensions.width"] }}
              </p>
            </div>

            <div class="space-y-1.5">
              <FormInput
                v-model="draftDimensions.height"
                type="number"
                step="any"
                :label="__('Height (h)')"
                :error="analysis.fieldErrors['dimensions.height']"
              />
              <p
                v-if="analysis.fieldErrors['dimensions.height']"
                class="cube-field-error"
              >
                {{ analysis.fieldErrors["dimensions.height"] }}
              </p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle class="cube-card-title">
              {{ __("Known global properties") }}
            </CardTitle>
          </CardHeader>
          <CardContent class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1.5">
              <FormInput
                v-model="knowns.volume"
                type="number"
                step="any"
                :label="__('Volume (V)')"
                :error="analysis.fieldErrors['knowns.volume']"
              />
              <p v-if="analysis.fieldErrors['knowns.volume']" class="cube-field-error">
                {{ analysis.fieldErrors["knowns.volume"] }}
              </p>
            </div>

            <div class="space-y-1.5">
              <FormInput
                v-model="knowns.surfaceArea"
                type="number"
                step="any"
                :label="__('Total surface (S)')"
                :error="analysis.fieldErrors['knowns.surfaceArea']"
              />
              <p
                v-if="analysis.fieldErrors['knowns.surfaceArea']"
                class="cube-field-error"
              >
                {{ analysis.fieldErrors["knowns.surfaceArea"] }}
              </p>
            </div>

            <div class="space-y-1.5">
              <FormInput
                v-model="knowns.spaceDiagonal"
                type="number"
                step="any"
                :label="__('Space diagonal (d)')"
                :error="analysis.fieldErrors['knowns.spaceDiagonal']"
              />
              <p
                v-if="analysis.fieldErrors['knowns.spaceDiagonal']"
                class="cube-field-error"
              >
                {{ analysis.fieldErrors["knowns.spaceDiagonal"] }}
              </p>
            </div>

            <div class="space-y-1.5">
              <FormInput
                v-model="knowns.radiusOfCircumscribedSphere"
                type="number"
                step="any"
                :label="__('Circumscribed sphere radius (R)')"
                :error="analysis.fieldErrors['knowns.radiusOfCircumscribedSphere']"
              />
              <p
                v-if="analysis.fieldErrors['knowns.radiusOfCircumscribedSphere']"
                class="cube-field-error"
              >
                {{ analysis.fieldErrors["knowns.radiusOfCircumscribedSphere"] }}
              </p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle class="cube-card-title">
              {{ __("Face geometry") }}
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-5">
            <div>
              <p class="cube-section-label">{{ __("Face areas") }}</p>
              <div class="mt-3 grid gap-4 md:grid-cols-3">
                <div class="space-y-1.5">
                  <FormInput
                    v-model="knowns.faceAreas.lw"
                    type="number"
                    step="any"
                    :label="__('lw face area')"
                    :error="analysis.fieldErrors['knowns.faceAreas.lw']"
                  />
                  <p
                    v-if="analysis.fieldErrors['knowns.faceAreas.lw']"
                    class="cube-field-error"
                  >
                    {{ analysis.fieldErrors["knowns.faceAreas.lw"] }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="knowns.faceAreas.lh"
                    type="number"
                    step="any"
                    :label="__('lh face area')"
                    :error="analysis.fieldErrors['knowns.faceAreas.lh']"
                  />
                  <p
                    v-if="analysis.fieldErrors['knowns.faceAreas.lh']"
                    class="cube-field-error"
                  >
                    {{ analysis.fieldErrors["knowns.faceAreas.lh"] }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="knowns.faceAreas.wh"
                    type="number"
                    step="any"
                    :label="__('wh face area')"
                    :error="analysis.fieldErrors['knowns.faceAreas.wh']"
                  />
                  <p
                    v-if="analysis.fieldErrors['knowns.faceAreas.wh']"
                    class="cube-field-error"
                  >
                    {{ analysis.fieldErrors["knowns.faceAreas.wh"] }}
                  </p>
                </div>
              </div>
            </div>

            <div>
              <p class="cube-section-label">{{ __("Face diagonals") }}</p>
              <div class="mt-3 grid gap-4 md:grid-cols-3">
                <div class="space-y-1.5">
                  <FormInput
                    v-model="knowns.faceDiagonals.lw"
                    type="number"
                    step="any"
                    :label="__('lw face diagonal')"
                    :error="analysis.fieldErrors['knowns.faceDiagonals.lw']"
                  />
                  <p
                    v-if="analysis.fieldErrors['knowns.faceDiagonals.lw']"
                    class="cube-field-error"
                  >
                    {{ analysis.fieldErrors["knowns.faceDiagonals.lw"] }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="knowns.faceDiagonals.lh"
                    type="number"
                    step="any"
                    :label="__('lh face diagonal')"
                    :error="analysis.fieldErrors['knowns.faceDiagonals.lh']"
                  />
                  <p
                    v-if="analysis.fieldErrors['knowns.faceDiagonals.lh']"
                    class="cube-field-error"
                  >
                    {{ analysis.fieldErrors["knowns.faceDiagonals.lh"] }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="knowns.faceDiagonals.wh"
                    type="number"
                    step="any"
                    :label="__('wh face diagonal')"
                    :error="analysis.fieldErrors['knowns.faceDiagonals.wh']"
                  />
                  <p
                    v-if="analysis.fieldErrors['knowns.faceDiagonals.wh']"
                    class="cube-field-error"
                  >
                    {{ analysis.fieldErrors["knowns.faceDiagonals.wh"] }}
                  </p>
                </div>
              </div>
            </div>

            <div>
              <p class="cube-section-label">{{ __("Face perimeters") }}</p>
              <div class="mt-3 grid gap-4 md:grid-cols-3">
                <div class="space-y-1.5">
                  <FormInput
                    v-model="knowns.facePerimeters.lw"
                    type="number"
                    step="any"
                    :label="__('lw face perimeter')"
                    :error="analysis.fieldErrors['knowns.facePerimeters.lw']"
                  />
                  <p
                    v-if="analysis.fieldErrors['knowns.facePerimeters.lw']"
                    class="cube-field-error"
                  >
                    {{ analysis.fieldErrors["knowns.facePerimeters.lw"] }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="knowns.facePerimeters.lh"
                    type="number"
                    step="any"
                    :label="__('lh face perimeter')"
                    :error="analysis.fieldErrors['knowns.facePerimeters.lh']"
                  />
                  <p
                    v-if="analysis.fieldErrors['knowns.facePerimeters.lh']"
                    class="cube-field-error"
                  >
                    {{ analysis.fieldErrors["knowns.facePerimeters.lh"] }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="knowns.facePerimeters.wh"
                    type="number"
                    step="any"
                    :label="__('wh face perimeter')"
                    :error="analysis.fieldErrors['knowns.facePerimeters.wh']"
                  />
                  <p
                    v-if="analysis.fieldErrors['knowns.facePerimeters.wh']"
                    class="cube-field-error"
                  >
                    {{ analysis.fieldErrors["knowns.facePerimeters.wh"] }}
                  </p>
                </div>
              </div>
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
                  <div class="space-y-3">
                    <div
                      v-for="step in analysis.steps"
                      :key="`${step.title}-${step.formula}`"
                      class="cube-step-card"
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
                      {{ __("Enter valid dimensions first.") }}
                    </p>
                  </div>
                </AccordionContent>
              </AccordionItem>
            </Accordion>
          </CardContent>
        </Card>
      </div>

      <aside class="space-y-6">
        <Card class="cube-result-shell">
          <CardHeader>
            <CardTitle class="cube-card-title flex items-center justify-between gap-3">
              <span>{{ __("Result") }}</span>
              <div class="cube-icon-chip">
                <Box :size="18" />
              </div>
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="cube-status-grid">
              <div class="cube-status-card">
                <p class="cube-status-label">{{ __("Solved dimensions") }}</p>
                <p class="cube-status-value">{{ resolvedCount }}/3</p>
              </div>
              <div class="cube-status-card">
                <p class="cube-status-label">{{ __("Live mode") }}</p>
                <p class="cube-status-value">
                  {{ analysis.hasAnyInput ? __("Active") : __("Ready") }}
                </p>
              </div>
            </div>

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
              class="grid gap-3 sm:grid-cols-2"
              :class="{ 'opacity-80': !resultHasData }"
            >
              <div class="cube-result-card" :class="{ 'is-muted': analysis.result.length === null }">
                <p class="cube-result-label">{{ __("Length (l)") }}</p>
                <p class="cube-result-value">{{ displayNumber(analysis.result.length) }}</p>
                <p class="cube-result-helper">{{ __("units") }}</p>
              </div>
              <div class="cube-result-card" :class="{ 'is-muted': analysis.result.width === null }">
                <p class="cube-result-label">{{ __("Width (w)") }}</p>
                <p class="cube-result-value">{{ displayNumber(analysis.result.width) }}</p>
                <p class="cube-result-helper">{{ __("units") }}</p>
              </div>
              <div class="cube-result-card" :class="{ 'is-muted': analysis.result.height === null }">
                <p class="cube-result-label">{{ __("Height (h)") }}</p>
                <p class="cube-result-value">{{ displayNumber(analysis.result.height) }}</p>
                <p class="cube-result-helper">{{ __("units") }}</p>
              </div>
              <div class="cube-result-card">
                <p class="cube-result-label">{{ __("Preview") }}</p>
                <p class="cube-result-value text-base">
                  {{ __("The 3D cuboid updates from the solved dimensions above.") }}
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle class="cube-card-title">
              {{ __("Core properties") }}
            </CardTitle>
          </CardHeader>
          <CardContent class="grid gap-3 sm:grid-cols-2">
            <div
              v-for="item in coreResultCards"
              :key="item.label"
              class="cube-result-card"
              :class="{ 'is-muted': item.value === null }"
            >
              <p class="cube-result-label">{{ __(item.label) }}</p>
              <p class="cube-result-value">{{ displayNumber(item.value) }}</p>
              <p v-if="item.helper" class="cube-result-helper">{{ __(item.helper) }}</p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle class="cube-card-title">
              {{ __("Face metrics") }}
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div>
              <p class="cube-section-label">{{ __("Face areas") }}</p>
              <div class="mt-3 grid gap-3">
                <div
                  v-for="item in faceAreaCards"
                  :key="item.label"
                  class="cube-result-card"
                  :class="{ 'is-muted': item.value === null }"
                >
                  <p class="cube-result-label">{{ __(item.label) }}</p>
                  <p class="cube-result-value">{{ displayNumber(item.value) }}</p>
                  <p class="cube-result-helper">{{ __(item.helper) }}</p>
                </div>
              </div>
            </div>

            <div>
              <p class="cube-section-label">{{ __("Face diagonals") }}</p>
              <div class="mt-3 grid gap-3">
                <div
                  v-for="item in faceDiagonalCards"
                  :key="item.label"
                  class="cube-result-card"
                  :class="{ 'is-muted': item.value === null }"
                >
                  <p class="cube-result-label">{{ __(item.label) }}</p>
                  <p class="cube-result-value">{{ displayNumber(item.value) }}</p>
                  <p class="cube-result-helper">{{ __(item.helper) }}</p>
                </div>
              </div>
            </div>

            <div>
              <p class="cube-section-label">{{ __("Face perimeters") }}</p>
              <div class="mt-3 grid gap-3">
                <div
                  v-for="item in facePerimeterCards"
                  :key="item.label"
                  class="cube-result-card"
                  :class="{ 'is-muted': item.value === null }"
                >
                  <p class="cube-result-label">{{ __(item.label) }}</p>
                  <p class="cube-result-value">{{ displayNumber(item.value) }}</p>
                  <p class="cube-result-helper">{{ __(item.helper) }}</p>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.cube-calculator {
  --cube-soft-shadow: 0 18px 56px color-mix(in srgb, var(--ring) 8%, transparent);
}

.cube-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

.cube-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  border-radius: 999px;
  border: 1px solid color-mix(in srgb, var(--chart-4) 26%, transparent);
  background: color-mix(in srgb, var(--chart-4) 10%, transparent);
  color: var(--chart-4);
  padding: 0.45rem 0.85rem;
  font-size: 0.75rem;
  font-weight: 700;
}

.cube-card-title {
  color: var(--foreground);
  font-size: 0.95rem;
  font-weight: 800;
  letter-spacing: 0;
}

.cube-section-label,
.cube-result-label,
.cube-status-label {
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--muted-foreground);
}

.cube-field-error {
  color: var(--color-destructive);
  font-size: 0.75rem;
  font-weight: 600;
}

.cube-icon-chip {
  display: inline-flex;
  height: 2.5rem;
  width: 2.5rem;
  align-items: center;
  justify-content: center;
  border-radius: 0.875rem;
  border: 1px solid color-mix(in srgb, var(--chart-4) 28%, var(--border));
  background: color-mix(in srgb, var(--chart-4) 10%, transparent);
  color: var(--chart-4);
}

.cube-status-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.cube-status-card,
.cube-result-card,
.cube-step-card {
  border-radius: 0.875rem;
  border: 1px solid color-mix(in srgb, var(--border) 82%, transparent);
  background: color-mix(in srgb, var(--muted) 42%, transparent);
  box-shadow: var(--cube-soft-shadow);
}

.cube-status-card {
  padding: 1rem;
}

.cube-status-value,
.cube-result-value {
  margin-top: 0.45rem;
  color: var(--foreground);
  font-weight: 800;
}

.cube-status-value {
  font-size: 1.15rem;
}

.cube-result-card,
.cube-step-card {
  padding: 1rem;
}

.cube-result-value {
  font-size: 1.35rem;
  line-height: 1.2;
}

.cube-result-helper {
  margin-top: 0.35rem;
  font-size: 0.75rem;
  color: var(--muted-foreground);
}

.cube-result-card.is-muted {
  opacity: 0.5;
}

.cube-result-shell {
  background:
    radial-gradient(
      circle at top right,
      color-mix(in srgb, var(--chart-4) 10%, transparent),
      transparent 16rem
    ),
    color-mix(in srgb, var(--card) 90%, transparent);
}

.cube-calculator :deep([data-slot="card"]) {
  overflow: hidden;
  border-color: color-mix(in srgb, var(--border) 82%, transparent);
  background:
    radial-gradient(
      circle at top right,
      color-mix(in srgb, var(--chart-4) 8%, transparent),
      transparent 14rem
    ),
    color-mix(in srgb, var(--card) 88%, transparent);
  box-shadow: var(--cube-soft-shadow);
}

.cube-calculator :deep([data-slot="card-header"]) {
  border-bottom: 1px solid color-mix(in srgb, var(--border) 74%, transparent);
  background: color-mix(in srgb, var(--muted) 35%, transparent);
  padding-bottom: 1rem;
}

.cube-calculator :deep(label) {
  color: var(--muted-foreground);
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.04em;
}

.cube-calculator :deep([data-slot="input-group"]) {
  min-height: 3rem;
  border-color: color-mix(in srgb, var(--border) 84%, transparent);
  background: color-mix(in srgb, var(--background) 70%, transparent);
}

.cube-calculator :deep([data-slot="input-group"]:focus-within) {
  border-color: color-mix(in srgb, var(--chart-4) 65%, var(--border));
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--chart-4) 14%, transparent);
}

.cube-calculator :deep(input) {
  font-family: var(--font-mono);
  color: var(--foreground);
}

.cube-calculator :deep([data-slot="button"]) {
  min-height: 2.75rem;
  border-radius: 0.75rem;
  font-weight: 800;
}

.cube-calculator :deep([data-slot="button"][data-variant="outline"]) {
  border-color: color-mix(in srgb, var(--border) 88%, transparent);
  background: color-mix(in srgb, var(--muted) 45%, transparent);
  color: var(--foreground);
}

@media (max-width: 48rem) {
  .cube-status-grid {
    grid-template-columns: 1fr;
  }
}
</style>
