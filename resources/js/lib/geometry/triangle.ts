import type {
  SolvedTriangle,
  TriangleMeasureKey,
  TriangleMeasurements,
  TriangleSolveMethod,
  TriangleSolveResult,
} from "@/types/twoDGeometry";

const MEASURE_KEYS: TriangleMeasureKey[] = [
  "sideA",
  "sideB",
  "sideC",
  "angleA",
  "angleB",
  "angleC",
  "base",
  "height",
];
const SIDE_KEYS = ["sideA", "sideB", "sideC"] as const;
const ANGLE_KEYS = ["angleA", "angleB", "angleC"] as const;
const RELATIVE_TOLERANCE = 1e-7;
const GEOMETRY_TOLERANCE = 1e-12;
const ANGLE_TOLERANCE = 1e-10;

type SideValues = [number | null, number | null, number | null];
type AngleValues = [number | null, number | null, number | null];

export function solveTriangle(
  values: TriangleMeasurements,
  maximumSide = Number.POSITIVE_INFINITY,
): TriangleSolveResult {
  const sideCSource: TriangleMeasureKey =
    values.sideC !== null ? "sideC" : "base";
  const sides: SideValues = [
    values.sideA,
    values.sideB,
    values.sideC ?? values.base,
  ];
  const angles: AngleValues = [
    toRadians(values.angleA),
    toRadians(values.angleB),
    toRadians(values.angleC),
  ];
  const sideSources: [TriangleMeasureKey, TriangleMeasureKey, TriangleMeasureKey] = [
    "sideA",
    "sideB",
    sideCSource,
  ];
  const candidates: SolvedTriangle[] = [];

  addSssCandidates(candidates, sides, sideSources);
  addSasCandidates(candidates, sides, angles, sideSources);
  addAngleSideCandidates(candidates, sides, angles, sideSources);
  addSsaCandidates(candidates, sides, angles, sideSources);

  const knownKeys = MEASURE_KEYS.filter((key) => values[key] !== null);
  const uniqueCandidates = deduplicateCandidates(candidates);

  if (!uniqueCandidates.length) {
    const error = hasSufficientPattern(sides, angles)
      ? "impossible-measurements"
      : independentMeasurementCount(sides, angles) < 3
        ? "not-enough-information"
        : "insufficient-independent-information";

    return { candidates: [], error };
  }

  const boundedCandidates = uniqueCandidates.filter(
    (candidate) =>
      candidate.sideA <= maximumSide &&
      candidate.sideB <= maximumSide &&
      candidate.sideC <= maximumSide &&
      candidate.height <= maximumSide,
  );

  if (!boundedCandidates.length) {
    return { candidates: [], error: "solution-out-of-range" };
  }

  const ranked = boundedCandidates
    .map((solution, index) => ({
      solution,
      index,
      mismatches: mismatchKeys(solution, values, knownKeys),
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

function addSssCandidates(
  candidates: SolvedTriangle[],
  sides: SideValues,
  sideSources: [TriangleMeasureKey, TriangleMeasureKey, TriangleMeasureKey],
) {
  if (sides.every(isPositive)) {
    addCandidate(candidates, sides as [number, number, number], "SSS", [
      ...sideSources,
    ]);
  }
}

function addSasCandidates(
  candidates: SolvedTriangle[],
  sides: SideValues,
  angles: AngleValues,
  sideSources: [TriangleMeasureKey, TriangleMeasureKey, TriangleMeasureKey],
) {
  const pairs: Array<[number, number, number]> = [
    [0, 1, 2],
    [0, 2, 1],
    [1, 2, 0],
  ];

  for (const [firstSide, secondSide, includedAngle] of pairs) {
    const first = sides[firstSide];
    const second = sides[secondSide];
    const angle = angles[includedAngle];
    if (!isPositive(first) || !isPositive(second) || !isValidAngle(angle)) continue;

    const missingSquared =
      (first - second) ** 2 +
      4 * first * second * Math.sin(angle / 2) ** 2;
    const missing = tolerantSqrt(
      missingSquared,
      Math.max(first ** 2, second ** 2, 1),
    );
    if (!isPositive(missing)) continue;

    const solvedSides: [number, number, number] = [0, 0, 0];
    solvedSides[firstSide] = first;
    solvedSides[secondSide] = second;
    solvedSides[includedAngle] = missing;
    addCandidate(candidates, solvedSides, "SAS", [
      sideSources[firstSide],
      sideSources[secondSide],
      ANGLE_KEYS[includedAngle],
    ]);
  }
}

function addAngleSideCandidates(
  candidates: SolvedTriangle[],
  sides: SideValues,
  angles: AngleValues,
  sideSources: [TriangleMeasureKey, TriangleMeasureKey, TriangleMeasureKey],
) {
  const knownAngles = angles
    .map((value, index) => ({ value, index }))
    .filter((entry): entry is { value: number; index: number } =>
      isValidAngle(entry.value),
    );

  if (knownAngles.length < 2) return;

  for (let firstIndex = 0; firstIndex < knownAngles.length - 1; firstIndex += 1) {
    for (let secondIndex = firstIndex + 1; secondIndex < knownAngles.length; secondIndex += 1) {
      const first = knownAngles[firstIndex];
      const second = knownAngles[secondIndex];
      const missingIndex = 3 - first.index - second.index;
      const missingAngle = Math.PI - first.value - second.value;
      if (!isValidAngle(missingAngle)) continue;

      const solvedAngles: [number, number, number] = [0, 0, 0];
      solvedAngles[first.index] = first.value;
      solvedAngles[second.index] = second.value;
      solvedAngles[missingIndex] = missingAngle;

      for (let sideIndex = 0; sideIndex < sides.length; sideIndex += 1) {
        const knownSide = sides[sideIndex];
        if (!isPositive(knownSide)) continue;

        const sine = Math.sin(solvedAngles[sideIndex]);
        if (sine <= ANGLE_TOLERANCE) continue;
        const scale = knownSide / sine;
        const solvedSides = solvedAngles.map((angle) => scale * Math.sin(angle)) as [
          number,
          number,
          number,
        ];
        addCandidate(candidates, solvedSides, "ASA/AAS", [
          ANGLE_KEYS[first.index],
          ANGLE_KEYS[second.index],
          sideSources[sideIndex],
        ]);
      }
    }
  }
}

function addSsaCandidates(
  candidates: SolvedTriangle[],
  sides: SideValues,
  angles: AngleValues,
  sideSources: [TriangleMeasureKey, TriangleMeasureKey, TriangleMeasureKey],
) {
  for (let knownIndex = 0; knownIndex < 3; knownIndex += 1) {
    const oppositeSide = sides[knownIndex];
    const knownAngle = angles[knownIndex];
    if (!isPositive(oppositeSide) || !isValidAngle(knownAngle)) continue;

    for (let otherIndex = 0; otherIndex < 3; otherIndex += 1) {
      if (otherIndex === knownIndex) continue;
      const otherSide = sides[otherIndex];
      if (!isPositive(otherSide)) continue;

      const sineValue = (otherSide * Math.sin(knownAngle)) / oppositeSide;
      for (const otherAngle of anglesFromSine(sineValue)) {
        const thirdIndex = 3 - knownIndex - otherIndex;
        const thirdAngle = Math.PI - knownAngle - otherAngle;
        if (!isValidAngle(thirdAngle)) continue;

        const thirdSide =
          (oppositeSide * Math.sin(thirdAngle)) / Math.sin(knownAngle);
        if (!isPositive(thirdSide)) continue;

        const solvedSides: [number, number, number] = [0, 0, 0];
        solvedSides[knownIndex] = oppositeSide;
        solvedSides[otherIndex] = otherSide;
        solvedSides[thirdIndex] = thirdSide;
        addCandidate(candidates, solvedSides, "SSA", [
          sideSources[knownIndex],
          ANGLE_KEYS[knownIndex],
          sideSources[otherIndex],
        ]);
      }
    }
  }
}

function addCandidate(
  candidates: SolvedTriangle[],
  sides: [number, number, number],
  method: TriangleSolveMethod,
  source: TriangleMeasureKey[],
) {
  const candidate = deriveTriangle(sides, method, source);
  if (candidate) candidates.push(candidate);
}

function deriveTriangle(
  [sideA, sideB, sideC]: [number, number, number],
  method: TriangleSolveMethod,
  source: TriangleMeasureKey[],
): SolvedTriangle | null {
  if (
    !isPositive(sideA) ||
    !isPositive(sideB) ||
    !isPositive(sideC) ||
    !hasStrictTriangleInequality(sideA, sideB, sideC)
  ) return null;

  const angleA = angleFromSides(sideA, sideB, sideC);
  const angleB = angleFromSides(sideB, sideA, sideC);
  if (angleA === null || angleB === null) return null;
  const angleC = Math.PI - angleA - angleB;
  if (!isValidAngle(angleC)) return null;

  const semiperimeter = (sideA + sideB + sideC) / 2;
  const [largest, middle, smallest] = [sideA, sideB, sideC].sort(
    (left, right) => right - left,
  );
  const areaProduct =
    (largest + (middle + smallest)) *
    (smallest - (largest - middle)) *
    (smallest + (largest - middle)) *
    (largest + (middle - smallest));
  const areaRoot = tolerantSqrt(
    areaProduct,
    Math.max(semiperimeter ** 4, 1),
  );
  const area = areaRoot === null ? null : areaRoot / 4;
  if (!isPositive(area)) return null;

  return {
    sideA,
    sideB,
    sideC,
    angleA: toDegrees(angleA),
    angleB: toDegrees(angleB),
    angleC: toDegrees(angleC),
    base: sideC,
    height: (2 * area) / sideC,
    area,
    perimeter: sideA + sideB + sideC,
    semiperimeter,
    method,
    source: uniqueKeys(source),
  };
}

function hasSufficientPattern(sides: SideValues, angles: AngleValues): boolean {
  const sideCount = sides.filter(isPositive).length;
  const angleCount = angles.filter(isValidAngle).length;
  if (sideCount === 3) return true;
  if (angleCount >= 2 && sideCount >= 1) return true;
  return sideCount >= 2 && angleCount >= 1;
}

function independentMeasurementCount(sides: SideValues, angles: AngleValues): number {
  return sides.filter(isPositive).length + angles.filter(isValidAngle).length;
}

function mismatchKeys(
  candidate: SolvedTriangle,
  values: TriangleMeasurements,
  knownKeys: TriangleMeasureKey[],
): TriangleMeasureKey[] {
  return knownKeys.filter((key) => {
    const known = values[key];
    return known !== null && !approximatelyEqual(candidate[key], known);
  });
}

function deduplicateCandidates(candidates: SolvedTriangle[]): SolvedTriangle[] {
  return candidates.filter(
    (candidate, index) =>
      candidates.findIndex(
        (other) =>
          approximatelyEqual(other.sideA, candidate.sideA) &&
          approximatelyEqual(other.sideB, candidate.sideB) &&
          approximatelyEqual(other.sideC, candidate.sideC),
      ) === index,
  );
}

function angleFromSides(opposite: number, adjacentA: number, adjacentB: number) {
  const cosine =
    (adjacentA ** 2 + adjacentB ** 2 - opposite ** 2) /
    (2 * adjacentA * adjacentB);
  if (!Number.isFinite(cosine) || Math.abs(cosine) > 1 + RELATIVE_TOLERANCE) {
    return null;
  }
  const angle = Math.acos(Math.max(-1, Math.min(1, cosine)));
  return isValidAngle(angle) ? angle : null;
}

function anglesFromSine(value: number): number[] {
  if (
    !Number.isFinite(value) ||
    value <= 0 ||
    value > 1 + RELATIVE_TOLERANCE
  ) return [];
  const first = Math.asin(Math.min(1, value));
  return uniqueNumbers([first, Math.PI - first]).filter(isValidAngle);
}

function hasStrictTriangleInequality(a: number, b: number, c: number) {
  const scale = Math.max(a, b, c, 1);
  const tolerance = GEOMETRY_TOLERANCE * scale;
  return a + b > c + tolerance && a + c > b + tolerance && b + c > a + tolerance;
}

function tolerantSqrt(value: number, scale: number): number | null {
  const tolerance = GEOMETRY_TOLERANCE * Math.max(scale, 1);
  if (!Number.isFinite(value) || value < -tolerance) return null;
  return Math.sqrt(Math.max(value, 0));
}

function uniqueKeys(values: TriangleMeasureKey[]): TriangleMeasureKey[] {
  return values.filter((value, index) => values.indexOf(value) === index);
}

function uniqueNumbers(values: number[]): number[] {
  return values.filter(
    (value, index) =>
      Number.isFinite(value) &&
      values.findIndex((other) => approximatelyEqual(other, value)) === index,
  );
}

function approximatelyEqual(left: number, right: number): boolean {
  return (
    Math.abs(left - right) <=
    RELATIVE_TOLERANCE * Math.max(Math.abs(left), Math.abs(right), 1)
  );
}

function isPositive(value: number | null): value is number {
  return value !== null && Number.isFinite(value) && value > 0;
}

function isValidAngle(value: number | null): value is number {
  return (
    value !== null &&
    Number.isFinite(value) &&
    value > ANGLE_TOLERANCE &&
    value < Math.PI - ANGLE_TOLERANCE
  );
}

function toRadians(value: number | null): number | null {
  return value === null ? null : (value * Math.PI) / 180;
}

function toDegrees(value: number): number {
  return (value * 180) / Math.PI;
}
