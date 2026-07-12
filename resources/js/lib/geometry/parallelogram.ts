import type {
  ParallelogramMeasureKey,
  ParallelogramMeasurements,
  ParallelogramSolveResult,
  SolvedParallelogram,
} from "@/types/twoDGeometry";

const MEASURE_KEYS: ParallelogramMeasureKey[] = [
  "base",
  "side",
  "height",
  "area",
  "perimeter",
  "angle",
  "diagonalA",
  "diagonalB",
];
const COMPARISON_TOLERANCE = 1e-7;
const ANGLE_TOLERANCE = 1e-10;

export function solveParallelogram(
  values: ParallelogramMeasurements,
  maximumDimension = Number.POSITIVE_INFINITY,
): ParallelogramSolveResult {
  const candidates: SolvedParallelogram[] = [];
  const add = (base: number, side: number, angle: number, source: ParallelogramMeasureKey[]) => {
    const candidate = deriveParallelogram(base, side, angle, source);
    if (candidate) candidates.push(candidate);
  };

  const base = values.base;
  const side = values.side;
  const height = values.height;
  const area = values.area;
  const perimeter = values.perimeter;
  const angle = values.angle !== null ? degreesToRadians(values.angle) : null;
  const diagonalA = values.diagonalA;
  const diagonalB = values.diagonalB;

  if (base !== null && side !== null) {
    addFromBaseSide(add, base, side, values, ["base", "side"]);
  }
  if (base !== null && height !== null) {
    addFromBaseHeight(add, base, height, values, ["base", "height"]);
  }
  if (base !== null && area !== null) {
    addFromBaseHeight(add, base, area / base, values, ["base", "area"]);
  }
  if (area !== null && height !== null) {
    addFromBaseHeight(add, area / height, height, values, ["area", "height"]);
  }
  if (side !== null && height !== null) {
    addFromSideHeight(add, side, height, values, ["side", "height"]);
  }
  if (base !== null && angle !== null) {
    addFromBaseAngle(add, base, angle, values, ["base", "angle"]);
  }
  if (side !== null && angle !== null) {
    addFromSideAngle(add, side, angle, values, ["side", "angle"]);
  }
  if (height !== null && angle !== null) {
    const sine = Math.sin(angle);
    if (sine > ANGLE_TOLERANCE) {
      addFromSideAngle(add, height / sine, angle, values, ["height", "angle"]);
    }
  }
  if (base !== null && perimeter !== null) {
    addFromBaseSide(add, base, perimeter / 2 - base, values, ["base", "perimeter"]);
  }
  if (side !== null && perimeter !== null) {
    addFromBaseSide(add, perimeter / 2 - side, side, values, ["side", "perimeter"]);
  }

  addAreaPerimeterAngleCandidates(add, values);
  addAreaOrPerimeterDiagonalAngleCandidates(add, values);
  addAreaPerimeterDiagonalCandidates(add, values);
  addSideAreaDiagonalCandidates(add, values);
  addPerimeterHeightDiagonalCandidates(add, values);
  addTwoDiagonalCandidates(add, values);

  const knownKeys = MEASURE_KEYS.filter((key) => values[key] !== null);
  const uniqueCandidates = deduplicateCandidates(candidates);

  if (!uniqueCandidates.length) {
    const hasEnoughFields = knownKeys.length >= 3;
    return {
      candidates: [],
      error: !hasEnoughFields
        ? "not-enough-information"
        : hasSufficientPattern(values)
          ? "impossible-measurements"
          : "insufficient-independent-information",
    };
  }

  const boundedCandidates = uniqueCandidates.filter(
    (candidate) =>
      candidate.base <= maximumDimension &&
      candidate.side <= maximumDimension &&
      candidate.height <= maximumDimension,
  );

  if (!boundedCandidates.length) {
    return { candidates: [], error: "solution-out-of-range" };
  }

  const ranked = boundedCandidates
    .map((candidate, index) => ({
      solution: candidate,
      index,
      mismatches: mismatchKeys(candidate, values, knownKeys),
    }))
    .sort(
      (left, right) =>
        left.mismatches.length - right.mismatches.length || left.index - right.index,
    );
  const bestMismatchCount = ranked[0].mismatches.length;

  return {
    candidates: ranked
      .filter((candidate) => candidate.mismatches.length === bestMismatchCount)
      .map(({ solution, mismatches }) => ({ solution, mismatches })),
    error: null,
  };
}

function hasSufficientPattern(values: ParallelogramMeasurements): boolean {
  const has = (...keys: ParallelogramMeasureKey[]) =>
    keys.every((key) => values[key] !== null);
  const hasAny = (...keys: ParallelogramMeasureKey[]) =>
    keys.some((key) => values[key] !== null);
  const angleBearing: ParallelogramMeasureKey[] = [
    "height",
    "area",
    "angle",
    "diagonalA",
    "diagonalB",
  ];

  return (
    (has("base", "side") && hasAny(...angleBearing)) ||
    (has("base", "height") &&
      hasAny("side", "perimeter", "angle", "diagonalA", "diagonalB")) ||
    (has("base", "area") &&
      hasAny("side", "perimeter", "angle", "diagonalA", "diagonalB")) ||
    (has("area", "height") &&
      hasAny("side", "perimeter", "angle", "diagonalA", "diagonalB")) ||
    (has("side", "height") &&
      hasAny("base", "area", "perimeter", "diagonalA", "diagonalB")) ||
    (has("base", "angle") &&
      hasAny("side", "height", "area", "perimeter", "diagonalA", "diagonalB")) ||
    (has("side", "angle") &&
      hasAny("base", "area", "perimeter", "diagonalA", "diagonalB")) ||
    (has("height", "angle") &&
      hasAny("base", "area", "perimeter", "diagonalA", "diagonalB")) ||
    has("area", "perimeter", "angle") ||
    (has("area", "angle") && hasAny("diagonalA", "diagonalB")) ||
    (has("perimeter", "angle") && hasAny("diagonalA", "diagonalB")) ||
    (has("area", "perimeter") && hasAny("diagonalA", "diagonalB")) ||
    (has("side", "area") && hasAny("diagonalA", "diagonalB")) ||
    (has("perimeter", "height") && hasAny("diagonalA", "diagonalB")) ||
    (has("diagonalA", "diagonalB") &&
      hasAny("base", "side", "height", "area", "perimeter", "angle"))
  );
}

type AddCandidate = (
  base: number,
  side: number,
  angle: number,
  source: ParallelogramMeasureKey[],
) => void;

function addFromBaseSide(
  add: AddCandidate,
  base: number,
  side: number,
  values: ParallelogramMeasurements,
  source: ParallelogramMeasureKey[],
) {
  if (!isPositive(base) || !isPositive(side)) return;

  if (values.angle !== null) {
    add(base, side, degreesToRadians(values.angle), [...source, "angle"]);
  }
  if (values.height !== null) {
    for (const angle of anglesFromSine(values.height / side)) {
      add(base, side, angle, [...source, "height"]);
    }
  }
  if (values.area !== null) {
    for (const angle of anglesFromSine(values.area / (base * side))) {
      add(base, side, angle, [...source, "area"]);
    }
  }
  if (values.diagonalA !== null) {
    const cosine =
      (values.diagonalA ** 2 - base ** 2 - side ** 2) / (2 * base * side);
    const angle = angleFromCosine(cosine);
    if (angle !== null) add(base, side, angle, [...source, "diagonalA"]);
  }
  if (values.diagonalB !== null) {
    const cosine =
      (base ** 2 + side ** 2 - values.diagonalB ** 2) / (2 * base * side);
    const angle = angleFromCosine(cosine);
    if (angle !== null) add(base, side, angle, [...source, "diagonalB"]);
  }
}

function addFromBaseHeight(
  add: AddCandidate,
  base: number,
  height: number,
  values: ParallelogramMeasurements,
  source: ParallelogramMeasureKey[],
) {
  if (!isPositive(base) || !isPositive(height)) return;

  const sideValues: Array<{ value: number; key: ParallelogramMeasureKey }> = [];
  if (values.side !== null) sideValues.push({ value: values.side, key: "side" });
  if (values.perimeter !== null) {
    sideValues.push({ value: values.perimeter / 2 - base, key: "perimeter" });
  }

  for (const entry of sideValues) {
    for (const angle of anglesFromSine(height / entry.value)) {
      add(base, entry.value, angle, [...source, entry.key]);
    }
  }

  if (values.angle !== null) {
    const angle = degreesToRadians(values.angle);
    const sine = Math.sin(angle);
    if (sine > ANGLE_TOLERANCE) {
      add(base, height / sine, angle, [...source, "angle"]);
    }
  }

  if (values.diagonalA !== null) {
    const projection = nonNegativeSqrt(values.diagonalA ** 2 - height ** 2);
    if (projection !== null) {
      for (const offset of uniqueNumbers([-base + projection, -base - projection])) {
        add(base, Math.hypot(offset, height), Math.atan2(height, offset), [
          ...source,
          "diagonalA",
        ]);
      }
    }
  }
  if (values.diagonalB !== null) {
    const projection = nonNegativeSqrt(values.diagonalB ** 2 - height ** 2);
    if (projection !== null) {
      for (const offset of uniqueNumbers([base + projection, base - projection])) {
        add(base, Math.hypot(offset, height), Math.atan2(height, offset), [
          ...source,
          "diagonalB",
        ]);
      }
    }
  }
}

function addFromSideHeight(
  add: AddCandidate,
  side: number,
  height: number,
  values: ParallelogramMeasurements,
  source: ParallelogramMeasureKey[],
) {
  const projection = nonNegativeSqrt(side ** 2 - height ** 2);
  if (!isPositive(side) || !isPositive(height) || projection === null) return;

  for (const offset of uniqueNumbers([projection, -projection])) {
    const angle = Math.atan2(height, offset);
    const baseValues: Array<{ value: number; key: ParallelogramMeasureKey }> = [];
    if (values.base !== null) baseValues.push({ value: values.base, key: "base" });
    if (values.area !== null) baseValues.push({ value: values.area / height, key: "area" });
    if (values.perimeter !== null) {
      baseValues.push({ value: values.perimeter / 2 - side, key: "perimeter" });
    }

    for (const entry of baseValues) {
      add(entry.value, side, angle, [...source, entry.key]);
    }

    addBasesFromHeightDiagonal(add, side, height, offset, angle, values, source);
  }
}

function addBasesFromHeightDiagonal(
  add: AddCandidate,
  side: number,
  height: number,
  offset: number,
  angle: number,
  values: ParallelogramMeasurements,
  source: ParallelogramMeasureKey[],
) {
  if (values.diagonalA !== null) {
    const span = nonNegativeSqrt(values.diagonalA ** 2 - height ** 2);
    if (span !== null) {
      for (const base of uniqueNumbers([-offset + span, -offset - span])) {
        add(base, side, angle, [...source, "diagonalA"]);
      }
    }
  }
  if (values.diagonalB !== null) {
    const span = nonNegativeSqrt(values.diagonalB ** 2 - height ** 2);
    if (span !== null) {
      for (const base of uniqueNumbers([offset + span, offset - span])) {
        add(base, side, angle, [...source, "diagonalB"]);
      }
    }
  }
}

function addFromBaseAngle(
  add: AddCandidate,
  base: number,
  angle: number,
  values: ParallelogramMeasurements,
  source: ParallelogramMeasureKey[],
) {
  const sine = Math.sin(angle);
  const cosine = Math.cos(angle);
  if (!isValidAngle(angle) || !isPositive(base) || sine <= ANGLE_TOLERANCE) return;

  const sideValues: Array<{ value: number; key: ParallelogramMeasureKey }> = [];
  if (values.side !== null) sideValues.push({ value: values.side, key: "side" });
  if (values.height !== null) sideValues.push({ value: values.height / sine, key: "height" });
  if (values.area !== null) sideValues.push({ value: values.area / (base * sine), key: "area" });
  if (values.perimeter !== null) {
    sideValues.push({ value: values.perimeter / 2 - base, key: "perimeter" });
  }
  if (values.diagonalA !== null) {
    for (const side of positiveQuadraticRoots(
      2 * base * cosine,
      base ** 2 - values.diagonalA ** 2,
    )) sideValues.push({ value: side, key: "diagonalA" });
  }
  if (values.diagonalB !== null) {
    for (const side of positiveQuadraticRoots(
      -2 * base * cosine,
      base ** 2 - values.diagonalB ** 2,
    )) sideValues.push({ value: side, key: "diagonalB" });
  }
  for (const entry of sideValues) add(base, entry.value, angle, [...source, entry.key]);
}

function addFromSideAngle(
  add: AddCandidate,
  side: number,
  angle: number,
  values: ParallelogramMeasurements,
  source: ParallelogramMeasureKey[],
) {
  const sine = Math.sin(angle);
  const cosine = Math.cos(angle);
  if (!isValidAngle(angle) || !isPositive(side) || sine <= ANGLE_TOLERANCE) return;

  const baseValues: Array<{ value: number; key: ParallelogramMeasureKey }> = [];
  if (values.base !== null) baseValues.push({ value: values.base, key: "base" });
  if (values.area !== null) baseValues.push({ value: values.area / (side * sine), key: "area" });
  if (values.perimeter !== null) {
    baseValues.push({ value: values.perimeter / 2 - side, key: "perimeter" });
  }
  if (values.diagonalA !== null) {
    for (const base of positiveQuadraticRoots(
      2 * side * cosine,
      side ** 2 - values.diagonalA ** 2,
    )) baseValues.push({ value: base, key: "diagonalA" });
  }
  if (values.diagonalB !== null) {
    for (const base of positiveQuadraticRoots(
      -2 * side * cosine,
      side ** 2 - values.diagonalB ** 2,
    )) baseValues.push({ value: base, key: "diagonalB" });
  }
  for (const entry of baseValues) add(entry.value, side, angle, [...source, entry.key]);
}

function addAreaPerimeterAngleCandidates(
  add: AddCandidate,
  values: ParallelogramMeasurements,
) {
  if (values.area === null || values.perimeter === null || values.angle === null) return;
  const angle = degreesToRadians(values.angle);
  const product = values.area / Math.sin(angle);
  for (const [base, side] of sidePairsFromSumProduct(values.perimeter / 2, product)) {
    add(base, side, angle, ["area", "perimeter", "angle"]);
  }
}

function addAreaOrPerimeterDiagonalAngleCandidates(
  add: AddCandidate,
  values: ParallelogramMeasurements,
) {
  if (values.angle === null) return;
  const angle = degreesToRadians(values.angle);
  const sine = Math.sin(angle);
  const cosine = Math.cos(angle);

  for (const diagonalKey of ["diagonalA", "diagonalB"] as const) {
    const diagonal = values[diagonalKey];
    if (diagonal === null) continue;
    const sign = diagonalKey === "diagonalA" ? 1 : -1;

    if (values.area !== null) {
      const product = values.area / sine;
      const squareSum = diagonal ** 2 - sign * 2 * product * cosine;
      for (const [base, side] of sidePairsFromSquareSumProduct(squareSum, product)) {
        add(base, side, angle, ["area", "angle", diagonalKey]);
      }
    }
    if (values.perimeter !== null) {
      const sum = values.perimeter / 2;
      const denominator = 2 * (1 - sign * cosine);
      const product = (sum ** 2 - diagonal ** 2) / denominator;
      for (const [base, side] of sidePairsFromSumProduct(sum, product)) {
        add(base, side, angle, ["perimeter", "angle", diagonalKey]);
      }
    }
  }
}

function addAreaPerimeterDiagonalCandidates(
  add: AddCandidate,
  values: ParallelogramMeasurements,
) {
  if (values.area === null || values.perimeter === null) return;
  const sum = values.perimeter / 2;

  for (const diagonalKey of ["diagonalA", "diagonalB"] as const) {
    const diagonal = values[diagonalKey];
    if (diagonal === null) continue;
    const constant =
      diagonalKey === "diagonalA"
        ? (diagonal ** 2 - sum ** 2) / 2
        : (sum ** 2 - diagonal ** 2) / 2;
    const product =
      diagonalKey === "diagonalA"
        ? -(values.area ** 2 + constant ** 2) / (2 * constant)
        : (values.area ** 2 + constant ** 2) / (2 * constant);
    const cosineProduct =
      diagonalKey === "diagonalA" ? product + constant : constant - product;
    const angle = Math.atan2(values.area, cosineProduct);
    for (const [base, side] of sidePairsFromSumProduct(sum, product)) {
      add(base, side, angle, ["area", "perimeter", diagonalKey]);
    }
  }
}

function addSideAreaDiagonalCandidates(
  add: AddCandidate,
  values: ParallelogramMeasurements,
) {
  if (values.side === null || values.area === null) return;
  const side = values.side;

  for (const diagonalKey of ["diagonalA", "diagonalB"] as const) {
    const diagonal = values[diagonalKey];
    if (diagonal === null) continue;
    const difference = diagonal ** 2 - side ** 2;
    const roots = positiveQuadraticRoots(
      -2 * values.area ** 2 * (diagonal ** 2 + side ** 2) /
        (difference ** 2 + 4 * values.area ** 2),
      values.area ** 4 / (difference ** 2 + 4 * values.area ** 2),
    );

    for (const heightSquared of roots) {
      const height = Math.sqrt(heightSquared);
      if (height > side) continue;
      const signedOffset =
        diagonalKey === "diagonalA"
          ? (difference * heightSquared - values.area ** 2) /
            (2 * values.area * height)
          : (values.area ** 2 - difference * heightSquared) /
            (2 * values.area * height);
      add(values.area / height, side, Math.atan2(height, signedOffset), [
        "side",
        "area",
        diagonalKey,
      ]);
    }
  }
}

function addPerimeterHeightDiagonalCandidates(
  add: AddCandidate,
  values: ParallelogramMeasurements,
) {
  if (values.perimeter === null || values.height === null) return;
  const sum = values.perimeter / 2;
  const height = values.height;

  for (const diagonalKey of ["diagonalA", "diagonalB"] as const) {
    const diagonal = values[diagonalKey];
    if (diagonal === null) continue;
    const span = nonNegativeSqrt(diagonal ** 2 - height ** 2);
    if (span === null) continue;

    for (const projectedGap of uniqueNumbers([sum + span, sum - span])) {
      if (!isPositive(projectedGap)) continue;
      const side = (projectedGap + height ** 2 / projectedGap) / 2;
      const offset =
        diagonalKey === "diagonalA"
          ? (height ** 2 / projectedGap - projectedGap) / 2
          : (projectedGap - height ** 2 / projectedGap) / 2;
      add(sum - side, side, Math.atan2(height, offset), [
        "perimeter",
        "height",
        diagonalKey,
      ]);
    }
  }
}

function addTwoDiagonalCandidates(
  add: AddCandidate,
  values: ParallelogramMeasurements,
) {
  if (values.diagonalA === null || values.diagonalB === null) return;
  const squareSum = (values.diagonalA ** 2 + values.diagonalB ** 2) / 2;
  const cosineProduct = (values.diagonalA ** 2 - values.diagonalB ** 2) / 4;

  const addFromProduct = (
    product: number,
    angle: number,
    source: ParallelogramMeasureKey[],
  ) => {
    for (const [base, side] of sidePairsFromSquareSumProduct(squareSum, product)) {
      add(base, side, angle, source);
    }
  };

  if (values.base !== null) {
    const side = nonNegativeSqrt(squareSum - values.base ** 2);
    if (side !== null && side > 0) {
      const angle = angleFromCosine(cosineProduct / (values.base * side));
      if (angle !== null) add(values.base, side, angle, ["base", "diagonalA", "diagonalB"]);
    }
  }
  if (values.side !== null) {
    const base = nonNegativeSqrt(squareSum - values.side ** 2);
    if (base !== null && base > 0) {
      const angle = angleFromCosine(cosineProduct / (base * values.side));
      if (angle !== null) add(base, values.side, angle, ["side", "diagonalA", "diagonalB"]);
    }
  }
  if (values.perimeter !== null) {
    const sum = values.perimeter / 2;
    const product = (sum ** 2 - squareSum) / 2;
    const angle = angleFromCosine(cosineProduct / product);
    if (angle !== null) addFromProduct(product, angle, ["perimeter", "diagonalA", "diagonalB"]);
  }
  if (values.area !== null) {
    const product = Math.hypot(values.area, cosineProduct);
    addFromProduct(product, Math.atan2(values.area, cosineProduct), ["area", "diagonalA", "diagonalB"]);
  }
  if (values.angle !== null) {
    const angle = degreesToRadians(values.angle);
    const cosine = Math.cos(angle);
    if (Math.abs(cosine) > ANGLE_TOLERANCE) {
      addFromProduct(cosineProduct / cosine, angle, ["angle", "diagonalA", "diagonalB"]);
    }
  }
  if (values.height !== null) {
    const reducedSquareSum = squareSum - values.height ** 2;
    for (const baseSquared of positiveQuadraticRoots(
      -reducedSquareSum,
      cosineProduct ** 2,
    )) {
      const base = Math.sqrt(baseSquared);
      const offset = cosineProduct / base;
      const side = Math.hypot(offset, values.height);
      add(base, side, Math.atan2(values.height, offset), [
        "height",
        "diagonalA",
        "diagonalB",
      ]);
    }
  }
}

function deriveParallelogram(
  base: number,
  side: number,
  angle: number,
  source: ParallelogramMeasureKey[],
): SolvedParallelogram | null {
  if (!isPositive(base) || !isPositive(side) || !isValidAngle(angle)) return null;
  const sine = Math.sin(angle);
  const cosine = Math.cos(angle);
  const height = side * sine;
  if (!isPositive(height)) return null;

  return {
    base,
    side,
    height,
    area: base * height,
    perimeter: 2 * (base + side),
    angle: radiansToDegrees(angle),
    diagonalA: Math.sqrt(base ** 2 + side ** 2 + 2 * base * side * cosine),
    diagonalB: Math.sqrt(base ** 2 + side ** 2 - 2 * base * side * cosine),
    offset: side * cosine,
    source,
  };
}

function sidePairsFromSumProduct(sum: number, product: number): Array<[number, number]> {
  if (!isPositive(sum) || !isPositive(product)) return [];
  const roots = positiveQuadraticRoots(-sum, product);
  if (roots.length < 2) return roots.length === 1 ? [[roots[0], roots[0]]] : [];
  return approximatelyEqual(roots[0], roots[1])
    ? [[roots[0], roots[0]]]
    : [[roots[0], roots[1]], [roots[1], roots[0]]];
}

function sidePairsFromSquareSumProduct(
  squareSum: number,
  product: number,
): Array<[number, number]> {
  const sum = nonNegativeSqrt(squareSum + 2 * product);
  return sum === null ? [] : sidePairsFromSumProduct(sum, product);
}

function positiveQuadraticRoots(linear: number, constant: number): number[] {
  const root = nonNegativeSqrt(linear ** 2 - 4 * constant);
  if (root === null) return [];
  return uniqueNumbers([(-linear + root) / 2, (-linear - root) / 2]).filter(isPositive);
}

function anglesFromSine(sine: number): number[] {
  if (!Number.isFinite(sine) || sine <= 0 || sine > 1 + COMPARISON_TOLERANCE) return [];
  const first = Math.asin(Math.min(sine, 1));
  return uniqueNumbers([first, Math.PI - first]).filter(isValidAngle);
}

function angleFromCosine(cosine: number): number | null {
  if (!Number.isFinite(cosine) || Math.abs(cosine) > 1 + COMPARISON_TOLERANCE) return null;
  const angle = Math.acos(Math.max(-1, Math.min(1, cosine)));
  return isValidAngle(angle) ? angle : null;
}

function mismatchKeys(
  candidate: SolvedParallelogram,
  values: ParallelogramMeasurements,
  knownKeys: ParallelogramMeasureKey[],
): ParallelogramMeasureKey[] {
  return knownKeys.filter((key) => {
    const knownValue = values[key];
    return knownValue !== null && !approximatelyEqual(candidate[key], knownValue);
  });
}

function deduplicateCandidates(
  candidates: SolvedParallelogram[],
): SolvedParallelogram[] {
  return candidates.filter(
    (candidate, index) =>
      candidates.findIndex((other) => {
        if (
          !approximatelyEqual(other.base, candidate.base) ||
          !approximatelyEqual(other.side, candidate.side)
        ) return false;
        return approximatelyEqual(other.angle, candidate.angle);
      }) === index,
  );
}

function uniqueNumbers(values: number[]): number[] {
  return values.filter(
    (value, index) =>
      Number.isFinite(value) &&
      values.findIndex((other) => approximatelyEqual(other, value)) === index,
  );
}

function nonNegativeSqrt(value: number): number | null {
  const tolerance = COMPARISON_TOLERANCE * Math.max(Math.abs(value), 1);
  if (!Number.isFinite(value) || value < -tolerance) return null;
  return Math.sqrt(Math.max(value, 0));
}

function approximatelyEqual(left: number, right: number): boolean {
  return Math.abs(left - right) <= COMPARISON_TOLERANCE * Math.max(Math.abs(left), Math.abs(right), 1);
}

function isPositive(value: number): boolean {
  return Number.isFinite(value) && value > 0;
}

function isValidAngle(angle: number): boolean {
  return Number.isFinite(angle) && angle > ANGLE_TOLERANCE && angle < Math.PI - ANGLE_TOLERANCE;
}

function degreesToRadians(value: number): number {
  return (value * Math.PI) / 180;
}

function radiansToDegrees(value: number): number {
  return (value * 180) / Math.PI;
}
