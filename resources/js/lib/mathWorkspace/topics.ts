import { solveCircle } from "@/lib/geometry/circle";
import {
  buildCubeCalculationSteps,
  CUBE_FIELD_DEFINITIONS,
  solveCube,
} from "@/lib/geometry/cube";
import { solveParallelogram } from "@/lib/geometry/parallelogram";
import { solveRectangle } from "@/lib/geometry/rectangle";
import { solveTrapezoid } from "@/lib/geometry/trapezoid";
import { solveTriangle } from "@/lib/geometry/triangle";
import { fromBaseUnit, toBaseUnit, unitLabel } from "@/lib/geometry/units";
import type {
  CircleMeasurements,
  ParallelogramMeasurements,
  RectangleMeasurements,
  SolvedCircle,
  SolvedParallelogram,
  SolvedRectangle,
  SolvedTrapezoid,
  SolvedTriangle,
  TrapezoidMeasurements,
  TriangleMeasurements,
} from "@/types/twoDGeometry";
import type {
  MathWorkspaceState,
  WorkspaceCalculationStep,
  WorkspaceConflict,
  WorkspaceDimension,
  WorkspaceFieldDefinition,
  WorkspaceFormula,
  WorkspaceSolveInput,
  WorkspaceTopicDefinition,
  WorkspaceTopicKey,
  WorkspaceValue,
} from "@/types/mathWorkspace";

const EMPTY_RESULT_WARNING = "Enter enough known values to solve this topic.";
const IMPOSSIBLE_RESULT_WARNING =
  "The entered values do not describe a valid shape.";
const OUT_OF_RANGE_WARNING =
  "The calculated shape exceeds the supported range.";

const RECTANGLE_FIELDS: WorkspaceFieldDefinition[] = [
  field("length", "Length", "l", 1),
  field("width", "Width", "w", 1),
  field("area", "Area", "A", 2),
  field("perimeter", "Perimeter", "P", 1),
  field("diagonal", "Diagonal", "d", 1),
];

const CIRCLE_FIELDS: WorkspaceFieldDefinition[] = [
  field("radius", "Radius", "r", 1),
  field("diameter", "Diameter", "d", 1),
  field("circumference", "Circumference", "C", 1),
  field("area", "Area", "A", 2),
];

const TRIANGLE_FIELDS: WorkspaceFieldDefinition[] = [
  field("sideA", "Side a", "a", 1),
  field("sideB", "Side b", "b", 1),
  field("sideC", "Side c", "c", 1),
  field("angleA", "Angle A", "A", 0),
  field("angleB", "Angle B", "B", 0),
  field("angleC", "Angle C", "C", 0),
  field("base", "Base", "b", 1),
  field("height", "Height", "h", 1),
];

const TRAPEZOID_FIELDS: WorkspaceFieldDefinition[] = [
  field("baseA", "Base a", "a", 1),
  field("baseB", "Base b", "b", 1),
  field("height", "Height", "h", 1),
  field("sideC", "Side c", "c", 1),
  field("sideD", "Side d", "d", 1),
];

const PARALLELOGRAM_FIELDS: WorkspaceFieldDefinition[] = [
  field("base", "Base", "b", 1),
  field("side", "Side", "a", 1),
  field("height", "Height", "h", 1),
  field("area", "Area", "A", 2),
  field("perimeter", "Perimeter", "P", 1),
  field("angle", "Angle", "θ", 0),
  field("diagonalA", "Diagonal 1", "d₁", 1),
  field("diagonalB", "Diagonal 2", "d₂", 1),
];

const CUBE_FIELDS: WorkspaceFieldDefinition[] = CUBE_FIELD_DEFINITIONS.map(
  (definition) =>
    field(
      definition.key,
      definition.label,
      definition.symbol,
      definition.dimension,
    ),
);

const RECTANGLE_FORMULAS: WorkspaceFormula[] = [
  formula("rectangle-area", "Area", "A = l × w", "area"),
  formula(
    "rectangle-perimeter",
    "Perimeter",
    "P = 2(l + w)",
    "perimeter",
  ),
  formula(
    "rectangle-diagonal",
    "Diagonal",
    "d = √(l² + w²)",
    "diagonal",
  ),
];

const CIRCLE_FORMULAS: WorkspaceFormula[] = [
  formula("circle-diameter", "Diameter", "d = 2r", "diameter"),
  formula(
    "circle-circumference",
    "Circumference",
    "C = 2πr",
    "circumference",
  ),
  formula("circle-area", "Area", "A = πr²", "area"),
];

const TRIANGLE_FORMULAS: WorkspaceFormula[] = [
  formula("triangle-area", "Area", "A = ½bh", "area"),
  formula("triangle-perimeter", "Perimeter", "P = a + b + c", "perimeter"),
  formula("triangle-heron", "Heron's formula", "A = √(s(s-a)(s-b)(s-c))", "area"),
];

const TRAPEZOID_FORMULAS: WorkspaceFormula[] = [
  formula("trapezoid-area", "Area", "A = ½(a + b)h", "area"),
  formula(
    "trapezoid-perimeter",
    "Perimeter",
    "P = a + b + c + d",
    "perimeter",
  ),
  formula("trapezoid-midline", "Midline", "m = ½(a + b)", "midline"),
];

const PARALLELOGRAM_FORMULAS: WorkspaceFormula[] = [
  formula("parallelogram-area", "Area", "A = bh", "area"),
  formula(
    "parallelogram-perimeter",
    "Perimeter",
    "P = 2(a + b)",
    "perimeter",
  ),
  formula(
    "parallelogram-diagonals",
    "Diagonals",
    "d₁,₂ = √(a² + b² ± 2ab cos θ)",
    "diagonalA",
  ),
];

const CUBE_FORMULAS: WorkspaceFormula[] = CUBE_FIELD_DEFINITIONS.filter(
  ({ key }) => key !== "side",
).map((definition) =>
  formula(
    `cube-${definition.key}`,
    definition.label,
    definition.forwardFormula,
    definition.key,
  ),
);

export const WORKSPACE_TOPICS: WorkspaceTopicDefinition[] = [
  topic(
    "rectangle",
    "Rectangle",
    "Plane Geometry",
    "mathematics.twoD",
    2,
    RECTANGLE_FIELDS,
    RECTANGLE_FORMULAS,
  ),
  topic(
    "circle",
    "Circle",
    "Plane Geometry",
    "mathematics.twoD",
    1,
    CIRCLE_FIELDS,
    CIRCLE_FORMULAS,
  ),
  topic(
    "triangle",
    "Triangle",
    "Plane Geometry",
    "mathematics.twoD",
    3,
    TRIANGLE_FIELDS,
    TRIANGLE_FORMULAS,
  ),
  topic(
    "trapezoid",
    "Trapezoid",
    "Plane Geometry",
    "mathematics.twoD",
    4,
    TRAPEZOID_FIELDS,
    TRAPEZOID_FORMULAS,
  ),
  topic(
    "parallelogram",
    "Parallelogram",
    "Plane Geometry",
    "mathematics.twoD",
    3,
    PARALLELOGRAM_FIELDS,
    PARALLELOGRAM_FORMULAS,
  ),
  topic(
    "cube",
    "Cube",
    "Solid Geometry",
    "mathematics.threeD",
    1,
    CUBE_FIELDS,
    CUBE_FORMULAS,
  ),
];

export function workspaceTopic(
  key: WorkspaceTopicKey,
): WorkspaceTopicDefinition {
  return WORKSPACE_TOPICS.find((item) => item.key === key) ?? WORKSPACE_TOPICS[0];
}

export function solveWorkspace(input: WorkspaceSolveInput): MathWorkspaceState {
  const definition = workspaceTopic(input.topic);
  const invalidFields = definition.fields.filter(({ key }) => {
    const value = input.values[key];
    return (
      value !== null &&
      value !== undefined &&
      (!Number.isFinite(value) || value <= 0)
    );
  });
  const validDisplayValues = Object.fromEntries(
    definition.fields.map(({ key }) => [
      key,
      invalidFields.some((fieldValue) => fieldValue.key === key)
        ? null
        : input.values[key] ?? null,
    ]),
  ) as Record<string, number | null>;
  const baseValues = toBaseValues(
    definition.fields,
    validDisplayValues,
    input.unit,
  );
  const invalidWarnings = invalidFields.length
    ? ["Enter positive numbers for all known values."]
    : [];

  if (input.topic === "rectangle") {
    const result = solveRectangle(baseValues as RectangleMeasurements);
    return rectangleState(
      input,
      definition,
      validDisplayValues,
      result.solution,
      result.mismatches,
      [...invalidWarnings, ...errorWarnings(result.error)],
    );
  }

  if (input.topic === "circle") {
    const result = solveCircle(baseValues as CircleMeasurements);
    return circleState(
      input,
      definition,
      validDisplayValues,
      result.solution,
      result.mismatches,
      [...invalidWarnings, ...errorWarnings(result.error)],
    );
  }

  if (input.topic === "triangle") {
    const result = solveTriangle(baseValues as TriangleMeasurements);
    const candidate = result.candidates[0] ?? null;
    const candidateWarnings =
      result.candidates.length > 1
        ? ["These measurements produce multiple valid solutions."]
        : [];
    return triangleState(
      input,
      definition,
      validDisplayValues,
      candidate?.solution ?? null,
      candidate?.mismatches ?? [],
      [
        ...invalidWarnings,
        ...errorWarnings(result.error),
        ...candidateWarnings,
      ],
    );
  }

  if (input.topic === "trapezoid") {
    const result = solveTrapezoid(baseValues as TrapezoidMeasurements);
    const candidate = result.candidates[0] ?? null;
    const candidateWarnings =
      result.candidates.length > 1
        ? ["These measurements produce multiple valid solutions."]
        : [];
    return trapezoidState(
      input,
      definition,
      validDisplayValues,
      candidate?.solution ?? null,
      candidate?.mismatches ?? [],
      [
        ...invalidWarnings,
        ...errorWarnings(result.error),
        ...candidateWarnings,
      ],
    );
  }

  if (input.topic === "parallelogram") {
    const result = solveParallelogram(
      baseValues as ParallelogramMeasurements,
    );
    const candidate = result.candidates[0] ?? null;
    const candidateWarnings =
      result.candidates.length > 1
        ? ["These measurements produce multiple valid solutions."]
        : [];
    return parallelogramState(
      input,
      definition,
      validDisplayValues,
      candidate?.solution ?? null,
      candidate?.mismatches ?? [],
      [
        ...invalidWarnings,
        ...errorWarnings(result.error),
        ...candidateWarnings,
      ],
    );
  }

  const result = solveCube(baseValues);
  const cubeValues = Object.fromEntries(
    Object.entries(result.values).map(([key, value]) => [
      key,
      fromBaseValue(
        Number(value),
        definition.fields.find((item) => item.key === key)?.dimension ?? 1,
        input.unit,
      ),
    ]),
  );
  const steps = buildCubeCalculationSteps(result, (value, key) =>
    formatNumber(
      fromBaseValue(
        value,
        definition.fields.find((item) => item.key === key)?.dimension ?? 1,
        input.unit,
      ),
    ),
  ).map((step, index) => ({
    id: `cube-step-${index}`,
    title: step.label,
    formula: step.formula,
    substitution: step.substitution,
    result: `${formatNumber(
      fromBaseValue(step.result, step.dimension, input.unit),
    )} ${displayUnit(input.unit, step.dimension)}`,
    rule: index === 0 ? "Use the entered property to solve the side." : undefined,
  }));

  return stateFromSolution({
    input,
    definition,
    entered: validDisplayValues,
    solved: cubeValues,
    sourceKeys: result.sourceField ? [result.sourceField] : [],
    mismatchKeys: result.conflicts.map(({ field }) => field),
    warnings: [
      ...invalidWarnings,
      ...(result.side === null && !invalidWarnings.length
        ? [EMPTY_RESULT_WARNING]
        : []),
    ],
    steps,
  });
}

function rectangleState(
  input: WorkspaceSolveInput,
  definition: WorkspaceTopicDefinition,
  entered: Record<string, number | null>,
  solution: SolvedRectangle | null,
  mismatches: string[],
  warnings: string[],
): MathWorkspaceState {
  return stateFromSolution({
    input,
    definition,
    entered,
    solved: solution ? displaySolved(solution, definition.fields, input.unit) : {},
    sourceKeys: solution?.source ?? [],
    mismatchKeys: mismatches,
    warnings,
    steps: solution
      ? formulaSteps(definition, solution, input.unit, solution.source)
      : [],
  });
}

function circleState(
  input: WorkspaceSolveInput,
  definition: WorkspaceTopicDefinition,
  entered: Record<string, number | null>,
  solution: SolvedCircle | null,
  mismatches: string[],
  warnings: string[],
): MathWorkspaceState {
  return stateFromSolution({
    input,
    definition,
    entered,
    solved: solution ? displaySolved(solution, definition.fields, input.unit) : {},
    sourceKeys: solution ? [solution.source] : [],
    mismatchKeys: mismatches,
    warnings,
    steps: solution
      ? formulaSteps(definition, solution, input.unit, [solution.source])
      : [],
  });
}

function triangleState(
  input: WorkspaceSolveInput,
  definition: WorkspaceTopicDefinition,
  entered: Record<string, number | null>,
  solution: SolvedTriangle | null,
  mismatches: string[],
  warnings: string[],
): MathWorkspaceState {
  return stateFromSolution({
    input,
    definition,
    entered,
    solved: solution
      ? displaySolved(
          {
            sideA: solution.sideA,
            sideB: solution.sideB,
            sideC: solution.sideC,
            angleA: solution.angleA,
            angleB: solution.angleB,
            angleC: solution.angleC,
            base: solution.base,
            height: solution.height,
            area: solution.area,
            perimeter: solution.perimeter,
          },
          [
            ...definition.fields,
            field("area", "Area", "A", 2),
            field("perimeter", "Perimeter", "P", 1),
          ],
          input.unit,
        )
      : {},
    sourceKeys: solution?.source ?? [],
    mismatchKeys: mismatches,
    warnings,
    steps: solution
      ? formulaSteps(
          definition,
          solution,
          input.unit,
          solution.source,
          solution.method,
        )
      : [],
  });
}

function trapezoidState(
  input: WorkspaceSolveInput,
  definition: WorkspaceTopicDefinition,
  entered: Record<string, number | null>,
  solution: SolvedTrapezoid | null,
  mismatches: string[],
  warnings: string[],
): MathWorkspaceState {
  return stateFromSolution({
    input,
    definition,
    entered,
    solved: solution
      ? displaySolved(
          solution,
          [
            ...definition.fields,
            field("area", "Area", "A", 2),
            field("perimeter", "Perimeter", "P", 1),
            field("midline", "Midline", "m", 1),
          ],
          input.unit,
        )
      : {},
    sourceKeys: solution?.source ?? [],
    mismatchKeys: mismatches,
    warnings,
    steps: solution
      ? formulaSteps(definition, solution, input.unit, solution.source)
      : [],
  });
}

function parallelogramState(
  input: WorkspaceSolveInput,
  definition: WorkspaceTopicDefinition,
  entered: Record<string, number | null>,
  solution: SolvedParallelogram | null,
  mismatches: string[],
  warnings: string[],
): MathWorkspaceState {
  return stateFromSolution({
    input,
    definition,
    entered,
    solved: solution ? displaySolved(solution, definition.fields, input.unit) : {},
    sourceKeys: solution?.source ?? [],
    mismatchKeys: mismatches,
    warnings,
    steps: solution
      ? formulaSteps(definition, solution, input.unit, solution.source)
      : [],
  });
}

function stateFromSolution({
  input,
  definition,
  entered,
  solved,
  sourceKeys,
  mismatchKeys,
  warnings,
  steps,
}: {
  input: WorkspaceSolveInput;
  definition: WorkspaceTopicDefinition;
  entered: Record<string, number | null>;
  solved: Record<string, number>;
  sourceKeys: string[];
  mismatchKeys: string[];
  warnings: string[];
  steps: WorkspaceCalculationStep[];
}): MathWorkspaceState {
  const allFields = uniqueFields([
    ...definition.fields,
    ...Object.keys(solved).map((key) => inferredField(key)),
  ]);
  const enteredValues = allFields
    .filter(({ key }) => entered[key] !== null && Number.isFinite(entered[key]))
    .map((fieldValue) =>
      workspaceValue(
        fieldValue,
        Number(entered[fieldValue.key]),
        "entered",
        input.unit,
      ),
    );
  const calculatedValues = allFields
    .filter(
      ({ key }) =>
        solved[key] !== undefined &&
        !enteredValues.some((value) => value.key === key),
    )
    .map((fieldValue) =>
      workspaceValue(
        fieldValue,
        solved[fieldValue.key],
        "calculated",
        input.unit,
      ),
    );
  const conflicts = mismatchKeys.map((key) => {
    const fieldValue = allFields.find((item) => item.key === key) ??
      inferredField(key);
    return {
      field: key,
      label: fieldValue.label,
      enteredValue: Number(entered[key]),
      expectedValue: solved[key] ?? null,
      message: "This entered value does not match the solved shape.",
    };
  });
  const lengthValues = allFields
    .filter(
      ({ key, dimension }) =>
        dimension === 1 &&
        solved[key] !== undefined &&
        Number.isFinite(solved[key]),
    )
    .slice(0, 6);

  return {
    version: 1,
    selectedTopic: input.topic,
    solveFor: input.solveFor ?? null,
    userEnteredValues: enteredValues,
    calculatedValues,
    units: {
      length: input.unit,
      area: unitLabel(input.unit, 2),
      volume: unitLabel(input.unit, 3),
      angle: "°",
    },
    formulas: definition.formulas,
    calculationSteps: steps,
    warnings: warnings.filter(Boolean),
    conflicts,
    graphConfiguration: lengthValues.length
      ? {
          title: "Length comparison",
          description: "Solved one-dimensional measurements",
          labels: lengthValues.map(({ label }) => label),
          values: lengthValues.map(({ key }) => solved[key]),
          unit: input.unit,
        }
      : null,
    visualizationConfiguration: Object.keys(solved).length
      ? {
          kind: input.topic === "cube" ? "geometry-3d" : "geometry-2d",
          shape: input.topic,
          values: solved,
        }
      : null,
  };
}

function formulaSteps(
  definition: WorkspaceTopicDefinition,
  solution: Record<string, unknown>,
  unit: WorkspaceSolveInput["unit"],
  sourceKeys: readonly string[],
  method?: string,
): WorkspaceCalculationStep[] {
  const source = sourceKeys
    .map((key) => definition.fields.find((item) => item.key === key)?.label ?? key)
    .join(", ");
  const first: WorkspaceCalculationStep = {
    id: `${definition.key}-source`,
    title: "Resolve the shape",
    formula: method ? `${method} method` : "Use the known measurements",
    substitution: source || "Known measurements",
    result: definition.label,
    rule: "Only the deterministic topic solver calculates these values.",
  };
  const resultSteps = definition.formulas.flatMap((formulaValue, index) => {
    const raw = solution[formulaValue.resultKey];
    if (typeof raw !== "number" || !Number.isFinite(raw)) return [];
    const resultField =
      definition.fields.find((item) => item.key === formulaValue.resultKey) ??
      inferredField(formulaValue.resultKey);
    const displayValue = fromBaseValue(raw, resultField.dimension, unit);
    return [
      {
        id: `${definition.key}-formula-${index}`,
        title: formulaValue.label,
        formula: formulaValue.expression,
        substitution: formulaValue.expression,
        result: `${formatNumber(displayValue)} ${displayUnit(
          unit,
          resultField.dimension,
        )}`.trim(),
      },
    ];
  });

  return [first, ...resultSteps];
}

function displaySolved(
  solution: Record<string, unknown>,
  fields: WorkspaceFieldDefinition[],
  unit: WorkspaceSolveInput["unit"],
): Record<string, number> {
  return Object.fromEntries(
    fields.flatMap((fieldValue) => {
      const rawValue = solution[fieldValue.key];
      if (typeof rawValue !== "number" || !Number.isFinite(rawValue)) return [];
      return [
        [
          fieldValue.key,
          fromBaseValue(rawValue, fieldValue.dimension, unit),
        ],
      ];
    }),
  );
}

function toBaseValues(
  fields: WorkspaceFieldDefinition[],
  values: Record<string, number | null>,
  unit: WorkspaceSolveInput["unit"],
): Record<string, number | null> {
  return Object.fromEntries(
    fields.map((fieldValue) => {
      const value = values[fieldValue.key];
      return [
        fieldValue.key,
        value === null
          ? null
          : fieldValue.dimension === 0
            ? value
            : toBaseUnit(value, unit, fieldValue.dimension),
      ];
    }),
  );
}

function fromBaseValue(
  value: number,
  dimension: WorkspaceDimension,
  unit: WorkspaceSolveInput["unit"],
): number {
  return dimension === 0 ? value : fromBaseUnit(value, unit, dimension);
}

function workspaceValue(
  definition: WorkspaceFieldDefinition,
  value: number,
  source: WorkspaceValue["source"],
  unit: WorkspaceSolveInput["unit"],
): WorkspaceValue {
  return {
    key: definition.key,
    label: definition.label,
    symbol: definition.symbol,
    value,
    dimension: definition.dimension,
    source,
    unit: displayUnit(unit, definition.dimension),
  };
}

function displayUnit(
  unit: WorkspaceSolveInput["unit"],
  dimension: WorkspaceDimension,
): string {
  if (dimension === 0) return "°";
  return unitLabel(unit, dimension);
}

function errorWarnings(error: string | null): string[] {
  if (error === null) return [];
  if (
    error === "not-enough-information" ||
    error === "insufficient-independent-information"
  ) {
    return [EMPTY_RESULT_WARNING];
  }
  if (error === "solution-out-of-range") return [OUT_OF_RANGE_WARNING];
  if (error === "near-parallelogram-underdetermined") {
    return [
      "The nearly equal bases make this trapezoid underdetermined at the current precision.",
    ];
  }
  return [IMPOSSIBLE_RESULT_WARNING];
}

function field(
  key: string,
  label: string,
  symbol: string,
  dimension: WorkspaceDimension,
): WorkspaceFieldDefinition {
  return { key, label, symbol, dimension };
}

function inferredField(key: string): WorkspaceFieldDefinition {
  const definitions: Record<
    string,
    [string, string, WorkspaceDimension]
  > = {
    area: ["Area", "A", 2],
    perimeter: ["Perimeter", "P", 1],
    diagonal: ["Diagonal", "d", 1],
    midline: ["Midline", "m", 1],
  };
  const [label, symbol, dimension] = definitions[key] ?? [key, key, 1];
  return field(key, label, symbol, dimension);
}

function formula(
  key: string,
  label: string,
  expression: string,
  resultKey: string,
): WorkspaceFormula {
  return { key, label, expression, resultKey };
}

function topic(
  key: WorkspaceTopicKey,
  label: string,
  category: string,
  calculatorRoute: string,
  minimumKnownValues: number,
  fields: WorkspaceFieldDefinition[],
  formulas: WorkspaceFormula[],
): WorkspaceTopicDefinition {
  return {
    key,
    label,
    category,
    calculatorRoute,
    minimumKnownValues,
    fields,
    formulas,
  };
}

function uniqueFields(
  fields: WorkspaceFieldDefinition[],
): WorkspaceFieldDefinition[] {
  return fields.filter(
    (fieldValue, index) =>
      fields.findIndex((other) => other.key === fieldValue.key) === index,
  );
}

export function formatNumber(value: number, precision = 6): string {
  if (!Number.isFinite(value)) return "-";
  const absolute = Math.abs(value);
  if ((absolute > 0 && absolute < 1e-6) || absolute >= 1e12) {
    return value.toExponential(Math.min(precision, 6));
  }
  return new Intl.NumberFormat(undefined, {
    maximumFractionDigits: precision,
  }).format(value);
}
