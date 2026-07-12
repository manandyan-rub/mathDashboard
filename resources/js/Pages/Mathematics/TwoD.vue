<script setup lang="ts">
import { computed, reactive, ref, watch, type Component } from "vue";
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
import { solveCircle } from "@/lib/geometry/circle";
import { solveParallelogram } from "@/lib/geometry/parallelogram";
import { solveRectangle } from "@/lib/geometry/rectangle";
import { solveTrapezoid } from "@/lib/geometry/trapezoid";
import { solveTriangle } from "@/lib/geometry/triangle";
import TwoDGeometryPreview from "./Partials/TwoDGeometryPreview.vue";
import type {
  NullableNumber,
  NumericLike,
  CircleMeasureKey,
  ParallelogramMeasureKey,
  SolvedCircle,
  SolvedParallelogram,
  RectangleMeasureKey,
  SolvedRectangle,
  SolvedTrapezoid,
  SolvedTriangle,
  TrapezoidMeasureKey,
  TriangleMeasureKey,
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
const MAX_RECTANGLE_AREA = MAX_DIMENSION ** 2;
const MAX_RECTANGLE_PERIMETER = 4 * MAX_DIMENSION;
const MAX_RECTANGLE_DIAGONAL = Math.SQRT2 * MAX_DIMENSION;
const MAX_CIRCLE_DIAMETER = 2 * MAX_DIMENSION;
const MAX_CIRCLE_CIRCUMFERENCE = 2 * Math.PI * MAX_DIMENSION;
const MAX_CIRCLE_AREA = Math.PI * MAX_DIMENSION ** 2;
const MAX_PARALLELOGRAM_AREA = MAX_DIMENSION ** 2;
const MAX_PARALLELOGRAM_PERIMETER = 4 * MAX_DIMENSION;
const MAX_PARALLELOGRAM_DIAGONAL = 2 * MAX_DIMENSION;

const shape = ref<TwoDShapeKey>("rectangle");

const shapeOptions: ShapeOption[] = [
  {
    value: "rectangle",
    label: "Rectangle",
    metric: "any 2 known",
    formula: "A = l * w",
    icon: Square,
    color: "var(--primary)",
  },
  {
    value: "triangle",
    label: "Triangle",
    metric: "SSS / SAS / ASA / SSA",
    formula: "A = (b * h) / 2",
    icon: Triangle,
    color: "var(--chart-2)",
  },
  {
    value: "circle",
    label: "Circle",
    metric: "any 1 known",
    formula: "A = pi * r^2",
    icon: Circle,
    color: "var(--chart-5)",
  },
  {
    value: "trapezoid",
    label: "Trapezoid",
    metric: "any 4 known",
    formula: "A = ((a + b) * h) / 2",
    icon: Box,
    color: "var(--chart-3)",
  },
  {
    value: "parallelogram",
    label: "Parallelogram",
    metric: "3 independent",
    formula: "A = b * h",
    icon: Ruler,
    color: "var(--destructive)",
  },
];

const inputs = reactive(createDefaultInputs());
const selectedTrapezoidIndex = ref(0);
const selectedParallelogramIndex = ref(0);
const selectedTriangleIndex = ref(0);
const rectangleAnalysisState = computed(() => rectangleAnalysis());
const circleAnalysisState = computed(() => circleAnalysis());
const triangleAnalysisState = computed(() =>
  triangleAnalysis(selectedTriangleIndex.value),
);
const trapezoidAnalysisState = computed(() =>
  trapezoidAnalysis(selectedTrapezoidIndex.value),
);
const parallelogramAnalysisState = computed(() =>
  parallelogramAnalysis(selectedParallelogramIndex.value),
);

const activeShape = computed(
  () =>
    shapeOptions.find((item) => item.value === shape.value) ?? shapeOptions[0],
);

const activeAnalysis = computed(() =>
  shape.value === "rectangle"
    ? rectangleAnalysisState.value
    : shape.value === "triangle"
      ? triangleAnalysisState.value
      : shape.value === "circle"
        ? circleAnalysisState.value
        : shape.value === "trapezoid"
          ? trapezoidAnalysisState.value
          : parallelogramAnalysisState.value,
);

const rectangleDimensions = computed(() => ({
  length: rectangleAnalysisState.value.solution?.length ?? null,
  width: rectangleAnalysisState.value.solution?.width ?? null,
}));
const circleDimensions = computed(() => ({
  radius: circleAnalysisState.value.solution?.radius ?? null,
  diameter: circleAnalysisState.value.solution?.diameter ?? null,
}));
const triangleGeometry = computed(() => triangleAnalysisState.value.geometry);
const triangleCandidates = computed(() => triangleAnalysisState.value.candidates);
const trapezoidGeometry = computed(() => trapezoidAnalysisState.value.geometry);
const trapezoidCandidates = computed(
  () => trapezoidAnalysisState.value.candidates,
);
const parallelogramGeometry = computed(
  () => parallelogramAnalysisState.value.geometry,
);
const parallelogramCandidates = computed(
  () => parallelogramAnalysisState.value.candidates,
);

watch(
  () => [
    inputs.triangle.sideA,
    inputs.triangle.sideB,
    inputs.triangle.sideC,
    inputs.triangle.angleA,
    inputs.triangle.angleB,
    inputs.triangle.angleC,
    inputs.triangle.base,
    inputs.triangle.height,
  ],
  () => {
    selectedTriangleIndex.value = 0;
  },
);

watch(
  () => [
    inputs.trapezoid.baseA,
    inputs.trapezoid.baseB,
    inputs.trapezoid.height,
    inputs.trapezoid.sideC,
    inputs.trapezoid.sideD,
  ],
  () => {
    selectedTrapezoidIndex.value = 0;
  },
);

watch(
  () => [
    inputs.parallelogram.base,
    inputs.parallelogram.side,
    inputs.parallelogram.height,
    inputs.parallelogram.area,
    inputs.parallelogram.perimeter,
    inputs.parallelogram.angle,
    inputs.parallelogram.diagonalA,
    inputs.parallelogram.diagonalB,
  ],
  () => {
    selectedParallelogramIndex.value = 0;
  },
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
      area: "",
      perimeter: "",
      diagonal: "",
    },
    triangle: {
      sideA: 5,
      sideB: 6,
      sideC: 7,
      angleA: "",
      angleB: "",
      angleC: "",
      base: "",
      height: "",
    },
    circle: {
      radius: 6,
      diameter: "",
      circumference: "",
      area: "",
    },
    trapezoid: {
      baseA: 8,
      baseB: 14,
      height: 4,
      sideC: 5,
      sideD: 5,
    },
    parallelogram: {
      base: 10,
      side: 6,
      height: "",
      area: "",
      perimeter: "",
      angle: 40,
      diagonalA: "",
      diagonalB: "",
    },
  };
}

function resetShape() {
  if (shape.value === "rectangle") {
    Object.assign(inputs.rectangle, {
      length: "",
      width: "",
      area: "",
      perimeter: "",
      diagonal: "",
    });
    return;
  }

  if (shape.value === "triangle") {
    Object.assign(inputs.triangle, {
      sideA: "",
      sideB: "",
      sideC: "",
      angleA: "",
      angleB: "",
      angleC: "",
      base: "",
      height: "",
    });
    return;
  }

  if (shape.value === "circle") {
    Object.assign(inputs.circle, {
      radius: "",
      diameter: "",
      circumference: "",
      area: "",
    });
    return;
  }

  if (shape.value === "trapezoid") {
    Object.assign(inputs.trapezoid, {
      baseA: "",
      baseB: "",
      height: "",
      sideC: "",
      sideD: "",
    });
    return;
  }

  Object.assign(inputs.parallelogram, {
    base: "",
    side: "",
    height: "",
    area: "",
    perimeter: "",
    angle: "",
    diagonalA: "",
    diagonalB: "",
  });
}

function selectShape(value: TwoDShapeKey) {
  shape.value = value;
}

function selectTrapezoidCandidate(index: number) {
  selectedTrapezoidIndex.value = index;
}

function selectParallelogramCandidate(index: number) {
  selectedParallelogramIndex.value = index;
}

function selectTriangleCandidate(index: number) {
  selectedTriangleIndex.value = index;
}

function numericState(value: NumericLike, maximum = MAX_DIMENSION) {
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

  if (Math.abs(parsed) > maximum) {
    return {
      value: null as NullableNumber,
      error: __("Use a value no greater than :maximum.", {
        maximum: displayNumber(maximum),
      }),
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

function angleState(value: NumericLike) {
  const state = numericState(value, 180);

  if (state.value !== null && state.value >= 180) {
    return {
      ...state,
      value: null as NullableNumber,
      error: __("Enter an angle greater than 0 and less than 180 degrees."),
    };
  }

  return state;
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
  const areaInput = numericState(inputs.rectangle.area, MAX_RECTANGLE_AREA);
  const perimeterInput = numericState(
    inputs.rectangle.perimeter,
    MAX_RECTANGLE_PERIMETER,
  );
  const diagonalInput = numericState(
    inputs.rectangle.diagonal,
    MAX_RECTANGLE_DIAGONAL,
  );
  const fieldMap = {
    "rectangle.length": length.error,
    "rectangle.width": width.error,
    "rectangle.area": areaInput.error,
    "rectangle.perimeter": perimeterInput.error,
    "rectangle.diagonal": diagonalInput.error,
  };
  const errors = collectErrors(fieldMap);
  const warnings: string[] = [];
  const solved = solveRectangle(
    {
      length: length.value,
      width: width.value,
      area: areaInput.value,
      perimeter: perimeterInput.value,
      diagonal: diagonalInput.value,
    },
    MAX_DIMENSION,
  );
  const solution = solved.solution;

  if (solved.error === "not-enough-information") {
    errors.push(__("Enter any two rectangle measurements."));
  } else if (solved.error === "impossible-measurements") {
    errors.push(__("Those measurements cannot form a rectangle."));
  } else if (solved.error === "solution-out-of-range") {
    errors.push(
      __("The solved length or width exceeds the supported maximum of :maximum.", {
        maximum: displayNumber(MAX_DIMENSION),
      }),
    );
  }

  if (solved.mismatches.length) {
    warnings.push(
      __("Some measurements do not match the solved rectangle: :fields.", {
        fields: solved.mismatches
          .map((key) => __(rectangleMeasureLabel(key)))
          .join(", "),
      }),
    );
  }

  if (solved.equivalentAssignments) {
    warnings.push(
      __(
        "Two positive length-width assignments are possible. They describe the same rectangle rotated, so the longer side is shown as length.",
      ),
    );
  }

  const sourceStep = rectangleSourceStep(solution);

  return {
    result: makeResult("Rectangle", "A = l * w", [
      { label: "Area", value: solution?.area ?? null, helper: "square units" },
      { label: "Perimeter", value: solution?.perimeter ?? null, helper: "units" },
      { label: "Diagonal", value: solution?.diagonal ?? null, helper: "units" },
      { label: "Length", value: solution?.length ?? null, helper: "units" },
      { label: "Width", value: solution?.width ?? null, helper: "units" },
    ]),
    solution,
    fieldErrors: fieldMap,
    errors,
    warnings,
    steps: [
      sourceStep,
      {
        title: __("Area"),
        formula: "A = l * w",
        detail: solution
          ? `${displayNumber(solution.length)} * ${displayNumber(solution.width)}`
          : "",
        result: solution?.area ?? null,
      },
      {
        title: __("Perimeter"),
        formula: "P = 2 * (l + w)",
        detail: solution
          ? `2 * (${displayNumber(solution.length)} + ${displayNumber(solution.width)})`
          : "",
        result: solution?.perimeter ?? null,
      },
      {
        title: __("Diagonal"),
        formula: "d = sqrt(l^2 + w^2)",
        detail: solution
          ? `sqrt(${displayNumber(solution.length)}^2 + ${displayNumber(solution.width)}^2)`
          : "",
        result: solution?.diagonal ?? null,
      },
    ],
  };
}

function rectangleMeasureLabel(key: RectangleMeasureKey): string {
  return {
    length: "Length",
    width: "Width",
    area: "Area",
    perimeter: "Perimeter",
    diagonal: "Diagonal",
  }[key];
}

function rectangleSourceStep(solution: SolvedRectangle | null) {
  if (!solution) {
    return {
      title: __("Solve dimensions"),
      formula: "",
      detail: "",
      result: null,
    };
  }

  const sourceKey = solution.source.join(":");
  const formulas: Record<string, string> = {
    "length:width": "l and w are known",
    "length:area": "w = A / l",
    "length:perimeter": "w = P / 2 - l",
    "length:diagonal": "w = sqrt(d^2 - l^2)",
    "width:area": "l = A / w",
    "width:perimeter": "l = P / 2 - w",
    "width:diagonal": "l = sqrt(d^2 - w^2)",
    "area:perimeter": "x^2 - (P / 2)x + A = 0",
    "area:diagonal": "x^2 - sqrt(d^2 + 2A)x + A = 0",
    "perimeter:diagonal": "A = ((P / 2)^2 - d^2) / 2",
  };

  return {
    title: __("Solve dimensions"),
    formula: formulas[sourceKey] ?? "",
    detail: `l = ${displayNumber(solution.length)}, w = ${displayNumber(solution.width)}`,
    result: null,
  };
}

function triangleAnalysis(selectedIndex = 0) {
  const sideA = numericState(inputs.triangle.sideA);
  const sideB = numericState(inputs.triangle.sideB);
  const sideC = numericState(inputs.triangle.sideC);
  const angleA = angleState(inputs.triangle.angleA);
  const angleB = angleState(inputs.triangle.angleB);
  const angleC = angleState(inputs.triangle.angleC);
  const base = numericState(inputs.triangle.base);
  const height = numericState(inputs.triangle.height);
  const fieldMap = {
    "triangle.sideA": sideA.error,
    "triangle.sideB": sideB.error,
    "triangle.sideC": sideC.error,
    "triangle.angleA": angleA.error,
    "triangle.angleB": angleB.error,
    "triangle.angleC": angleC.error,
    "triangle.base": base.error,
    "triangle.height": height.error,
  };
  const errors = collectErrors(fieldMap);
  const warnings: string[] = [];
  const solved = solveTriangle(
    {
      sideA: sideA.value,
      sideB: sideB.value,
      sideC: sideC.value,
      angleA: angleA.value,
      angleB: angleB.value,
      angleC: angleC.value,
      base: base.value,
      height: height.value,
    },
    MAX_DIMENSION,
  );
  const candidates = solved.candidates;
  const clampedIndex = Math.min(
    Math.max(selectedIndex, 0),
    Math.max(candidates.length - 1, 0),
  );
  const selectedCandidate = candidates[clampedIndex] ?? null;
  const solution = selectedCandidate?.solution ?? null;

  if (solved.error === "not-enough-information") {
    warnings.push(
      __(
        "A triangle needs three independent measurements. Use SSS, SAS, ASA, AAS, or SSA to solve the full shape.",
      ),
    );
  } else if (solved.error === "insufficient-independent-information") {
    warnings.push(
      __(
        "These measurements do not fix a unique triangle scale. Three angles alone determine shape but not size.",
      ),
    );
  } else if (solved.error === "impossible-measurements" && !errors.length) {
    errors.push(__("Those measurements cannot form a consistent triangle."));
  } else if (solved.error === "solution-out-of-range") {
    errors.push(
      __(
        "A solved triangle dimension exceeds the supported maximum of :maximum.",
        { maximum: displayNumber(MAX_DIMENSION) },
      ),
    );
  }

  if (candidates.length > 1) {
    warnings.push(
      __(
        "These measurements produce :count valid triangles. Select a solution to inspect it.",
        { count: String(candidates.length) },
      ),
    );
  }

  if (selectedCandidate?.mismatches.length) {
    warnings.push(
      __("Some measurements do not match the selected triangle: :fields.", {
        fields: selectedCandidate.mismatches
          .map((key) => __(triangleMeasureLabel(key)))
          .join(", "),
      }),
    );
  }

  const partialBase = base.value ?? sideC.value;
  const partialArea =
    partialBase !== null && height.value !== null
      ? (partialBase * height.value) / 2
      : null;
  const area = solution?.area ?? partialArea;
  const effectiveBase = solution?.base ?? partialBase;
  const effectiveHeight = solution?.height ?? height.value;
  const formula = solution
    ? "A = sqrt(s(s-a)(s-b)(s-c))"
    : partialArea !== null
      ? "A = (c * h) / 2"
      : "A = sqrt(s(s-a)(s-b)(s-c))";
  const sourceStep = triangleSourceStep(solution);

  return {
    result: makeResult("Triangle", formula, [
      { label: "Area", value: area, helper: "square units" },
      { label: "Perimeter", value: solution?.perimeter ?? null, helper: "units" },
      {
        label: "Semiperimeter",
        value: solution?.semiperimeter ?? null,
        helper: "units",
      },
      { label: "Side a", value: solution?.sideA ?? sideA.value, helper: "units" },
      { label: "Side b", value: solution?.sideB ?? sideB.value, helper: "units" },
      { label: "Side c", value: solution?.sideC ?? sideC.value, helper: "units" },
      { label: "Angle A", value: solution?.angleA ?? angleA.value, helper: "degrees" },
      { label: "Angle B", value: solution?.angleB ?? angleB.value, helper: "degrees" },
      { label: "Angle C", value: solution?.angleC ?? angleC.value, helper: "degrees" },
      { label: "Base", value: effectiveBase, helper: "units" },
      { label: "Height", value: effectiveHeight, helper: "units" },
    ]),
    candidates,
    geometry: {
      sideA: solution?.sideA ?? sideA.value,
      sideB: solution?.sideB ?? sideB.value,
      sideC: solution?.sideC ?? partialBase,
      angleA: solution?.angleA ?? angleA.value,
      angleB: solution?.angleB ?? angleB.value,
      angleC: solution?.angleC ?? angleC.value,
      height: effectiveHeight,
    },
    fieldErrors: fieldMap,
    errors,
    warnings,
    steps: [
      sourceStep,
      {
        title: __("Semiperimeter"),
        formula: "s = (a + b + c) / 2",
        detail: solution
          ? `(${displayNumber(solution.sideA)} + ${displayNumber(solution.sideB)} + ${displayNumber(solution.sideC)}) / 2`
          : "",
        result: solution?.semiperimeter ?? null,
      },
      {
        title: __("Area"),
        formula,
        detail: solution
          ? `sqrt(${displayNumber(solution.semiperimeter)}(${displayNumber(solution.semiperimeter)}-${displayNumber(solution.sideA)})(${displayNumber(solution.semiperimeter)}-${displayNumber(solution.sideB)})(${displayNumber(solution.semiperimeter)}-${displayNumber(solution.sideC)}))`
          : partialArea !== null && partialBase !== null && height.value !== null
            ? `(${displayNumber(partialBase)} * ${displayNumber(height.value)}) / 2`
            : "",
        result: area,
      },
      {
        title: __("Perimeter"),
        formula: "P = a + b + c",
        detail: solution
          ? `${displayNumber(solution.sideA)} + ${displayNumber(solution.sideB)} + ${displayNumber(solution.sideC)}`
          : "",
        result: solution?.perimeter ?? null,
      },
    ],
  };
}

function triangleMeasureLabel(key: TriangleMeasureKey): string {
  return {
    sideA: "Side a",
    sideB: "Side b",
    sideC: "Side c",
    angleA: "Angle A",
    angleB: "Angle B",
    angleC: "Angle C",
    base: "Base",
    height: "Height",
  }[key];
}

function triangleSourceStep(solution: SolvedTriangle | null) {
  if (!solution) {
    return {
      title: __("Solve triangle"),
      formula: "SSS / SAS / ASA / AAS / SSA",
      detail: "",
      result: null,
    };
  }

  return {
    title: __("Solve triangle"),
    formula: solution.method,
    detail: solution.source
      .map((key) => __(triangleMeasureLabel(key)))
      .join(" + "),
    result: null,
  };
}

function circleAnalysis() {
  const radius = numericState(inputs.circle.radius);
  const diameter = numericState(inputs.circle.diameter, MAX_CIRCLE_DIAMETER);
  const circumferenceInput = numericState(
    inputs.circle.circumference,
    MAX_CIRCLE_CIRCUMFERENCE,
  );
  const areaInput = numericState(inputs.circle.area, MAX_CIRCLE_AREA);
  const fieldMap = {
    "circle.radius": radius.error,
    "circle.diameter": diameter.error,
    "circle.circumference": circumferenceInput.error,
    "circle.area": areaInput.error,
  };
  const errors = collectErrors(fieldMap);
  const warnings: string[] = [];
  const solved = solveCircle(
    {
      radius: radius.value,
      diameter: diameter.value,
      circumference: circumferenceInput.value,
      area: areaInput.value,
    },
    MAX_DIMENSION,
  );
  const solution = solved.solution;

  if (solved.error === "not-enough-information") {
    errors.push(__("Enter any one circle measurement."));
  } else if (solved.error === "solution-out-of-range") {
    errors.push(
      __("The solved radius exceeds the supported maximum of :maximum.", {
        maximum: displayNumber(MAX_DIMENSION),
      }),
    );
  }

  if (solved.mismatches.length) {
    warnings.push(
      __("Some measurements do not match the solved circle: :fields.", {
        fields: solved.mismatches
          .map((key) => __(circleMeasureLabel(key)))
          .join(", "),
      }),
    );
  }

  const sourceStep = circleSourceStep(solution);

  return {
    result: makeResult("Circle", "A = pi * r^2", [
      { label: "Area", value: solution?.area ?? null, helper: "square units" },
      {
        label: "Circumference",
        value: solution?.circumference ?? null,
        helper: "units",
      },
      { label: "Radius", value: solution?.radius ?? null, helper: "units" },
      { label: "Diameter", value: solution?.diameter ?? null, helper: "units" },
    ]),
    solution,
    fieldErrors: fieldMap,
    errors,
    warnings,
    steps: [
      sourceStep,
      {
        title: __("Area"),
        formula: "A = pi * r^2",
        detail: solution ? `pi * ${displayNumber(solution.radius)}^2` : "",
        result: solution?.area ?? null,
      },
      {
        title: __("Circumference"),
        formula: "C = 2 * pi * r",
        detail: solution ? `2 * pi * ${displayNumber(solution.radius)}` : "",
        result: solution?.circumference ?? null,
      },
    ],
  };
}

function circleMeasureLabel(key: CircleMeasureKey): string {
  return {
    radius: "Radius",
    diameter: "Diameter",
    circumference: "Circumference",
    area: "Area",
  }[key];
}

function circleSourceStep(solution: SolvedCircle | null) {
  if (!solution) {
    return {
      title: __("Solve radius"),
      formula: "",
      detail: "",
      result: null,
    };
  }

  const formulas = {
    radius: "r is known",
    diameter: "r = d / 2",
    circumference: "r = C / (2 * pi)",
    area: "r = sqrt(A / pi)",
  };

  return {
    title: __("Solve radius"),
    formula: formulas[solution.source],
    detail: `r = ${displayNumber(solution.radius)}`,
    result: solution.radius,
  };
}

function trapezoidAnalysis(selectedIndex = 0) {
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
  const solved = solveTrapezoid(
    {
      baseA: baseA.value,
      baseB: baseB.value,
      height: height.value,
      sideC: sideC.value,
      sideD: sideD.value,
    },
    MAX_DIMENSION,
  );
  const candidates = solved.candidates;
  const clampedIndex = Math.min(
    Math.max(selectedIndex, 0),
    Math.max(candidates.length - 1, 0),
  );
  const selectedCandidate = candidates[clampedIndex] ?? null;
  const solution = selectedCandidate?.solution ?? null;

  if (
    height.value !== null &&
    sideC.value !== null &&
    sideC.value < height.value
  ) {
    errors.push(
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
    errors.push(
      __(
        "Side d is shorter than the height, so this cannot form a consistent trapezoid.",
      ),
    );
  }

  if (solved.error === "not-enough-information") {
    warnings.push(
      __(
        "Enter four independent trapezoid measurements to solve the full shape. Partial results are shown when possible.",
      ),
    );
  } else if (solved.error === "near-parallelogram-underdetermined") {
    warnings.push(
      __(
        "The bases are nearly equal, so deriving height from four sides would be numerically unstable. Add height or an angle to solve this near-parallelogram safely.",
      ),
    );
  } else if (
    solved.error === "impossible-measurements" &&
    !errors.length
  ) {
    errors.push(__("Those measurements cannot form a consistent trapezoid."));
  } else if (solved.error === "solution-out-of-range") {
    errors.push(
      __(
        "A solved trapezoid dimension exceeds the supported maximum of :maximum.",
        { maximum: displayNumber(MAX_DIMENSION) },
      ),
    );
  }

  if (candidates.length > 1) {
    warnings.push(
      __("These measurements produce :count valid trapezoids. Select a solution to inspect it.", {
        count: String(candidates.length),
      }),
    );
  }

  if (selectedCandidate?.mismatches.length) {
    warnings.push(
      __("Some measurements do not match the selected trapezoid: :fields.", {
        fields: selectedCandidate.mismatches
          .map((key) => __(trapezoidMeasureLabel(key)))
          .join(", "),
      }),
    );
  }

  const effectiveBaseA = solution?.baseA ?? baseA.value;
  const effectiveBaseB = solution?.baseB ?? baseB.value;
  const effectiveHeight = solution?.height ?? height.value;
  const effectiveSideC = solution?.sideC ?? sideC.value;
  const effectiveSideD = solution?.sideD ?? sideD.value;
  const area =
    effectiveBaseA !== null &&
    effectiveBaseB !== null &&
    effectiveHeight !== null
      ? ((effectiveBaseA + effectiveBaseB) * effectiveHeight) / 2
      : null;
  const perimeter =
    effectiveBaseA !== null &&
    effectiveBaseB !== null &&
    effectiveSideC !== null &&
    effectiveSideD !== null
      ? effectiveBaseA + effectiveBaseB + effectiveSideC + effectiveSideD
      : null;
  const midline =
    effectiveBaseA !== null && effectiveBaseB !== null
      ? (effectiveBaseA + effectiveBaseB) / 2
      : null;
  const sourceStep = trapezoidSourceStep(solution);

  return {
    result: makeResult("Trapezoid", "A = ((a + b) * h) / 2", [
      { label: "Area", value: area, helper: "square units" },
      { label: "Perimeter", value: perimeter, helper: "units" },
      { label: "Midline", value: midline, helper: "units" },
      { label: "Base A", value: effectiveBaseA, helper: "units" },
      { label: "Base B", value: effectiveBaseB, helper: "units" },
      { label: "Height", value: effectiveHeight, helper: "units" },
    ]),
    candidates,
    geometry: {
      baseA: effectiveBaseA,
      baseB: effectiveBaseB,
      height: effectiveHeight,
      sideC: effectiveSideC,
      sideD: effectiveSideD,
      offset: solution?.offset ?? null,
    },
    fieldErrors: fieldMap,
    errors,
    warnings,
    steps: [
      sourceStep,
      {
        title: __("Midline"),
        formula: "m = (a + b) / 2",
        detail:
          effectiveBaseA !== null && effectiveBaseB !== null
            ? `(${displayNumber(effectiveBaseA)} + ${displayNumber(effectiveBaseB)}) / 2`
            : "",
        result: midline,
      },
      {
        title: __("Area"),
        formula: "A = m * h",
        detail:
          midline !== null && effectiveHeight !== null
            ? `${displayNumber(midline)} * ${displayNumber(effectiveHeight)}`
            : "",
        result: area,
      },
      {
        title: __("Perimeter"),
        formula: "P = a + b + c + d",
        detail:
          effectiveBaseA !== null &&
          effectiveBaseB !== null &&
          effectiveSideC !== null &&
          effectiveSideD !== null
            ? `${displayNumber(effectiveBaseA)} + ${displayNumber(effectiveBaseB)} + ${displayNumber(effectiveSideC)} + ${displayNumber(effectiveSideD)}`
            : "",
        result: perimeter,
      },
    ],
  };
}

function trapezoidMeasureLabel(key: TrapezoidMeasureKey): string {
  return {
    baseA: "Base A",
    baseB: "Base B",
    height: "Height",
    sideC: "Side c",
    sideD: "Side d",
  }[key];
}

function trapezoidSourceStep(solution: SolvedTrapezoid | null) {
  if (!solution) {
    return {
      title: __("Solve trapezoid"),
      formula: "",
      detail: "",
      result: null,
    };
  }

  const formulas = {
    "baseA:baseB:height:sideC": "x = +/-sqrt(c^2 - h^2)",
    "baseA:baseB:height:sideD": "b - a - x = +/-sqrt(d^2 - h^2)",
    "baseA:baseB:sideC:sideD":
      "x = ((b - a)^2 + c^2 - d^2) / (2(b - a))",
    "baseB:height:sideC:sideD": "b - a = +/-sqrt(c^2-h^2) +/-sqrt(d^2-h^2)",
    "baseA:height:sideC:sideD": "b - a = +/-sqrt(c^2-h^2) +/-sqrt(d^2-h^2)",
  };

  return {
    title: __("Solve trapezoid"),
    formula: formulas[solution.source.join(":")] ?? "",
    detail: `a = ${displayNumber(solution.baseA)}, b = ${displayNumber(solution.baseB)}, h = ${displayNumber(solution.height)}, x = ${displayNumber(solution.offset)}`,
    result: null,
  };
}

function parallelogramAnalysis(selectedIndex = 0) {
  const base = numericState(inputs.parallelogram.base);
  const side = numericState(inputs.parallelogram.side);
  const height = numericState(inputs.parallelogram.height);
  const areaInput = numericState(
    inputs.parallelogram.area,
    MAX_PARALLELOGRAM_AREA,
  );
  const perimeterInput = numericState(
    inputs.parallelogram.perimeter,
    MAX_PARALLELOGRAM_PERIMETER,
  );
  const angleInput = angleState(inputs.parallelogram.angle);
  const diagonalAInput = numericState(
    inputs.parallelogram.diagonalA,
    MAX_PARALLELOGRAM_DIAGONAL,
  );
  const diagonalBInput = numericState(
    inputs.parallelogram.diagonalB,
    MAX_PARALLELOGRAM_DIAGONAL,
  );
  const fieldMap = {
    "parallelogram.base": base.error,
    "parallelogram.side": side.error,
    "parallelogram.height": height.error,
    "parallelogram.area": areaInput.error,
    "parallelogram.perimeter": perimeterInput.error,
    "parallelogram.angle": angleInput.error,
    "parallelogram.diagonalA": diagonalAInput.error,
    "parallelogram.diagonalB": diagonalBInput.error,
  };
  const errors = collectErrors(fieldMap);
  const warnings: string[] = [];
  const solved = solveParallelogram(
    {
      base: base.value,
      side: side.value,
      height: height.value,
      area: areaInput.value,
      perimeter: perimeterInput.value,
      angle: angleInput.value,
      diagonalA: diagonalAInput.value,
      diagonalB: diagonalBInput.value,
    },
    MAX_DIMENSION,
  );
  const candidates = solved.candidates;
  const clampedIndex = Math.min(
    Math.max(selectedIndex, 0),
    Math.max(candidates.length - 1, 0),
  );
  const selectedCandidate = candidates[clampedIndex] ?? null;
  const solution = selectedCandidate?.solution ?? null;

  if (
    side.value !== null &&
    height.value !== null &&
    height.value > side.value
  ) {
    errors.push(
      __(
        "Height is larger than the side length. That makes the side-height pair geometrically inconsistent for this parallelogram.",
      ),
    );
  }

  if (solved.error === "not-enough-information") {
    warnings.push(
      __(
        "A parallelogram needs three independent measurements. Enter another side, angle, height, area, perimeter, or diagonal.",
      ),
    );
  } else if (solved.error === "insufficient-independent-information") {
    warnings.push(
      __(
        "These values are not independent enough to determine the parallelogram. Add a measurement that fixes its angle or scale.",
      ),
    );
  } else if (solved.error === "impossible-measurements" && !errors.length) {
    errors.push(__("Those measurements cannot form a consistent parallelogram."));
  } else if (solved.error === "solution-out-of-range") {
    errors.push(
      __(
        "A solved parallelogram dimension exceeds the supported maximum of :maximum.",
        { maximum: displayNumber(MAX_DIMENSION) },
      ),
    );
  }

  if (candidates.length > 1) {
    warnings.push(
      __(
        "These measurements produce :count valid parallelograms. Select a solution to inspect it.",
        { count: String(candidates.length) },
      ),
    );
  }

  if (selectedCandidate?.mismatches.length) {
    warnings.push(
      __("Some measurements do not match the selected parallelogram: :fields.", {
        fields: selectedCandidate.mismatches
          .map((key) => __(parallelogramMeasureLabel(key)))
          .join(", "),
      }),
    );
  }

  const effectiveBase = solution?.base ?? base.value;
  const effectiveSide = solution?.side ?? side.value;
  const effectiveAngle = solution?.angle ?? angleInput.value;
  const derivedHeight =
    effectiveSide !== null && effectiveAngle !== null
      ? effectiveSide * Math.sin((effectiveAngle * Math.PI) / 180)
      : null;
  const effectiveHeight = solution?.height ?? height.value ?? derivedHeight;
  const area =
    solution?.area ??
    areaInput.value ??
    (effectiveBase !== null && effectiveHeight !== null
      ? effectiveBase * effectiveHeight
      : null);
  const perimeter =
    solution?.perimeter ??
    perimeterInput.value ??
    (effectiveBase !== null && effectiveSide !== null
      ? 2 * (effectiveBase + effectiveSide)
      : null);
  const diagonalA = solution?.diagonalA ?? diagonalAInput.value;
  const diagonalB = solution?.diagonalB ?? diagonalBInput.value;
  const sourceStep = parallelogramSourceStep(solution);

  return {
    result: makeResult("Parallelogram", "A = b * h", [
      { label: "Area", value: area, helper: "square units" },
      { label: "Perimeter", value: perimeter, helper: "units" },
      { label: "Base", value: effectiveBase, helper: "units" },
      { label: "Side", value: effectiveSide, helper: "units" },
      { label: "Height", value: effectiveHeight, helper: "units" },
      { label: "Angle", value: effectiveAngle, helper: "degrees" },
      { label: "Diagonal 1", value: diagonalA, helper: "units" },
      { label: "Diagonal 2", value: diagonalB, helper: "units" },
    ]),
    candidates,
    geometry: {
      base: effectiveBase,
      side: effectiveSide,
      height: effectiveHeight,
      angle: effectiveAngle,
    },
    fieldErrors: fieldMap,
    errors,
    warnings,
    steps: [
      sourceStep,
      {
        title: __("Area"),
        formula: "A = b * h",
        detail:
          effectiveBase !== null && effectiveHeight !== null
            ? `${displayNumber(effectiveBase)} * ${displayNumber(effectiveHeight)}`
            : "",
        result: area,
      },
      {
        title: __("Perimeter"),
        formula: "P = 2 * (b + s)",
        detail:
          effectiveBase !== null && effectiveSide !== null
            ? `2 * (${displayNumber(effectiveBase)} + ${displayNumber(effectiveSide)})`
            : "",
        result: perimeter,
      },
      {
        title: __("Diagonals"),
        formula: "d1,d2 = sqrt(b^2 + s^2 +/- 2bs cos(theta))",
        detail:
          diagonalA !== null && diagonalB !== null
            ? `d1 = ${displayNumber(diagonalA)}, d2 = ${displayNumber(diagonalB)}`
            : "",
        result: diagonalA,
      },
    ],
  };
}

function parallelogramMeasureLabel(key: ParallelogramMeasureKey): string {
  return {
    base: "Base",
    side: "Side",
    height: "Height",
    area: "Area",
    perimeter: "Perimeter",
    angle: "Angle",
    diagonalA: "Diagonal 1",
    diagonalB: "Diagonal 2",
  }[key];
}

function parallelogramSourceStep(solution: SolvedParallelogram | null) {
  if (!solution) {
    return {
      title: __("Solve parallelogram"),
      formula: "",
      detail: "",
      result: null,
    };
  }

  return {
    title: __("Solve parallelogram"),
    formula: solution.source
      .map((key) => __(parallelogramMeasureLabel(key)))
      .join(" + "),
    detail: `b = ${displayNumber(solution.base)}, s = ${displayNumber(solution.side)}, theta = ${displayNumber(solution.angle)} ${__("degrees")}`,
    result: null,
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
              :rectangle-dimensions="rectangleDimensions"
              :triangle-geometry="triangleGeometry"
              :circle-dimensions="circleDimensions"
              :trapezoid-geometry="trapezoidGeometry"
              :parallelogram-geometry="parallelogramGeometry"
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
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
              >
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.rectangle.length"
                    type="number"
                    step="any"
                    :max="MAX_DIMENSION"
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
                    :max="MAX_DIMENSION"
                    :label="__('Width')"
                    :error="fieldError('rectangle.width')"
                  />
                  <p v-if="fieldError('rectangle.width')" class="plane-field-error">
                    {{ fieldError("rectangle.width") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.rectangle.area"
                    type="number"
                    step="any"
                    :max="MAX_RECTANGLE_AREA"
                    :label="__('Area')"
                    :error="fieldError('rectangle.area')"
                  />
                  <p v-if="fieldError('rectangle.area')" class="plane-field-error">
                    {{ fieldError("rectangle.area") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.rectangle.perimeter"
                    type="number"
                    step="any"
                    :max="MAX_RECTANGLE_PERIMETER"
                    :label="__('Perimeter')"
                    :error="fieldError('rectangle.perimeter')"
                  />
                  <p
                    v-if="fieldError('rectangle.perimeter')"
                    class="plane-field-error"
                  >
                    {{ fieldError("rectangle.perimeter") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.rectangle.diagonal"
                    type="number"
                    step="any"
                    :max="MAX_RECTANGLE_DIAGONAL"
                    :label="__('Diagonal')"
                    :error="fieldError('rectangle.diagonal')"
                  />
                  <p
                    v-if="fieldError('rectangle.diagonal')"
                    class="plane-field-error"
                  >
                    {{ fieldError("rectangle.diagonal") }}
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
                    :max="MAX_DIMENSION"
                    :label="__('Side a')"
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
                    :max="MAX_DIMENSION"
                    :label="__('Side b')"
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
                    :max="MAX_DIMENSION"
                    :label="__('Side c')"
                    :error="fieldError('triangle.sideC')"
                  />
                  <p v-if="fieldError('triangle.sideC')" class="plane-field-error">
                    {{ fieldError("triangle.sideC") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.triangle.angleA"
                    type="number"
                    step="any"
                    :max="180"
                    :label="__('Angle A')"
                    :error="fieldError('triangle.angleA')"
                  />
                  <p v-if="fieldError('triangle.angleA')" class="plane-field-error">
                    {{ fieldError("triangle.angleA") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.triangle.angleB"
                    type="number"
                    step="any"
                    :max="180"
                    :label="__('Angle B')"
                    :error="fieldError('triangle.angleB')"
                  />
                  <p v-if="fieldError('triangle.angleB')" class="plane-field-error">
                    {{ fieldError("triangle.angleB") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.triangle.angleC"
                    type="number"
                    step="any"
                    :max="180"
                    :label="__('Angle C')"
                    :error="fieldError('triangle.angleC')"
                  />
                  <p v-if="fieldError('triangle.angleC')" class="plane-field-error">
                    {{ fieldError("triangle.angleC") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.triangle.base"
                    type="number"
                    step="any"
                    :max="MAX_DIMENSION"
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
                    :max="MAX_DIMENSION"
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
                    :max="MAX_DIMENSION"
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
                    :max="MAX_CIRCLE_DIAMETER"
                    :label="__('Diameter')"
                    :error="fieldError('circle.diameter')"
                  />
                  <p v-if="fieldError('circle.diameter')" class="plane-field-error">
                    {{ fieldError("circle.diameter") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.circle.circumference"
                    type="number"
                    step="any"
                    :max="MAX_CIRCLE_CIRCUMFERENCE"
                    :label="__('Circumference')"
                    :error="fieldError('circle.circumference')"
                  />
                  <p
                    v-if="fieldError('circle.circumference')"
                    class="plane-field-error"
                  >
                    {{ fieldError("circle.circumference") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.circle.area"
                    type="number"
                    step="any"
                    :max="MAX_CIRCLE_AREA"
                    :label="__('Area')"
                    :error="fieldError('circle.area')"
                  />
                  <p v-if="fieldError('circle.area')" class="plane-field-error">
                    {{ fieldError("circle.area") }}
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

              <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.parallelogram.base"
                    type="number"
                    step="any"
                    :max="MAX_DIMENSION"
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
                    :max="MAX_DIMENSION"
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
                    :max="MAX_DIMENSION"
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
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.parallelogram.area"
                    type="number"
                    step="any"
                    :max="MAX_PARALLELOGRAM_AREA"
                    :label="__('Area')"
                    :error="fieldError('parallelogram.area')"
                  />
                  <p v-if="fieldError('parallelogram.area')" class="plane-field-error">
                    {{ fieldError("parallelogram.area") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.parallelogram.perimeter"
                    type="number"
                    step="any"
                    :max="MAX_PARALLELOGRAM_PERIMETER"
                    :label="__('Perimeter')"
                    :error="fieldError('parallelogram.perimeter')"
                  />
                  <p
                    v-if="fieldError('parallelogram.perimeter')"
                    class="plane-field-error"
                  >
                    {{ fieldError("parallelogram.perimeter") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.parallelogram.angle"
                    type="number"
                    step="any"
                    :max="180"
                    :label="__('Angle')"
                    :error="fieldError('parallelogram.angle')"
                  />
                  <p v-if="fieldError('parallelogram.angle')" class="plane-field-error">
                    {{ fieldError("parallelogram.angle") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.parallelogram.diagonalA"
                    type="number"
                    step="any"
                    :max="MAX_PARALLELOGRAM_DIAGONAL"
                    :label="__('Diagonal 1')"
                    :error="fieldError('parallelogram.diagonalA')"
                  />
                  <p
                    v-if="fieldError('parallelogram.diagonalA')"
                    class="plane-field-error"
                  >
                    {{ fieldError("parallelogram.diagonalA") }}
                  </p>
                </div>
                <div class="space-y-1.5">
                  <FormInput
                    v-model="inputs.parallelogram.diagonalB"
                    type="number"
                    step="any"
                    :max="MAX_PARALLELOGRAM_DIAGONAL"
                    :label="__('Diagonal 2')"
                    :error="fieldError('parallelogram.diagonalB')"
                  />
                  <p
                    v-if="fieldError('parallelogram.diagonalB')"
                    class="plane-field-error"
                  >
                    {{ fieldError("parallelogram.diagonalB") }}
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
                v-if="shape === 'triangle' && triangleCandidates.length > 1"
                class="plane-solution-picker"
              >
                <div>
                  <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                    {{ __("Valid solutions") }}
                  </p>
                  <p class="mt-1 text-sm text-muted-foreground">
                    {{ __("Each option satisfies the known measurements.") }}
                  </p>
                </div>

                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                  <button
                    v-for="(candidate, index) in triangleCandidates"
                    :key="`${candidate.solution.sideA}-${candidate.solution.sideB}-${candidate.solution.sideC}`"
                    type="button"
                    class="plane-solution-button"
                    :class="{ 'is-active': selectedTriangleIndex === index }"
                    :aria-pressed="selectedTriangleIndex === index"
                    @click="selectTriangleCandidate(index)"
                  >
                    <span class="font-semibold">
                      {{ __("Solution :number", { number: String(index + 1) }) }}
                      <span class="text-xs text-muted-foreground">
                        ({{ candidate.solution.method }})
                      </span>
                    </span>
                    <span class="font-mono text-xs text-muted-foreground">
                      a={{ displayNumber(candidate.solution.sideA) }},
                      b={{ displayNumber(candidate.solution.sideB) }},
                      c={{ displayNumber(candidate.solution.sideC) }}
                    </span>
                  </button>
                </div>
              </div>

              <div
                v-if="shape === 'trapezoid' && trapezoidCandidates.length > 1"
                class="plane-solution-picker"
              >
                <div>
                  <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                    {{ __("Valid solutions") }}
                  </p>
                  <p class="mt-1 text-sm text-muted-foreground">
                    {{ __("Each option satisfies the known measurements.") }}
                  </p>
                </div>

                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                  <button
                    v-for="(candidate, index) in trapezoidCandidates"
                    :key="`${candidate.solution.baseA}-${candidate.solution.baseB}-${candidate.solution.offset}`"
                    type="button"
                    class="plane-solution-button"
                    :class="{ 'is-active': selectedTrapezoidIndex === index }"
                    :aria-pressed="selectedTrapezoidIndex === index"
                    @click="selectTrapezoidCandidate(index)"
                  >
                    <span class="font-semibold">
                      {{ __("Solution :number", { number: String(index + 1) }) }}
                    </span>
                    <span class="font-mono text-xs text-muted-foreground">
                      a={{ displayNumber(candidate.solution.baseA) }},
                      b={{ displayNumber(candidate.solution.baseB) }},
                      h={{ displayNumber(candidate.solution.height) }},
                      x={{ displayNumber(candidate.solution.offset) }}
                    </span>
                  </button>
                </div>
              </div>

              <div
                v-if="shape === 'parallelogram' && parallelogramCandidates.length > 1"
                class="plane-solution-picker"
              >
                <div>
                  <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                    {{ __("Valid solutions") }}
                  </p>
                  <p class="mt-1 text-sm text-muted-foreground">
                    {{ __("Each option satisfies the known measurements.") }}
                  </p>
                </div>

                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                  <button
                    v-for="(candidate, index) in parallelogramCandidates"
                    :key="`${candidate.solution.base}-${candidate.solution.side}-${candidate.solution.angle}`"
                    type="button"
                    class="plane-solution-button"
                    :class="{ 'is-active': selectedParallelogramIndex === index }"
                    :aria-pressed="selectedParallelogramIndex === index"
                    @click="selectParallelogramCandidate(index)"
                  >
                    <span class="font-semibold">
                      {{ __("Solution :number", { number: String(index + 1) }) }}
                    </span>
                    <span class="font-mono text-xs text-muted-foreground">
                      b={{ displayNumber(candidate.solution.base) }},
                      s={{ displayNumber(candidate.solution.side) }},
                      theta={{ displayNumber(candidate.solution.angle) }}
                      {{ __("degrees") }}
                    </span>
                  </button>
                </div>
              </div>

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
                      <p
                        v-if="step.result !== null"
                        class="mt-2 text-lg font-bold text-foreground"
                      >
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

.plane-solution-picker {
  border-block: 1px solid color-mix(in srgb, var(--border) 78%, transparent);
  padding-block: 1rem;
}

.plane-solution-button {
  display: flex;
  min-width: 0;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.35rem;
  border-radius: 0.75rem;
  border: 1px solid color-mix(in srgb, var(--border) 85%, transparent);
  background: color-mix(in srgb, var(--muted) 38%, transparent);
  padding: 0.75rem;
  color: var(--foreground);
  text-align: left;
  transition:
    border-color 0.18s ease,
    background-color 0.18s ease;
}

.plane-solution-button:hover,
.plane-solution-button.is-active {
  border-color: color-mix(in srgb, var(--primary) 55%, var(--border));
  background: color-mix(in srgb, var(--primary) 10%, var(--card));
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
