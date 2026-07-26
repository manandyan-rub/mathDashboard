import type {
  CubeCalculationStep,
  CubeConflict,
  CubeEnteredValues,
  CubeFieldDefinition,
  CubeFieldKey,
  CubeSolution,
} from "@/types/geometryCalculator";

export const MAX_CUBE_SIDE = 1_000_000;
const RELATIVE_TOLERANCE = 1e-9;
const ABSOLUTE_TOLERANCE = 1e-12;

export const CUBE_FIELD_DEFINITIONS: CubeFieldDefinition[] = [
  {
    key: "side",
    label: "Side",
    symbol: "a",
    dimension: 1,
    maximum: MAX_CUBE_SIDE,
    inverseFormula: "a = side",
    forwardFormula: "a = side",
  },
  {
    key: "faceArea",
    label: "Face area",
    symbol: "Af",
    dimension: 2,
    maximum: MAX_CUBE_SIDE ** 2,
    inverseFormula: "a = √Af",
    forwardFormula: "Af = a²",
  },
  {
    key: "surfaceArea",
    label: "Total surface area",
    symbol: "S",
    dimension: 2,
    maximum: 6 * MAX_CUBE_SIDE ** 2,
    inverseFormula: "a = √(S / 6)",
    forwardFormula: "S = 6a²",
  },
  {
    key: "volume",
    label: "Volume",
    symbol: "V",
    dimension: 3,
    maximum: MAX_CUBE_SIDE ** 3,
    inverseFormula: "a = ∛V",
    forwardFormula: "V = a³",
  },
  {
    key: "faceDiagonal",
    label: "Face diagonal",
    symbol: "df",
    dimension: 1,
    maximum: Math.SQRT2 * MAX_CUBE_SIDE,
    inverseFormula: "a = df / √2",
    forwardFormula: "df = a√2",
  },
  {
    key: "spaceDiagonal",
    label: "Space diagonal",
    symbol: "d",
    dimension: 1,
    maximum: Math.sqrt(3) * MAX_CUBE_SIDE,
    inverseFormula: "a = d / √3",
    forwardFormula: "d = a√3",
  },
  {
    key: "facePerimeter",
    label: "Face perimeter",
    symbol: "Pf",
    dimension: 1,
    maximum: 4 * MAX_CUBE_SIDE,
    inverseFormula: "a = Pf / 4",
    forwardFormula: "Pf = 4a",
  },
  {
    key: "totalEdgeLength",
    label: "Total edge length",
    symbol: "E",
    dimension: 1,
    maximum: 12 * MAX_CUBE_SIDE,
    inverseFormula: "a = E / 12",
    forwardFormula: "E = 12a",
  },
  {
    key: "circumsphereRadius",
    label: "Circumsphere radius",
    symbol: "R",
    dimension: 1,
    maximum: (Math.sqrt(3) * MAX_CUBE_SIDE) / 2,
    inverseFormula: "a = 2R / √3",
    forwardFormula: "R = a√3 / 2",
  },
  {
    key: "insphereRadius",
    label: "Insphere radius",
    symbol: "r",
    dimension: 1,
    maximum: MAX_CUBE_SIDE / 2,
    inverseFormula: "a = 2r",
    forwardFormula: "r = a / 2",
  },
];

export const CUBE_FIELD_KEYS = CUBE_FIELD_DEFINITIONS.map(
  ({ key }) => key,
);

const DEFINITION_BY_KEY = Object.fromEntries(
  CUBE_FIELD_DEFINITIONS.map((definition) => [definition.key, definition]),
) as Record<CubeFieldKey, CubeFieldDefinition>;

type SideCandidate = {
  field: CubeFieldKey;
  enteredValue: number;
  side: number;
};

export function solveCube(enteredValues: CubeEnteredValues): CubeSolution {
  const enteredKeys = CUBE_FIELD_KEYS.filter(
    (key) => enteredValues[key] !== null && enteredValues[key] !== undefined,
  );
  const invalidFields: CubeFieldKey[] = [];
  const warnings: string[] = [];
  const candidates: SideCandidate[] = [];

  for (const key of enteredKeys) {
    const value = enteredValues[key];
    const maximum = DEFINITION_BY_KEY[key].maximum;

    if (
      typeof value !== "number" ||
      !Number.isFinite(value) ||
      value <= 0 ||
      value > maximum
    ) {
      invalidFields.push(key);
      warnings.push(`Invalid ${key} value.`);
      continue;
    }

    const side = sideFromValue(key, value);
    if (!Number.isFinite(side) || side <= 0 || side > MAX_CUBE_SIDE) {
      invalidFields.push(key);
      warnings.push(`The ${key} value produces an unsupported cube side.`);
      continue;
    }

    candidates.push({ field: key, enteredValue: value, side });
  }

  if (!candidates.length) {
    return emptySolution(warnings, invalidFields);
  }

  const groups = groupCandidates(candidates);
  const selection = selectCandidateGroup(groups);

  if (selection === null) {
    const conflicts = pairwiseConflicts(candidates);
    return {
      ...emptySolution(
        [...warnings, "Entered values imply different cube side lengths."],
        invalidFields,
      ),
      conflicts,
    };
  }

  const side = average(selection.map((candidate) => candidate.side));
  const sourceCandidate =
    selection.find((candidate) => candidate.field === "side") ?? selection[0];
  const values = valuesFromSide(side);
  const conflicts = enteredConflicts(candidates, values);
  const calculatedFields = CUBE_FIELD_KEYS.filter(
    (key) => !enteredKeys.includes(key),
  );

  return {
    side,
    sourceField: sourceCandidate.field,
    values,
    calculatedFields,
    formulas: Object.fromEntries(
      CUBE_FIELD_DEFINITIONS.map(({ key, forwardFormula }) => [
        key,
        forwardFormula,
      ]),
    ),
    conflicts,
    warnings,
    invalidFields,
    isValid: conflicts.length === 0 && invalidFields.length === 0,
  };
}

export function buildCubeCalculationSteps(
  solution: CubeSolution,
  formatValue: (value: number, field: CubeFieldKey) => string,
): CubeCalculationStep[] {
  if (solution.side === null || solution.sourceField === null) return [];

  const source = DEFINITION_BY_KEY[solution.sourceField];
  const sourceValue = solution.values[solution.sourceField];
  if (sourceValue === undefined) return [];

  const steps: CubeCalculationStep[] = [
    {
      field: "side",
      label: "Solve the side",
      formula: source.inverseFormula,
      substitution: inverseSubstitution(
        solution.sourceField,
        formatValue(sourceValue, solution.sourceField),
      ),
      result: solution.side,
      dimension: 1,
    },
  ];

  for (const definition of CUBE_FIELD_DEFINITIONS) {
    if (definition.key === "side") continue;
    const result = solution.values[definition.key];
    if (result === undefined) continue;

    steps.push({
      field: definition.key,
      label: definition.label,
      formula: definition.forwardFormula,
      substitution: forwardSubstitution(
        definition.key,
        formatValue(solution.side, "side"),
      ),
      result,
      dimension: definition.dimension,
    });
  }

  return steps;
}

export function cubeFieldDefinition(
  key: CubeFieldKey,
): CubeFieldDefinition {
  return DEFINITION_BY_KEY[key];
}

function valuesFromSide(side: number): Record<CubeFieldKey, number> {
  return {
    side,
    faceArea: side ** 2,
    surfaceArea: 6 * side ** 2,
    volume: side ** 3,
    faceDiagonal: side * Math.SQRT2,
    spaceDiagonal: side * Math.sqrt(3),
    facePerimeter: 4 * side,
    totalEdgeLength: 12 * side,
    circumsphereRadius: (side * Math.sqrt(3)) / 2,
    insphereRadius: side / 2,
  };
}

function sideFromValue(key: CubeFieldKey, value: number): number {
  switch (key) {
    case "side":
      return value;
    case "faceArea":
      return Math.sqrt(value);
    case "surfaceArea":
      return Math.sqrt(value / 6);
    case "volume":
      return Math.cbrt(value);
    case "faceDiagonal":
      return value / Math.SQRT2;
    case "spaceDiagonal":
      return value / Math.sqrt(3);
    case "facePerimeter":
      return value / 4;
    case "totalEdgeLength":
      return value / 12;
    case "circumsphereRadius":
      return (2 * value) / Math.sqrt(3);
    case "insphereRadius":
      return 2 * value;
  }
}

function groupCandidates(candidates: SideCandidate[]): SideCandidate[][] {
  const groups: SideCandidate[][] = [];

  for (const candidate of candidates) {
    const group = groups.find((items) =>
      approximatelyEqual(items[0].side, candidate.side),
    );
    if (group) group.push(candidate);
    else groups.push([candidate]);
  }

  return groups;
}

function selectCandidateGroup(
  groups: SideCandidate[][],
): SideCandidate[] | null {
  if (groups.length === 1) return groups[0];

  const sideGroup = groups.find((group) =>
    group.some((candidate) => candidate.field === "side"),
  );
  if (sideGroup) return sideGroup;

  const ranked = [...groups].sort((left, right) => right.length - left.length);
  return ranked.length > 1 && ranked[0].length > ranked[1].length
    ? ranked[0]
    : null;
}

function enteredConflicts(
  candidates: SideCandidate[],
  expectedValues: Record<CubeFieldKey, number>,
): CubeConflict[] {
  return candidates
    .filter(
      ({ field, enteredValue }) =>
        !approximatelyEqual(enteredValue, expectedValues[field]),
    )
    .map(({ field, enteredValue }) =>
      makeConflict(field, enteredValue, expectedValues[field]),
    );
}

function pairwiseConflicts(candidates: SideCandidate[]): CubeConflict[] {
  return candidates.flatMap((candidate) => {
    const comparison = candidates.find(
      (other) => !approximatelyEqual(other.side, candidate.side),
    );
    if (!comparison) return [];

    const expectedValue = valuesFromSide(comparison.side)[candidate.field];
    return [makeConflict(candidate.field, candidate.enteredValue, expectedValue)];
  });
}

function makeConflict(
  field: CubeFieldKey,
  enteredValue: number,
  expectedValue: number,
): CubeConflict {
  return {
    field,
    enteredValue,
    expectedValue,
    difference: enteredValue - expectedValue,
    message: `Entered ${field} does not match the same cube.`,
  };
}

function inverseSubstitution(key: CubeFieldKey, value: string): string {
  switch (key) {
    case "side":
      return `a = ${value}`;
    case "faceArea":
      return `a = √${value}`;
    case "surfaceArea":
      return `a = √(${value} / 6)`;
    case "volume":
      return `a = ∛${value}`;
    case "faceDiagonal":
      return `a = ${value} / √2`;
    case "spaceDiagonal":
      return `a = ${value} / √3`;
    case "facePerimeter":
      return `a = ${value} / 4`;
    case "totalEdgeLength":
      return `a = ${value} / 12`;
    case "circumsphereRadius":
      return `a = 2 × ${value} / √3`;
    case "insphereRadius":
      return `a = 2 × ${value}`;
  }
}

function forwardSubstitution(key: CubeFieldKey, side: string): string {
  switch (key) {
    case "side":
      return `a = ${side}`;
    case "faceArea":
      return `Af = ${side}²`;
    case "surfaceArea":
      return `S = 6 × ${side}²`;
    case "volume":
      return `V = ${side}³`;
    case "faceDiagonal":
      return `df = ${side} × √2`;
    case "spaceDiagonal":
      return `d = ${side} × √3`;
    case "facePerimeter":
      return `Pf = 4 × ${side}`;
    case "totalEdgeLength":
      return `E = 12 × ${side}`;
    case "circumsphereRadius":
      return `R = ${side} × √3 / 2`;
    case "insphereRadius":
      return `r = ${side} / 2`;
  }
}

function approximatelyEqual(left: number, right: number): boolean {
  return (
    Math.abs(left - right) <=
    Math.max(
      ABSOLUTE_TOLERANCE,
      RELATIVE_TOLERANCE * Math.max(Math.abs(left), Math.abs(right), 1),
    )
  );
}

function average(values: number[]): number {
  return values.reduce((sum, value) => sum + value, 0) / values.length;
}

function emptySolution(
  warnings: string[] = [],
  invalidFields: CubeFieldKey[] = [],
): CubeSolution {
  return {
    side: null,
    sourceField: null,
    values: {},
    calculatedFields: [],
    formulas: {},
    conflicts: [],
    warnings,
    invalidFields,
    isValid: false,
  };
}
