import type {
  RectangleMeasureKey,
  RectangleMeasurements,
  RectangleSolveResult,
  SolvedRectangle,
} from "@/types/twoDGeometry";

const MEASURE_KEYS: RectangleMeasureKey[] = [
  "length",
  "width",
  "area",
  "perimeter",
  "diagonal",
];
const RELATIVE_TOLERANCE = 1e-7;

type RectangleCandidate = SolvedRectangle;

export function solveRectangle(
  values: RectangleMeasurements,
  maximumSide = Number.POSITIVE_INFINITY,
): RectangleSolveResult {
  const knownKeys = MEASURE_KEYS.filter((key) => values[key] !== null);

  if (knownKeys.length < 2) {
    return emptyResult("not-enough-information");
  }

  const candidates: RectangleCandidate[] = [];

  for (let firstIndex = 0; firstIndex < knownKeys.length - 1; firstIndex += 1) {
    for (
      let secondIndex = firstIndex + 1;
      secondIndex < knownKeys.length;
      secondIndex += 1
    ) {
      const first = knownKeys[firstIndex];
      const second = knownKeys[secondIndex];
      candidates.push(...solvePair(first, second, values));
    }
  }

  const uniqueCandidates = deduplicateCandidates(candidates);

  if (!uniqueCandidates.length) {
    return emptyResult("impossible-measurements");
  }

  const boundedCandidates = uniqueCandidates.filter(
    (candidateValue) =>
      candidateValue.length <= maximumSide &&
      candidateValue.width <= maximumSide,
  );

  if (!boundedCandidates.length) {
    return emptyResult("solution-out-of-range");
  }

  const ranked = boundedCandidates
    .map((candidate, index) => ({
      candidate,
      index,
      mismatches: mismatchKeys(candidate, values, knownKeys),
    }))
    .sort(
      (left, right) =>
        left.mismatches.length - right.mismatches.length ||
        left.index - right.index,
    );

  let selected = ranked[0].candidate;
  const swapped = deriveRectangle(
    selected.width,
    selected.length,
    selected.source,
  );
  const swappedMismatches = mismatchKeys(swapped, values, knownKeys);
  const equivalentAssignments =
    !approximatelyEqual(selected.length, selected.width) &&
    swappedMismatches.length === ranked[0].mismatches.length;

  if (equivalentAssignments && selected.length < selected.width) {
    selected = swapped;
  }

  return {
    solution: selected,
    error: null,
    mismatches: mismatchKeys(selected, values, knownKeys),
    equivalentAssignments,
  };
}

function solvePair(
  first: RectangleMeasureKey,
  second: RectangleMeasureKey,
  values: RectangleMeasurements,
): RectangleCandidate[] {
  const source: [RectangleMeasureKey, RectangleMeasureKey] = [first, second];
  const firstValue = values[first];
  const secondValue = values[second];

  if (firstValue === null || secondValue === null) {
    return [];
  }

  if (first === "length" && second === "width") {
    return candidate(firstValue, secondValue, source);
  }

  if (first === "length") {
    return solveFromKnownSide(firstValue, second, secondValue, source, false);
  }

  if (first === "width") {
    return solveFromKnownSide(firstValue, second, secondValue, source, true);
  }

  if (first === "area" && second === "perimeter") {
    return solveFromSumAndProduct(secondValue / 2, firstValue, source);
  }

  if (first === "area" && second === "diagonal") {
    const sideSum = Math.sqrt(secondValue ** 2 + 2 * firstValue);
    return solveFromSumAndProduct(sideSum, firstValue, source);
  }

  if (first === "perimeter" && second === "diagonal") {
    const sideSum = firstValue / 2;
    const area = ((sideSum - secondValue) * (sideSum + secondValue)) / 2;
    return solveFromSumAndProduct(sideSum, area, source);
  }

  return [];
}

function solveFromKnownSide(
  knownSide: number,
  otherKey: RectangleMeasureKey,
  otherValue: number,
  source: [RectangleMeasureKey, RectangleMeasureKey],
  knownIsWidth: boolean,
): RectangleCandidate[] {
  let otherSide: number | null = null;

  if (otherKey === "area") {
    otherSide = otherValue / knownSide;
  } else if (otherKey === "perimeter") {
    otherSide = otherValue / 2 - knownSide;
  } else if (otherKey === "diagonal") {
    otherSide = nonNegativeSqrt(
      otherValue ** 2 - knownSide ** 2,
      Math.max(otherValue ** 2, knownSide ** 2),
    );
  }

  return knownIsWidth
    ? candidate(otherSide, knownSide, source)
    : candidate(knownSide, otherSide, source);
}

function solveFromSumAndProduct(
  sum: number,
  product: number,
  source: [RectangleMeasureKey, RectangleMeasureKey],
): RectangleCandidate[] {
  if (!isPositiveFinite(sum) || !isPositiveFinite(product)) {
    return [];
  }

  const discriminant = sum ** 2 - 4 * product;
  const root = nonNegativeSqrt(discriminant, Math.max(sum ** 2, 4 * product));

  if (root === null) {
    return [];
  }

  const larger = (sum + root) / 2;
  const smaller = product / larger;
  const first = candidate(larger, smaller, source);

  if (approximatelyEqual(larger, smaller)) {
    return first;
  }

  return [...first, ...candidate(smaller, larger, source)];
}

function candidate(
  length: number | null,
  width: number | null,
  source: [RectangleMeasureKey, RectangleMeasureKey],
): RectangleCandidate[] {
  if (!isPositiveFinite(length) || !isPositiveFinite(width)) {
    return [];
  }

  return [deriveRectangle(length, width, source)];
}

function deriveRectangle(
  length: number,
  width: number,
  source: [RectangleMeasureKey, RectangleMeasureKey],
): RectangleCandidate {
  return {
    length,
    width,
    area: length * width,
    perimeter: 2 * (length + width),
    diagonal: Math.hypot(length, width),
    source,
  };
}

function mismatchKeys(
  candidate: RectangleCandidate,
  values: RectangleMeasurements,
  knownKeys: RectangleMeasureKey[],
): RectangleMeasureKey[] {
  return knownKeys.filter((key) => {
    const knownValue = values[key];
    return knownValue !== null && !approximatelyEqual(candidate[key], knownValue);
  });
}

function deduplicateCandidates(
  candidates: RectangleCandidate[],
): RectangleCandidate[] {
  return candidates.filter(
    (candidateValue, index) =>
      candidates.findIndex(
        (other) =>
          approximatelyEqual(other.length, candidateValue.length) &&
          approximatelyEqual(other.width, candidateValue.width),
      ) === index,
  );
}

function nonNegativeSqrt(value: number, scale: number): number | null {
  const tolerance = RELATIVE_TOLERANCE * Math.max(scale, 1);

  if (!Number.isFinite(value) || value < -tolerance) {
    return null;
  }

  return Math.sqrt(Math.max(value, 0));
}

function approximatelyEqual(left: number, right: number): boolean {
  return (
    Math.abs(left - right) <=
    RELATIVE_TOLERANCE * Math.max(Math.abs(left), Math.abs(right), 1)
  );
}

function isPositiveFinite(value: number | null): value is number {
  return value !== null && Number.isFinite(value) && value > 0;
}

function emptyResult(error: RectangleSolveResult["error"]): RectangleSolveResult {
  return {
    solution: null,
    error,
    mismatches: [],
    equivalentAssignments: false,
  };
}
