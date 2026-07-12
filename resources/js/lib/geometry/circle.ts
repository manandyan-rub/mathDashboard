import type {
  CircleMeasureKey,
  CircleMeasurements,
  CircleSolveResult,
  SolvedCircle,
} from "@/types/twoDGeometry";

const MEASURE_KEYS: CircleMeasureKey[] = [
  "radius",
  "diameter",
  "circumference",
  "area",
];
const RELATIVE_TOLERANCE = 1e-7;

export function solveCircle(
  values: CircleMeasurements,
  maximumRadius = Number.POSITIVE_INFINITY,
): CircleSolveResult {
  const knownKeys = MEASURE_KEYS.filter((key) => values[key] !== null);

  if (!knownKeys.length) {
    return emptyResult("not-enough-information");
  }

  const candidates = knownKeys
    .map((key) => circleFromMeasurement(key, values[key]))
    .filter((candidate): candidate is SolvedCircle => candidate !== null);
  const boundedCandidates = candidates.filter(
    (candidate) => candidate.radius <= maximumRadius,
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
  const selected = ranked[0].candidate;

  return {
    solution: selected,
    error: null,
    mismatches: mismatchKeys(selected, values, knownKeys),
  };
}

function circleFromMeasurement(
  key: CircleMeasureKey,
  value: number | null,
): SolvedCircle | null {
  if (value === null || !Number.isFinite(value) || value <= 0) {
    return null;
  }

  const radius =
    key === "radius"
      ? value
      : key === "diameter"
        ? value / 2
        : key === "circumference"
          ? value / (2 * Math.PI)
          : Math.sqrt(value / Math.PI);

  if (!Number.isFinite(radius) || radius <= 0) {
    return null;
  }

  return {
    radius,
    diameter: 2 * radius,
    circumference: 2 * Math.PI * radius,
    area: Math.PI * radius ** 2,
    source: key,
  };
}

function mismatchKeys(
  candidate: SolvedCircle,
  values: CircleMeasurements,
  knownKeys: CircleMeasureKey[],
): CircleMeasureKey[] {
  return knownKeys.filter((key) => {
    const knownValue = values[key];
    return knownValue !== null && !approximatelyEqual(candidate[key], knownValue);
  });
}

function approximatelyEqual(left: number, right: number): boolean {
  return (
    Math.abs(left - right) <=
    RELATIVE_TOLERANCE * Math.max(Math.abs(left), Math.abs(right), 1)
  );
}

function emptyResult(error: CircleSolveResult["error"]): CircleSolveResult {
  return {
    solution: null,
    error,
    mismatches: [],
  };
}
