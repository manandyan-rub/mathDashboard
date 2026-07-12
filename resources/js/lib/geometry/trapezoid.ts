import type {
  SolvedTrapezoid,
  TrapezoidMeasureKey,
  TrapezoidMeasurements,
  TrapezoidSolveResult,
} from "@/types/twoDGeometry";

const MEASURE_KEYS: TrapezoidMeasureKey[] = [
  "baseA",
  "baseB",
  "height",
  "sideC",
  "sideD",
];
const COMPARISON_TOLERANCE = 1e-7;
const NEAR_PARALLELOGRAM_TOLERANCE = 1e-9;

export function solveTrapezoid(
  values: TrapezoidMeasurements,
  maximumDimension = Number.POSITIVE_INFINITY,
): TrapezoidSolveResult {
  const knownKeys = MEASURE_KEYS.filter((key) => values[key] !== null);

  if (knownKeys.length < 4) {
    return emptyResult("not-enough-information");
  }

  const candidates: SolvedTrapezoid[] = [];
  let nearParallelogramUnderdetermined = false;

  if (hasValues(values, ["baseA", "baseB", "height", "sideC"])) {
    candidates.push(...solveFromBaseHeightAndLeftLeg(values));
  }

  if (hasValues(values, ["baseA", "baseB", "height", "sideD"])) {
    candidates.push(...solveFromBaseHeightAndRightLeg(values));
  }

  if (hasValues(values, ["baseA", "baseB", "sideC", "sideD"])) {
    const fourSideResult = solveFromFourSides(values);
    candidates.push(...fourSideResult.candidates);
    nearParallelogramUnderdetermined = fourSideResult.nearParallelogram;
  }

  if (hasValues(values, ["baseB", "height", "sideC", "sideD"])) {
    candidates.push(...solveMissingBaseA(values));
  }

  if (hasValues(values, ["baseA", "height", "sideC", "sideD"])) {
    candidates.push(...solveMissingBaseB(values));
  }

  const uniqueCandidates = deduplicateCandidates(candidates);

  if (!uniqueCandidates.length) {
    return emptyResult(
      nearParallelogramUnderdetermined
        ? "near-parallelogram-underdetermined"
        : "impossible-measurements",
    );
  }

  const boundedCandidates = uniqueCandidates.filter((candidate) =>
    MEASURE_KEYS.every((key) => candidate[key] <= maximumDimension),
  );

  if (!boundedCandidates.length) {
    return emptyResult("solution-out-of-range");
  }

  const ranked = boundedCandidates
    .map((candidate, index) => ({
      solution: candidate,
      index,
      mismatches: mismatchKeys(candidate, values, knownKeys),
    }))
    .sort(
      (left, right) =>
        left.mismatches.length - right.mismatches.length ||
        left.index - right.index,
    );
  const bestMismatchCount = ranked[0].mismatches.length;

  return {
    candidates: ranked
      .filter((candidate) => candidate.mismatches.length === bestMismatchCount)
      .map(({ solution, mismatches }) => ({ solution, mismatches })),
    error: null,
  };
}

function solveFromBaseHeightAndLeftLeg(
  values: TrapezoidMeasurements,
): SolvedTrapezoid[] {
  const baseA = required(values.baseA);
  const baseB = required(values.baseB);
  const height = required(values.height);
  const sideC = required(values.sideC);
  const projection = legProjection(sideC, height);

  if (projection === null) {
    return [];
  }

  return uniqueNumbers([projection, -projection]).map((offset) =>
    deriveTrapezoid(baseA, baseB, height, offset, [
      "baseA",
      "baseB",
      "height",
      "sideC",
    ]),
  );
}

function solveFromBaseHeightAndRightLeg(
  values: TrapezoidMeasurements,
): SolvedTrapezoid[] {
  const baseA = required(values.baseA);
  const baseB = required(values.baseB);
  const height = required(values.height);
  const sideD = required(values.sideD);
  const projection = legProjection(sideD, height);

  if (projection === null) {
    return [];
  }

  const delta = baseB - baseA;

  return uniqueNumbers([projection, -projection]).map((rightProjection) =>
    deriveTrapezoid(baseA, baseB, height, delta - rightProjection, [
      "baseA",
      "baseB",
      "height",
      "sideD",
    ]),
  );
}

function solveFromFourSides(values: TrapezoidMeasurements): {
  candidates: SolvedTrapezoid[];
  nearParallelogram: boolean;
} {
  const baseA = required(values.baseA);
  const baseB = required(values.baseB);
  const sideC = required(values.sideC);
  const sideD = required(values.sideD);
  const delta = baseB - baseA;
  const baseScale = Math.max(baseA, baseB, 1);

  if (
    Math.abs(delta) <= NEAR_PARALLELOGRAM_TOLERANCE * baseScale
  ) {
    return { candidates: [], nearParallelogram: true };
  }

  const offset =
    (delta + ((sideC - sideD) * (sideC + sideD)) / delta) / 2;
  const height = nonNegativeSqrt(
    (sideC - offset) * (sideC + offset),
    Math.max(sideC ** 2, offset ** 2),
  );

  if (height === null || height === 0) {
    return { candidates: [], nearParallelogram: false };
  }

  return {
    candidates: [
      deriveTrapezoid(baseA, baseB, height, offset, [
        "baseA",
        "baseB",
        "sideC",
        "sideD",
      ]),
    ],
    nearParallelogram: false,
  };
}

function solveMissingBaseA(
  values: TrapezoidMeasurements,
): SolvedTrapezoid[] {
  const baseB = required(values.baseB);
  const height = required(values.height);
  const sideC = required(values.sideC);
  const sideD = required(values.sideD);
  const leftProjection = legProjection(sideC, height);
  const rightProjection = legProjection(sideD, height);

  if (leftProjection === null || rightProjection === null) {
    return [];
  }

  return projectionPairs(leftProjection, rightProjection)
    .map(({ offset, delta }) => ({
      baseA: baseB - delta,
      offset,
    }))
    .filter(({ baseA }) => baseA > 0)
    .map(({ baseA, offset }) =>
      deriveTrapezoid(baseA, baseB, height, offset, [
        "baseB",
        "height",
        "sideC",
        "sideD",
      ]),
    );
}

function solveMissingBaseB(
  values: TrapezoidMeasurements,
): SolvedTrapezoid[] {
  const baseA = required(values.baseA);
  const height = required(values.height);
  const sideC = required(values.sideC);
  const sideD = required(values.sideD);
  const leftProjection = legProjection(sideC, height);
  const rightProjection = legProjection(sideD, height);

  if (leftProjection === null || rightProjection === null) {
    return [];
  }

  return projectionPairs(leftProjection, rightProjection)
    .map(({ offset, delta }) => ({
      baseB: baseA + delta,
      offset,
    }))
    .filter(({ baseB }) => baseB > 0)
    .map(({ baseB, offset }) =>
      deriveTrapezoid(baseA, baseB, height, offset, [
        "baseA",
        "height",
        "sideC",
        "sideD",
      ]),
    );
}

function projectionPairs(left: number, right: number) {
  const pairs: Array<{ offset: number; delta: number }> = [];

  for (const leftSign of [-1, 1]) {
    for (const rightSign of [-1, 1]) {
      const offset = leftSign * left;
      pairs.push({ offset, delta: offset + rightSign * right });
    }
  }

  return pairs;
}

function deriveTrapezoid(
  baseA: number,
  baseB: number,
  height: number,
  offset: number,
  source: TrapezoidMeasureKey[],
): SolvedTrapezoid {
  const rightProjection = baseB - baseA - offset;
  const sideC = Math.hypot(offset, height);
  const sideD = Math.hypot(rightProjection, height);
  const midline = (baseA + baseB) / 2;

  return {
    baseA,
    baseB,
    height,
    sideC,
    sideD,
    offset,
    area: midline * height,
    perimeter: baseA + baseB + sideC + sideD,
    midline,
    source,
  };
}

function legProjection(leg: number, height: number): number | null {
  return nonNegativeSqrt(
    (leg - height) * (leg + height),
    Math.max(leg ** 2, height ** 2),
  );
}

function mismatchKeys(
  candidate: SolvedTrapezoid,
  values: TrapezoidMeasurements,
  knownKeys: TrapezoidMeasureKey[],
): TrapezoidMeasureKey[] {
  return knownKeys.filter((key) => {
    const knownValue = values[key];
    return knownValue !== null && !approximatelyEqual(candidate[key], knownValue);
  });
}

function deduplicateCandidates(
  candidates: SolvedTrapezoid[],
): SolvedTrapezoid[] {
  return candidates.filter(
    (candidate, index) =>
      candidates.findIndex(
        (other) => areCongruentTrapezoids(other, candidate),
      ) === index,
  );
}

function areCongruentTrapezoids(
  left: SolvedTrapezoid,
  right: SolvedTrapezoid,
): boolean {
  if (
    !approximatelyEqual(left.baseA, right.baseA) ||
    !approximatelyEqual(left.baseB, right.baseB) ||
    !approximatelyEqual(left.height, right.height)
  ) {
    return false;
  }

  const delta = left.baseB - left.baseA;
  const sameOffset = approximatelyEqual(left.offset, right.offset);
  const reflectedOffset = approximatelyEqual(
    left.offset,
    delta - right.offset,
  );

  return sameOffset || reflectedOffset;
}

function uniqueNumbers(values: number[]): number[] {
  return values.filter(
    (value, index) =>
      values.findIndex((other) => approximatelyEqual(other, value)) === index,
  );
}

function hasValues(
  values: TrapezoidMeasurements,
  keys: TrapezoidMeasureKey[],
): boolean {
  return keys.every((key) => values[key] !== null);
}

function nonNegativeSqrt(value: number, scale: number): number | null {
  const tolerance = COMPARISON_TOLERANCE * Math.max(scale, 1);

  if (!Number.isFinite(value) || value < -tolerance) {
    return null;
  }

  return Math.sqrt(Math.max(value, 0));
}

function approximatelyEqual(left: number, right: number): boolean {
  return (
    Math.abs(left - right) <=
    COMPARISON_TOLERANCE * Math.max(Math.abs(left), Math.abs(right), 1)
  );
}

function required(value: number | null): number {
  return value as number;
}

function emptyResult(error: TrapezoidSolveResult["error"]): TrapezoidSolveResult {
  return { candidates: [], error };
}
