import type {
  CuboidCalculationStep,
  CuboidCandidate,
  CuboidConflict,
  CuboidDerivation,
  CuboidDimensionKey,
  CuboidDimensions,
  CuboidEnteredValues,
  CuboidFieldDefinition,
  CuboidFieldKey,
  CuboidPartialCandidate,
  CuboidSolution,
  PartialCuboidDimensions,
} from "@/types/geometryCalculator";

export const MAX_CUBOID_SIDE = 1_000_000;

const RELATIVE_TOLERANCE = 1e-9;
const ABSOLUTE_TOLERANCE = 1e-12;
const DIMENSION_KEYS: CuboidDimensionKey[] = [
  "length",
  "width",
  "height",
];

export const CUBOID_FIELD_DEFINITIONS: CuboidFieldDefinition[] = [
  definition("length", "Length", "l", 1, MAX_CUBOID_SIDE, "l = length", "dimensions"),
  definition("width", "Width", "w", 1, MAX_CUBOID_SIDE, "w = width", "dimensions"),
  definition("height", "Height", "h", 1, MAX_CUBOID_SIDE, "h = height", "dimensions"),
  definition("volume", "Volume", "V", 3, MAX_CUBOID_SIDE ** 3, "V = lwh", "core"),
  definition(
    "surfaceArea",
    "Total surface area",
    "S",
    2,
    6 * MAX_CUBOID_SIDE ** 2,
    "S = 2(lw + lh + wh)",
    "core",
  ),
  definition(
    "lateralSurface",
    "Lateral surface (lw base)",
    "Sl",
    2,
    4 * MAX_CUBOID_SIDE ** 2,
    "Sl = 2h(l + w)",
    "core",
    false,
  ),
  definition(
    "spaceDiagonal",
    "Space diagonal",
    "d",
    1,
    Math.sqrt(3) * MAX_CUBOID_SIDE,
    "d = √(l² + w² + h²)",
    "core",
  ),
  definition(
    "circumsphereRadius",
    "Circumscribed sphere radius",
    "R",
    1,
    (Math.sqrt(3) * MAX_CUBOID_SIDE) / 2,
    "R = d / 2",
    "core",
  ),
  definition(
    "totalEdgeLength",
    "Total edge length",
    "E",
    1,
    12 * MAX_CUBOID_SIDE,
    "E = 4(l + w + h)",
    "core",
  ),
  definition(
    "lengthWidthArea",
    "lw face area",
    "Alw",
    2,
    MAX_CUBOID_SIDE ** 2,
    "Alw = lw",
    "face-areas",
  ),
  definition(
    "lengthHeightArea",
    "lh face area",
    "Alh",
    2,
    MAX_CUBOID_SIDE ** 2,
    "Alh = lh",
    "face-areas",
  ),
  definition(
    "widthHeightArea",
    "wh face area",
    "Awh",
    2,
    MAX_CUBOID_SIDE ** 2,
    "Awh = wh",
    "face-areas",
  ),
  definition(
    "lengthWidthDiagonal",
    "lw face diagonal",
    "Dlw",
    1,
    Math.SQRT2 * MAX_CUBOID_SIDE,
    "Dlw = √(l² + w²)",
    "face-diagonals",
  ),
  definition(
    "lengthHeightDiagonal",
    "lh face diagonal",
    "Dlh",
    1,
    Math.SQRT2 * MAX_CUBOID_SIDE,
    "Dlh = √(l² + h²)",
    "face-diagonals",
  ),
  definition(
    "widthHeightDiagonal",
    "wh face diagonal",
    "Dwh",
    1,
    Math.SQRT2 * MAX_CUBOID_SIDE,
    "Dwh = √(w² + h²)",
    "face-diagonals",
  ),
  definition(
    "lengthWidthPerimeter",
    "lw face perimeter",
    "Plw",
    1,
    4 * MAX_CUBOID_SIDE,
    "Plw = 2(l + w)",
    "face-perimeters",
  ),
  definition(
    "lengthHeightPerimeter",
    "lh face perimeter",
    "Plh",
    1,
    4 * MAX_CUBOID_SIDE,
    "Plh = 2(l + h)",
    "face-perimeters",
  ),
  definition(
    "widthHeightPerimeter",
    "wh face perimeter",
    "Pwh",
    1,
    4 * MAX_CUBOID_SIDE,
    "Pwh = 2(w + h)",
    "face-perimeters",
  ),
];

export const CUBOID_FIELD_KEYS = CUBOID_FIELD_DEFINITIONS.map(
  ({ key }) => key,
);

export const CUBOID_EDITABLE_FIELD_DEFINITIONS =
  CUBOID_FIELD_DEFINITIONS.filter(({ editable }) => editable);

const DEFINITION_BY_KEY = Object.fromEntries(
  CUBOID_FIELD_DEFINITIONS.map((item) => [item.key, item]),
) as Record<CuboidFieldKey, CuboidFieldDefinition>;

type PartialState = {
  dimensions: PartialCuboidDimensions;
  derivations: CuboidDerivation[];
  assumptions: number;
};

type GenerationResult = {
  states: PartialState[];
  hadImpossibleDerivation: boolean;
};

type DerivationResult = {
  states: PartialState[];
  hadImpossibleDerivation: boolean;
};

export function solveCuboid(
  enteredValues: CuboidEnteredValues,
): CuboidSolution {
  const enteredKeys = CUBOID_FIELD_KEYS.filter(
    (key) => enteredValues[key] !== null && enteredValues[key] !== undefined,
  );
  const validValues: Partial<Record<CuboidFieldKey, number>> = {};
  const invalidFields: CuboidFieldKey[] = [];

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
      continue;
    }

    validValues[key] = value;
  }

  if (!Object.keys(validValues).length) {
    return emptySolution(
      invalidFields.length ? ["Invalid cuboid measurements were ignored."] : [],
      invalidFields,
    );
  }

  const generated = generateDimensionStates(validValues);
  const completed = generated.states.filter(isCompleteState);
  const candidates = rankFullCandidates(
    completed.map((state) => candidateFromState(state, validValues)),
  );

  if (candidates.length) {
    const referenceCandidate = candidates[0];
    const selectedCandidate =
      candidates.length === 1 ? referenceCandidate : null;
    const warnings = buildWarnings({
      invalidFields,
      conflicts: referenceCandidate.conflicts,
      ambiguous: candidates.length > 1,
      incomplete: false,
      impossible: false,
    });

    return {
      candidates,
      partialCandidates: [],
      selectedCandidate,
      values: selectedCandidate?.values ?? {},
      calculatedFields:
        selectedCandidate === null
          ? []
          : CUBOID_FIELD_KEYS.filter((key) => !enteredKeys.includes(key)),
      conflicts: referenceCandidate.conflicts,
      warnings,
      invalidFields,
      isValid:
        invalidFields.length === 0 &&
        referenceCandidate.conflicts.length === 0,
      isAmbiguous: candidates.length > 1,
    };
  }

  const partialCandidates = rankPartialCandidates(
    generated.states
      .filter((state) => resolvedDimensionCount(state.dimensions) > 0)
      .map((state) => partialCandidateFromState(state, validValues)),
  );
  const referencePartial = partialCandidates[0] ?? null;
  const selectedPartial =
    partialCandidates.length === 1 ? referencePartial : null;
  const values = selectedPartial?.values ?? {};
  const conflicts = referencePartial?.conflicts ?? [];
  const warnings = buildWarnings({
    invalidFields,
    conflicts,
    ambiguous: partialCandidates.length > 1,
    incomplete: true,
    impossible: generated.hadImpossibleDerivation,
  });

  return {
    candidates: [],
    partialCandidates,
    selectedCandidate: null,
    values,
    calculatedFields: CUBOID_FIELD_KEYS.filter(
      (key) => !enteredKeys.includes(key) && values[key] !== undefined,
    ),
    conflicts,
    warnings,
    invalidFields,
    isValid: false,
    isAmbiguous: partialCandidates.length > 1,
  };
}

export function cuboidValuesFromDimensions(
  dimensions: CuboidDimensions,
): Record<CuboidFieldKey, number> {
  const { length, width, height } = dimensions;
  const lengthWidthArea = length * width;
  const lengthHeightArea = length * height;
  const widthHeightArea = width * height;
  const spaceDiagonal = Math.hypot(length, width, height);

  return {
    length,
    width,
    height,
    volume: length * width * height,
    surfaceArea:
      2 * (lengthWidthArea + lengthHeightArea + widthHeightArea),
    lateralSurface: 2 * height * (length + width),
    spaceDiagonal,
    circumsphereRadius: spaceDiagonal / 2,
    lengthWidthArea,
    lengthHeightArea,
    widthHeightArea,
    lengthWidthDiagonal: Math.hypot(length, width),
    lengthHeightDiagonal: Math.hypot(length, height),
    widthHeightDiagonal: Math.hypot(width, height),
    lengthWidthPerimeter: 2 * (length + width),
    lengthHeightPerimeter: 2 * (length + height),
    widthHeightPerimeter: 2 * (width + height),
    totalEdgeLength: 4 * (length + width + height),
  };
}

export function cuboidFieldDefinition(
  key: CuboidFieldKey,
): CuboidFieldDefinition {
  return DEFINITION_BY_KEY[key];
}

export function buildCuboidCalculationSteps(
  source: CuboidCandidate | CuboidPartialCandidate | null,
  formatValue: (value: number, field: CuboidFieldKey) => string,
): CuboidCalculationStep[] {
  if (source === null) return [];

  const steps: CuboidCalculationStep[] = source.derivations.map((derivation) => ({
    field: derivation.target,
    label: derivation.label,
    formula: derivation.formula,
    substitution: derivation.sourceFields
      .flatMap((key) => {
        const value = derivation.sourceValues[key];
        return value === undefined
          ? []
          : [
              `${DEFINITION_BY_KEY[key].symbol} = ${formatValue(value, key)}`,
            ];
      })
      .join(", "),
    result: derivation.result,
    dimension: 1,
  }));

  if (!("length" in source)) return steps;

  for (const field of CUBOID_FIELD_DEFINITIONS) {
    if (DIMENSION_KEYS.includes(field.key as CuboidDimensionKey)) continue;

    steps.push({
      field: field.key,
      label: field.label,
      formula: field.forwardFormula,
      substitution: [
        `l = ${formatValue(source.length, "length")}`,
        `w = ${formatValue(source.width, "width")}`,
        `h = ${formatValue(source.height, "height")}`,
      ].join(", "),
      result: source.values[field.key],
      dimension: field.dimension,
    });
  }

  return steps;
}

function generateDimensionStates(
  values: Partial<Record<CuboidFieldKey, number>>,
): GenerationResult {
  let hadImpossibleDerivation = false;
  const seeds: PartialState[] = [
    {
      dimensions: {
        length: values.length ?? null,
        width: values.width ?? null,
        height: values.height ?? null,
      },
      derivations: [],
      assumptions: 0,
    },
  ];

  const addSeed = (
    dimensions: PartialCuboidDimensions,
    derivations: CuboidDerivation[],
  ) => {
    if (!validPartialDimensions(dimensions)) {
      hadImpossibleDerivation = true;
      return;
    }
    seeds.push({
      dimensions,
      derivations,
      assumptions: derivations.length,
    });
  };

  addVolumeFaceAreaSeeds(values, addSeed);
  addThreeFaceAreaSeed(values, addSeed);
  addThreeFacePerimeterSeed(values, addSeed);
  addThreeFaceDiagonalSeed(values, addSeed);
  addFacePairSeeds(values, addSeed);

  let states = deduplicateStates(seeds);

  for (let iteration = 0; iteration < 3; iteration += 1) {
    const expanded = [...states];

    for (const state of states) {
      if (isCompleteState(state)) continue;

      for (const target of DIMENSION_KEYS) {
        if (state.dimensions[target] !== null) continue;
        const result = deriveDimension(target, state, values);
        expanded.push(...result.states);
        hadImpossibleDerivation ||= result.hadImpossibleDerivation;
      }
    }

    const nextStates = deduplicateStates(expanded);
    if (nextStates.length === states.length) {
      states = nextStates;
      break;
    }
    states = nextStates;
  }

  return { states, hadImpossibleDerivation };
}

function deriveDimension(
  target: CuboidDimensionKey,
  state: PartialState,
  values: Partial<Record<CuboidFieldKey, number>>,
): DerivationResult {
  const { length, width, height } = state.dimensions;
  const states: PartialState[] = [];
  let hadImpossibleDerivation = false;

  const add = (
    id: string,
    formula: string,
    sourceFields: CuboidFieldKey[],
    result: number,
  ) => {
    if (!validDimension(result)) {
      hadImpossibleDerivation = true;
      return;
    }

    const sourceValues = Object.fromEntries(
      sourceFields.flatMap((key) => {
        const value = dimensionValue(key, state.dimensions) ?? values[key];
        return value === undefined ? [] : [[key, value]];
      }),
    ) as Partial<Record<CuboidFieldKey, number>>;
    const derivation: CuboidDerivation = {
      id,
      target,
      label: `Solve ${target}`,
      formula,
      sourceFields,
      sourceValues,
      result,
    };

    states.push({
      dimensions: { ...state.dimensions, [target]: result },
      derivations: [...state.derivations, derivation],
      assumptions: state.assumptions + 1,
    });
  };

  if (target === "length") {
    if (width !== null && height !== null) {
      deriveGlobalDimension(
        add,
        "length",
        width,
        height,
        "width",
        "height",
        values,
      );
    }
    if (width !== null) {
      deriveFaceDimension(
        add,
        "length",
        width,
        "width",
        "lengthWidthArea",
        "lengthWidthDiagonal",
        "lengthWidthPerimeter",
        values,
      );
    }
    if (height !== null) {
      deriveFaceDimension(
        add,
        "length",
        height,
        "height",
        "lengthHeightArea",
        "lengthHeightDiagonal",
        "lengthHeightPerimeter",
        values,
      );
    }
  }

  if (target === "width") {
    if (length !== null && height !== null) {
      deriveGlobalDimension(
        add,
        "width",
        length,
        height,
        "length",
        "height",
        values,
      );
    }
    if (length !== null) {
      deriveFaceDimension(
        add,
        "width",
        length,
        "length",
        "lengthWidthArea",
        "lengthWidthDiagonal",
        "lengthWidthPerimeter",
        values,
      );
    }
    if (height !== null) {
      deriveFaceDimension(
        add,
        "width",
        height,
        "height",
        "widthHeightArea",
        "widthHeightDiagonal",
        "widthHeightPerimeter",
        values,
      );
    }
  }

  if (target === "height") {
    if (length !== null && width !== null) {
      deriveGlobalDimension(
        add,
        "height",
        length,
        width,
        "length",
        "width",
        values,
      );
    }
    if (length !== null) {
      deriveFaceDimension(
        add,
        "height",
        length,
        "length",
        "lengthHeightArea",
        "lengthHeightDiagonal",
        "lengthHeightPerimeter",
        values,
      );
    }
    if (width !== null) {
      deriveFaceDimension(
        add,
        "height",
        width,
        "width",
        "widthHeightArea",
        "widthHeightDiagonal",
        "widthHeightPerimeter",
        values,
      );
    }
  }

  return { states, hadImpossibleDerivation };
}

function deriveGlobalDimension(
  add: (
    id: string,
    formula: string,
    sourceFields: CuboidFieldKey[],
    result: number,
  ) => void,
  target: CuboidDimensionKey,
  first: number,
  second: number,
  firstKey: CuboidDimensionKey,
  secondKey: CuboidDimensionKey,
  values: Partial<Record<CuboidFieldKey, number>>,
) {
  const targetSymbol = DEFINITION_BY_KEY[target].symbol;
  const firstSymbol = DEFINITION_BY_KEY[firstKey].symbol;
  const secondSymbol = DEFINITION_BY_KEY[secondKey].symbol;

  if (values.volume !== undefined) {
    add(
      `${target}-from-volume`,
      `${targetSymbol} = V / (${firstSymbol}${secondSymbol})`,
      ["volume", firstKey, secondKey],
      values.volume / (first * second),
    );
  }
  if (values.surfaceArea !== undefined) {
    add(
      `${target}-from-surface-area`,
      `${targetSymbol} = (S / 2 - ${firstSymbol}${secondSymbol}) / (${firstSymbol} + ${secondSymbol})`,
      ["surfaceArea", firstKey, secondKey],
      (values.surfaceArea / 2 - first * second) / (first + second),
    );
  }
  if (values.spaceDiagonal !== undefined) {
    add(
      `${target}-from-space-diagonal`,
      `${targetSymbol} = √(d² - ${firstSymbol}² - ${secondSymbol}²)`,
      ["spaceDiagonal", firstKey, secondKey],
      positiveSquareRoot(
        values.spaceDiagonal ** 2 - first ** 2 - second ** 2,
      ),
    );
  }
  if (values.circumsphereRadius !== undefined) {
    add(
      `${target}-from-circumsphere-radius`,
      `${targetSymbol} = √((2R)² - ${firstSymbol}² - ${secondSymbol}²)`,
      ["circumsphereRadius", firstKey, secondKey],
      positiveSquareRoot(
        (2 * values.circumsphereRadius) ** 2 -
          first ** 2 -
          second ** 2,
      ),
    );
  }
  if (values.totalEdgeLength !== undefined) {
    add(
      `${target}-from-edge-length`,
      `${targetSymbol} = E / 4 - ${firstSymbol} - ${secondSymbol}`,
      ["totalEdgeLength", firstKey, secondKey],
      values.totalEdgeLength / 4 - first - second,
    );
  }
  if (values.lateralSurface !== undefined) {
    if (target === "height") {
      add(
        "height-from-lateral-surface",
        "h = Sl / (2(l + w))",
        ["lateralSurface", "length", "width"],
        values.lateralSurface / (2 * (first + second)),
      );
    } else if (secondKey === "height") {
      add(
        `${target}-from-lateral-surface`,
        `${targetSymbol} = Sl / (2h) - ${firstSymbol}`,
        ["lateralSurface", "height", firstKey],
        values.lateralSurface / (2 * second) - first,
      );
    }
  }
}

function deriveFaceDimension(
  add: (
    id: string,
    formula: string,
    sourceFields: CuboidFieldKey[],
    result: number,
  ) => void,
  target: CuboidDimensionKey,
  relatedDimension: number,
  relatedKey: CuboidDimensionKey,
  areaKey: CuboidFieldKey,
  diagonalKey: CuboidFieldKey,
  perimeterKey: CuboidFieldKey,
  values: Partial<Record<CuboidFieldKey, number>>,
) {
  const targetSymbol = DEFINITION_BY_KEY[target].symbol;
  const relatedSymbol = DEFINITION_BY_KEY[relatedKey].symbol;

  if (values[areaKey] !== undefined) {
    add(
      `${target}-from-${areaKey}`,
      `${targetSymbol} = ${DEFINITION_BY_KEY[areaKey].symbol} / ${relatedSymbol}`,
      [areaKey, relatedKey],
      values[areaKey] / relatedDimension,
    );
  }
  if (values[diagonalKey] !== undefined) {
    add(
      `${target}-from-${diagonalKey}`,
      `${targetSymbol} = √(${DEFINITION_BY_KEY[diagonalKey].symbol}² - ${relatedSymbol}²)`,
      [diagonalKey, relatedKey],
      positiveSquareRoot(values[diagonalKey] ** 2 - relatedDimension ** 2),
    );
  }
  if (values[perimeterKey] !== undefined) {
    add(
      `${target}-from-${perimeterKey}`,
      `${targetSymbol} = ${DEFINITION_BY_KEY[perimeterKey].symbol} / 2 - ${relatedSymbol}`,
      [perimeterKey, relatedKey],
      values[perimeterKey] / 2 - relatedDimension,
    );
  }
}

function addVolumeFaceAreaSeeds(
  values: Partial<Record<CuboidFieldKey, number>>,
  addSeed: (
    dimensions: PartialCuboidDimensions,
    derivations: CuboidDerivation[],
  ) => void,
) {
  const volume = values.volume;
  if (volume === undefined) return;

  const combinations: Array<{
    areaKey: CuboidFieldKey;
    target: CuboidDimensionKey;
    formula: string;
  }> = [
    {
      areaKey: "lengthWidthArea",
      target: "height",
      formula: "h = V / Alw",
    },
    {
      areaKey: "lengthHeightArea",
      target: "width",
      formula: "w = V / Alh",
    },
    {
      areaKey: "widthHeightArea",
      target: "length",
      formula: "l = V / Awh",
    },
  ];

  for (const { areaKey, target, formula } of combinations) {
    const area = values[areaKey];
    if (area === undefined) continue;
    const result = volume / area;
    addSeed(
      emptyDimensions({ [target]: result }),
      [
        derivation(
          `${target}-from-volume-and-${areaKey}`,
          target,
          formula,
          ["volume", areaKey],
          values,
          result,
        ),
      ],
    );
  }
}

function addThreeFaceAreaSeed(
  values: Partial<Record<CuboidFieldKey, number>>,
  addSeed: (
    dimensions: PartialCuboidDimensions,
    derivations: CuboidDerivation[],
  ) => void,
) {
  const lw = values.lengthWidthArea;
  const lh = values.lengthHeightArea;
  const wh = values.widthHeightArea;
  if (lw === undefined || lh === undefined || wh === undefined) return;

  const length = positiveSquareRoot((lw * lh) / wh);
  const width = positiveSquareRoot((lw * wh) / lh);
  const height = positiveSquareRoot((lh * wh) / lw);
  const sources: CuboidFieldKey[] = [
    "lengthWidthArea",
    "lengthHeightArea",
    "widthHeightArea",
  ];

  addSeed(
    { length, width, height },
    [
      derivation(
        "length-from-three-face-areas",
        "length",
        "l = √((Alw × Alh) / Awh)",
        sources,
        values,
        length,
      ),
      derivation(
        "width-from-three-face-areas",
        "width",
        "w = √((Alw × Awh) / Alh)",
        sources,
        values,
        width,
      ),
      derivation(
        "height-from-three-face-areas",
        "height",
        "h = √((Alh × Awh) / Alw)",
        sources,
        values,
        height,
      ),
    ],
  );
}

function addThreeFacePerimeterSeed(
  values: Partial<Record<CuboidFieldKey, number>>,
  addSeed: (
    dimensions: PartialCuboidDimensions,
    derivations: CuboidDerivation[],
  ) => void,
) {
  const lw = values.lengthWidthPerimeter;
  const lh = values.lengthHeightPerimeter;
  const wh = values.widthHeightPerimeter;
  if (lw === undefined || lh === undefined || wh === undefined) return;

  const length = (lw + lh - wh) / 4;
  const width = (lw + wh - lh) / 4;
  const height = (lh + wh - lw) / 4;
  const sources: CuboidFieldKey[] = [
    "lengthWidthPerimeter",
    "lengthHeightPerimeter",
    "widthHeightPerimeter",
  ];

  addSeed(
    { length, width, height },
    [
      derivation(
        "length-from-three-face-perimeters",
        "length",
        "l = (Plw + Plh - Pwh) / 4",
        sources,
        values,
        length,
      ),
      derivation(
        "width-from-three-face-perimeters",
        "width",
        "w = (Plw + Pwh - Plh) / 4",
        sources,
        values,
        width,
      ),
      derivation(
        "height-from-three-face-perimeters",
        "height",
        "h = (Plh + Pwh - Plw) / 4",
        sources,
        values,
        height,
      ),
    ],
  );
}

function addThreeFaceDiagonalSeed(
  values: Partial<Record<CuboidFieldKey, number>>,
  addSeed: (
    dimensions: PartialCuboidDimensions,
    derivations: CuboidDerivation[],
  ) => void,
) {
  const lw = values.lengthWidthDiagonal;
  const lh = values.lengthHeightDiagonal;
  const wh = values.widthHeightDiagonal;
  if (lw === undefined || lh === undefined || wh === undefined) return;

  const length = positiveSquareRoot((lw ** 2 + lh ** 2 - wh ** 2) / 2);
  const width = positiveSquareRoot((lw ** 2 + wh ** 2 - lh ** 2) / 2);
  const height = positiveSquareRoot((lh ** 2 + wh ** 2 - lw ** 2) / 2);
  const sources: CuboidFieldKey[] = [
    "lengthWidthDiagonal",
    "lengthHeightDiagonal",
    "widthHeightDiagonal",
  ];

  addSeed(
    { length, width, height },
    [
      derivation(
        "length-from-three-face-diagonals",
        "length",
        "l = √((Dlw² + Dlh² - Dwh²) / 2)",
        sources,
        values,
        length,
      ),
      derivation(
        "width-from-three-face-diagonals",
        "width",
        "w = √((Dlw² + Dwh² - Dlh²) / 2)",
        sources,
        values,
        width,
      ),
      derivation(
        "height-from-three-face-diagonals",
        "height",
        "h = √((Dlh² + Dwh² - Dlw²) / 2)",
        sources,
        values,
        height,
      ),
    ],
  );
}

function addFacePairSeeds(
  values: Partial<Record<CuboidFieldKey, number>>,
  addSeed: (
    dimensions: PartialCuboidDimensions,
    derivations: CuboidDerivation[],
  ) => void,
) {
  const faces: Array<{
    first: CuboidDimensionKey;
    second: CuboidDimensionKey;
    area: CuboidFieldKey;
    diagonal: CuboidFieldKey;
    perimeter: CuboidFieldKey;
  }> = [
    {
      first: "length",
      second: "width",
      area: "lengthWidthArea",
      diagonal: "lengthWidthDiagonal",
      perimeter: "lengthWidthPerimeter",
    },
    {
      first: "length",
      second: "height",
      area: "lengthHeightArea",
      diagonal: "lengthHeightDiagonal",
      perimeter: "lengthHeightPerimeter",
    },
    {
      first: "width",
      second: "height",
      area: "widthHeightArea",
      diagonal: "widthHeightDiagonal",
      perimeter: "widthHeightPerimeter",
    },
  ];

  for (const face of faces) {
    const area = values[face.area];
    const diagonal = values[face.diagonal];
    const perimeter = values[face.perimeter];
    const pairs: Array<{
      roots: Array<[number, number]>;
      formula: string;
      sources: CuboidFieldKey[];
      id: string;
    }> = [];

    if (area !== undefined && perimeter !== undefined) {
      pairs.push({
        roots: rootsFromSumAndProduct(perimeter / 2, area),
        formula: "x + y = P / 2, xy = A",
        sources: [face.area, face.perimeter],
        id: "area-perimeter",
      });
    }
    if (area !== undefined && diagonal !== undefined) {
      pairs.push({
        roots: rootsFromSumAndProduct(
          positiveSquareRoot(diagonal ** 2 + 2 * area),
          area,
        ),
        formula: "x + y = √(D² + 2A), xy = A",
        sources: [face.area, face.diagonal],
        id: "area-diagonal",
      });
    }
    if (perimeter !== undefined && diagonal !== undefined) {
      const sum = perimeter / 2;
      pairs.push({
        roots: rootsFromSumAndProduct(
          sum,
          (sum ** 2 - diagonal ** 2) / 2,
        ),
        formula: "x + y = P / 2, xy = ((P / 2)² - D²) / 2",
        sources: [face.perimeter, face.diagonal],
        id: "perimeter-diagonal",
      });
    }

    for (const pair of pairs) {
      for (const [firstValue, secondValue] of pair.roots) {
        const dimensions = emptyDimensions({
          [face.first]: firstValue,
          [face.second]: secondValue,
        });
        const remainingDimension = DIMENSION_KEYS.find(
          (key) => key !== face.first && key !== face.second,
        );
        if (
          remainingDimension !== undefined &&
          values[remainingDimension] !== undefined
        ) {
          dimensions[remainingDimension] = values[remainingDimension] as number;
        }

        addSeed(
          dimensions,
          [
            derivation(
              `${face.first}-from-${pair.id}`,
              face.first,
              pair.formula,
              pair.sources,
              values,
              firstValue,
            ),
            derivation(
              `${face.second}-from-${pair.id}`,
              face.second,
              pair.formula,
              pair.sources,
              values,
              secondValue,
            ),
          ],
        );
      }
    }
  }
}

function candidateFromState(
  state: PartialState,
  enteredValues: Partial<Record<CuboidFieldKey, number>>,
): CuboidCandidate {
  const dimensions = state.dimensions as CuboidDimensions;
  const values = cuboidValuesFromDimensions(dimensions);
  const evaluation = evaluateValues(values, enteredValues);

  return {
    ...dimensions,
    values,
    formulasUsed: uniqueStrings(
      state.derivations.map(({ formula }) => formula),
    ),
    derivations: state.derivations,
    conflicts: evaluation.conflicts,
    matchedFields: evaluation.matchedFields,
    totalError: evaluation.totalError,
    assumptions: state.assumptions,
  };
}

function partialCandidateFromState(
  state: PartialState,
  enteredValues: Partial<Record<CuboidFieldKey, number>>,
): CuboidPartialCandidate {
  const values = partialValuesFromDimensions(state.dimensions);
  const evaluation = evaluateValues(values, enteredValues);

  return {
    dimensions: state.dimensions,
    values,
    derivations: state.derivations,
    conflicts: evaluation.conflicts,
    matchedFields: evaluation.matchedFields,
    totalError: evaluation.totalError,
    assumptions: state.assumptions,
  };
}

function partialValuesFromDimensions(
  dimensions: PartialCuboidDimensions,
): Partial<Record<CuboidFieldKey, number>> {
  const { length, width, height } = dimensions;
  const values: Partial<Record<CuboidFieldKey, number>> = {};

  if (length !== null) values.length = length;
  if (width !== null) values.width = width;
  if (height !== null) values.height = height;

  if (length !== null && width !== null) {
    values.lengthWidthArea = length * width;
    values.lengthWidthDiagonal = Math.hypot(length, width);
    values.lengthWidthPerimeter = 2 * (length + width);
  }
  if (length !== null && height !== null) {
    values.lengthHeightArea = length * height;
    values.lengthHeightDiagonal = Math.hypot(length, height);
    values.lengthHeightPerimeter = 2 * (length + height);
  }
  if (width !== null && height !== null) {
    values.widthHeightArea = width * height;
    values.widthHeightDiagonal = Math.hypot(width, height);
    values.widthHeightPerimeter = 2 * (width + height);
  }

  if (length !== null && width !== null && height !== null) {
    return cuboidValuesFromDimensions({ length, width, height });
  }

  return values;
}

function evaluateValues(
  expectedValues: Partial<Record<CuboidFieldKey, number>>,
  enteredValues: Partial<Record<CuboidFieldKey, number>>,
): {
  conflicts: CuboidConflict[];
  matchedFields: CuboidFieldKey[];
  totalError: number;
} {
  const conflicts: CuboidConflict[] = [];
  const matchedFields: CuboidFieldKey[] = [];
  let totalError = 0;

  for (const key of CUBOID_FIELD_KEYS) {
    const enteredValue = enteredValues[key];
    const expectedValue = expectedValues[key];
    if (enteredValue === undefined || expectedValue === undefined) continue;

    const absoluteDifference = Math.abs(enteredValue - expectedValue);
    const relativeDifference =
      absoluteDifference /
      Math.max(Math.abs(enteredValue), Math.abs(expectedValue), ABSOLUTE_TOLERANCE);
    totalError += relativeDifference;

    if (approximatelyEqual(enteredValue, expectedValue)) {
      matchedFields.push(key);
    } else {
      conflicts.push({
        field: key,
        enteredValue,
        expectedValue,
        absoluteDifference,
        relativeDifference,
        message:
          "The entered :field value conflicts with the solved cuboid. Expected :expected.",
      });
    }
  }

  return { conflicts, matchedFields, totalError };
}

function rankFullCandidates(
  candidates: CuboidCandidate[],
): CuboidCandidate[] {
  const ranked = deduplicateFullCandidates(candidates).sort(compareCandidates);
  if (!ranked.length) return [];
  return ranked.filter((candidate) => equalCandidateRank(candidate, ranked[0]));
}

function rankPartialCandidates(
  candidates: CuboidPartialCandidate[],
): CuboidPartialCandidate[] {
  const ranked = deduplicatePartialCandidates(candidates).sort(
    (left, right) =>
      resolvedDimensionCount(right.dimensions) -
        resolvedDimensionCount(left.dimensions) ||
      compareCandidates(left, right),
  );
  if (!ranked.length) return [];
  const best = ranked[0];

  return ranked.filter(
    (candidate) =>
      resolvedDimensionCount(candidate.dimensions) ===
        resolvedDimensionCount(best.dimensions) &&
      equalCandidateRank(candidate, best),
  );
}

function compareCandidates(
  left: CuboidCandidate | CuboidPartialCandidate,
  right: CuboidCandidate | CuboidPartialCandidate,
): number {
  return (
    left.conflicts.length - right.conflicts.length ||
    left.totalError - right.totalError ||
    right.matchedFields.length - left.matchedFields.length ||
    left.assumptions - right.assumptions
  );
}

function equalCandidateRank(
  left: CuboidCandidate | CuboidPartialCandidate,
  right: CuboidCandidate | CuboidPartialCandidate,
): boolean {
  return (
    left.conflicts.length === right.conflicts.length &&
    approximatelyEqual(left.totalError, right.totalError) &&
    left.matchedFields.length === right.matchedFields.length &&
    left.assumptions === right.assumptions
  );
}

function deduplicateStates(states: PartialState[]): PartialState[] {
  const unique: PartialState[] = [];

  for (const state of states) {
    const existingIndex = unique.findIndex((candidate) =>
      samePartialDimensions(candidate.dimensions, state.dimensions),
    );
    if (existingIndex === -1) {
      unique.push(state);
    } else if (state.assumptions < unique[existingIndex].assumptions) {
      unique[existingIndex] = state;
    }
  }

  return unique;
}

function deduplicateFullCandidates(
  candidates: CuboidCandidate[],
): CuboidCandidate[] {
  return candidates.filter(
    (candidate, index) =>
      candidates.findIndex(
        (other) =>
          approximatelyEqual(candidate.length, other.length) &&
          approximatelyEqual(candidate.width, other.width) &&
          approximatelyEqual(candidate.height, other.height),
      ) === index,
  );
}

function deduplicatePartialCandidates(
  candidates: CuboidPartialCandidate[],
): CuboidPartialCandidate[] {
  return candidates.filter(
    (candidate, index) =>
      candidates.findIndex((other) =>
        samePartialDimensions(candidate.dimensions, other.dimensions),
      ) === index,
  );
}

function samePartialDimensions(
  left: PartialCuboidDimensions,
  right: PartialCuboidDimensions,
): boolean {
  return DIMENSION_KEYS.every((key) => {
    const leftValue = left[key];
    const rightValue = right[key];
    if (leftValue === null || rightValue === null) {
      return leftValue === rightValue;
    }
    return approximatelyEqual(leftValue, rightValue);
  });
}

function rootsFromSumAndProduct(
  sum: number,
  product: number,
): Array<[number, number]> {
  if (!validDimension(sum) || !validDimension(product)) return [];

  const rawDiscriminant = sum ** 2 - 4 * product;
  const tolerance =
    RELATIVE_TOLERANCE * Math.max(sum ** 2, 4 * product, 1);
  if (rawDiscriminant < -tolerance) return [];

  const root = Math.sqrt(Math.max(0, rawDiscriminant));
  const larger = (sum + root) / 2;
  const smaller = product / larger;
  if (!validDimension(larger) || !validDimension(smaller)) return [];
  if (approximatelyEqual(larger, smaller)) return [[larger, smaller]];

  return [
    [larger, smaller],
    [smaller, larger],
  ];
}

function derivation(
  id: string,
  target: CuboidDimensionKey,
  formula: string,
  sourceFields: CuboidFieldKey[],
  sourceValues: Partial<Record<CuboidFieldKey, number>>,
  result: number,
): CuboidDerivation {
  return {
    id,
    target,
    label: `Solve ${target}`,
    formula,
    sourceFields,
    sourceValues: Object.fromEntries(
      sourceFields.flatMap((key) =>
        sourceValues[key] === undefined
          ? []
          : [[key, sourceValues[key] as number]],
      ),
    ),
    result,
  };
}

function emptyDimensions(
  values: Partial<Record<CuboidDimensionKey, number>>,
): PartialCuboidDimensions {
  return {
    length: values.length ?? null,
    width: values.width ?? null,
    height: values.height ?? null,
  };
}

function dimensionValue(
  key: CuboidFieldKey,
  dimensions: PartialCuboidDimensions,
): number | null {
  return DIMENSION_KEYS.includes(key as CuboidDimensionKey)
    ? dimensions[key as CuboidDimensionKey]
    : null;
}

function isCompleteState(
  state: PartialState,
): state is PartialState & { dimensions: CuboidDimensions } {
  return DIMENSION_KEYS.every((key) => state.dimensions[key] !== null);
}

function validPartialDimensions(
  dimensions: PartialCuboidDimensions,
): boolean {
  return DIMENSION_KEYS.every((key) => {
    const value = dimensions[key];
    return value === null || validDimension(value);
  });
}

function validDimension(value: number): boolean {
  return Number.isFinite(value) && value > 0 && value <= MAX_CUBOID_SIDE;
}

function positiveSquareRoot(value: number): number {
  if (!Number.isFinite(value) || value <= 0) return Number.NaN;
  return Math.sqrt(value);
}

function resolvedDimensionCount(
  dimensions: PartialCuboidDimensions,
): number {
  return DIMENSION_KEYS.filter((key) => dimensions[key] !== null).length;
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

function uniqueStrings(values: string[]): string[] {
  return values.filter((value, index) => values.indexOf(value) === index);
}

function buildWarnings({
  invalidFields,
  conflicts,
  ambiguous,
  incomplete,
  impossible,
}: {
  invalidFields: CuboidFieldKey[];
  conflicts: CuboidConflict[];
  ambiguous: boolean;
  incomplete: boolean;
  impossible: boolean;
}): string[] {
  const warnings: string[] = [];

  if (invalidFields.length) {
    warnings.push("Invalid cuboid measurements were ignored.");
  }
  if (conflicts.length) {
    warnings.push(
      "Some entered measurements conflict with the closest cuboid solution.",
    );
  }
  if (ambiguous) {
    warnings.push("These measurements produce multiple valid cuboids.");
  }
  if (incomplete) {
    warnings.push(
      impossible
        ? "The entered measurements cannot form a valid cuboid."
        : "Not enough compatible information is available to solve all three dimensions yet.",
    );
  }

  return warnings;
}

function emptySolution(
  warnings: string[],
  invalidFields: CuboidFieldKey[],
): CuboidSolution {
  return {
    candidates: [],
    partialCandidates: [],
    selectedCandidate: null,
    values: {},
    calculatedFields: [],
    conflicts: [],
    warnings,
    invalidFields,
    isValid: false,
    isAmbiguous: false,
  };
}

function definition(
  key: CuboidFieldKey,
  label: string,
  symbol: string,
  dimension: 1 | 2 | 3,
  maximum: number,
  forwardFormula: string,
  group: CuboidFieldDefinition["group"],
  editable = true,
): CuboidFieldDefinition {
  return {
    key,
    label,
    symbol,
    dimension,
    maximum,
    forwardFormula,
    group,
    editable,
  };
}
