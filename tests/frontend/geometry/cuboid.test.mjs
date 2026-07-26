import assert from "node:assert/strict";
import test from "node:test";

import {
  cuboidValuesFromDimensions,
  solveCuboid,
} from "../../../storage/framework/testing/geometry/resources/js/lib/geometry/cuboid.js";
import {
  fromBaseUnit,
  toBaseUnit,
} from "../../../storage/framework/testing/geometry/resources/js/lib/geometry/units.js";

const dimensions = { length: 5, width: 6, height: 4 };
const values = cuboidValuesFromDimensions(dimensions);

function approximatelyEqual(actual, expected, tolerance = 1e-9) {
  assert.ok(
    Math.abs(actual - expected) <=
      tolerance * Math.max(Math.abs(actual), Math.abs(expected), 1),
    `Expected ${actual} to approximately equal ${expected}`,
  );
}

function assertDimensions(candidate, expected = dimensions) {
  assert.ok(candidate, "Expected a cuboid candidate");
  approximatelyEqual(candidate.length, expected.length);
  approximatelyEqual(candidate.width, expected.width);
  approximatelyEqual(candidate.height, expected.height);
}

function onlyCandidate(input) {
  const solution = solveCuboid(input);
  assert.equal(solution.candidates.length, 1);
  assert.ok(solution.selectedCandidate);
  return solution.selectedCandidate;
}

function assertPartialDimension(input, key, expected) {
  const solution = solveCuboid(input);
  const candidate = solution.partialCandidates.find(
    ({ dimensions: partial }) =>
      partial[key] !== null &&
      Math.abs(partial[key] - expected) <=
        1e-9 * Math.max(Math.abs(expected), 1),
  );
  assert.ok(candidate, `Expected partial ${key} = ${expected}`);
}

test("solves and derives every property from three dimensions", () => {
  const solution = solveCuboid(dimensions);
  const candidate = onlyCandidate(dimensions);

  assertDimensions(candidate);
  approximatelyEqual(candidate.values.volume, 120);
  approximatelyEqual(candidate.values.surfaceArea, 148);
  approximatelyEqual(candidate.values.spaceDiagonal, Math.sqrt(77));
  assert.equal(solution.conflicts.length, 0);
  assert.ok(solution.calculatedFields.includes("volume"));
  assert.deepEqual(Object.keys(dimensions).sort(), [
    "height",
    "length",
    "width",
  ]);
});

test("solves each volume plus two dimensions inverse", () => {
  assertDimensions(
    onlyCandidate({ volume: values.volume, length: 5, width: 6 }),
  );
  assertDimensions(
    onlyCandidate({ volume: values.volume, length: 5, height: 4 }),
  );
  assertDimensions(
    onlyCandidate({ volume: values.volume, width: 6, height: 4 }),
  );
});

test("solves each total surface area plus two dimensions inverse", () => {
  assertDimensions(
    onlyCandidate({ surfaceArea: values.surfaceArea, length: 5, width: 6 }),
  );
  assertDimensions(
    onlyCandidate({ surfaceArea: values.surfaceArea, length: 5, height: 4 }),
  );
  assertDimensions(
    onlyCandidate({ surfaceArea: values.surfaceArea, width: 6, height: 4 }),
  );
});

test("solves each space diagonal plus two dimensions inverse", () => {
  assertDimensions(
    onlyCandidate({
      spaceDiagonal: values.spaceDiagonal,
      length: 5,
      width: 6,
    }),
  );
  assertDimensions(
    onlyCandidate({
      spaceDiagonal: values.spaceDiagonal,
      length: 5,
      height: 4,
    }),
  );
  assertDimensions(
    onlyCandidate({
      spaceDiagonal: values.spaceDiagonal,
      width: 6,
      height: 4,
    }),
  );
});

test("solves each total edge length plus two dimensions inverse", () => {
  assertDimensions(
    onlyCandidate({
      totalEdgeLength: values.totalEdgeLength,
      length: 5,
      width: 6,
    }),
  );
  assertDimensions(
    onlyCandidate({
      totalEdgeLength: values.totalEdgeLength,
      length: 5,
      height: 4,
    }),
  );
  assertDimensions(
    onlyCandidate({
      totalEdgeLength: values.totalEdgeLength,
      width: 6,
      height: 4,
    }),
  );
});

test("derives a related dimension from every face area inverse", () => {
  assertPartialDimension({ lengthWidthArea: 30, length: 5 }, "width", 6);
  assertPartialDimension({ lengthWidthArea: 30, width: 6 }, "length", 5);
  assertPartialDimension({ lengthHeightArea: 20, length: 5 }, "height", 4);
  assertPartialDimension({ lengthHeightArea: 20, height: 4 }, "length", 5);
  assertPartialDimension({ widthHeightArea: 24, width: 6 }, "height", 4);
  assertPartialDimension({ widthHeightArea: 24, height: 4 }, "width", 6);
});

test("derives a related dimension from every face diagonal inverse", () => {
  assertPartialDimension(
    { lengthWidthDiagonal: Math.sqrt(61), length: 5 },
    "width",
    6,
  );
  assertPartialDimension(
    { lengthWidthDiagonal: Math.sqrt(61), width: 6 },
    "length",
    5,
  );
  assertPartialDimension(
    { lengthHeightDiagonal: Math.sqrt(41), length: 5 },
    "height",
    4,
  );
  assertPartialDimension(
    { lengthHeightDiagonal: Math.sqrt(41), height: 4 },
    "length",
    5,
  );
  assertPartialDimension(
    { widthHeightDiagonal: Math.sqrt(52), width: 6 },
    "height",
    4,
  );
  assertPartialDimension(
    { widthHeightDiagonal: Math.sqrt(52), height: 4 },
    "width",
    6,
  );
});

test("derives a related dimension from every face perimeter inverse", () => {
  assertPartialDimension({ lengthWidthPerimeter: 22, length: 5 }, "width", 6);
  assertPartialDimension({ lengthWidthPerimeter: 22, width: 6 }, "length", 5);
  assertPartialDimension({ lengthHeightPerimeter: 18, length: 5 }, "height", 4);
  assertPartialDimension({ lengthHeightPerimeter: 18, height: 4 }, "length", 5);
  assertPartialDimension({ widthHeightPerimeter: 20, width: 6 }, "height", 4);
  assertPartialDimension({ widthHeightPerimeter: 20, height: 4 }, "width", 6);
});

test("solves three face areas", () => {
  assertDimensions(
    onlyCandidate({
      lengthWidthArea: 30,
      lengthHeightArea: 20,
      widthHeightArea: 24,
    }),
  );
});

test("solves three face perimeters", () => {
  assertDimensions(
    onlyCandidate({
      lengthWidthPerimeter: 22,
      lengthHeightPerimeter: 18,
      widthHeightPerimeter: 20,
    }),
  );
});

test("solves three face diagonals", () => {
  assertDimensions(
    onlyCandidate({
      lengthWidthDiagonal: Math.sqrt(61),
      lengthHeightDiagonal: Math.sqrt(41),
      widthHeightDiagonal: Math.sqrt(52),
    }),
  );
});

test("uses additional consistent measurements as checks", () => {
  const solution = solveCuboid({
    ...dimensions,
    volume: 120,
    surfaceArea: 148,
    spaceDiagonal: Math.sqrt(77),
    totalEdgeLength: 60,
  });

  assertDimensions(solution.selectedCandidate);
  assert.equal(solution.conflicts.length, 0);
  assert.equal(solution.selectedCandidate.matchedFields.length, 7);
});

test("reports conflicts without overwriting entered values", () => {
  const input = { length: 4, width: 3, height: 2, volume: 30 };
  const solution = solveCuboid(input);

  assert.equal(input.volume, 30);
  assert.equal(solution.conflicts.length, 1);
  assert.equal(solution.conflicts[0].field, "volume");
  assert.equal(solution.conflicts[0].enteredValue, 30);
  assert.equal(solution.conflicts[0].expectedValue, 24);
  assert.equal(solution.isValid, false);
});

test("returns not enough information for an underdetermined system", () => {
  const solution = solveCuboid({ volume: 120 });

  assert.equal(solution.candidates.length, 0);
  assert.equal(solution.selectedCandidate, null);
  assert.ok(
    solution.warnings.includes(
      "Not enough compatible information is available to solve all three dimensions yet.",
    ),
  );
});

test("rejects impossible square roots", () => {
  const solution = solveCuboid({
    lengthWidthDiagonal: 3,
    length: 4,
  });

  assert.equal(solution.candidates.length, 0);
  assert.equal(
    solution.partialCandidates.some(
      ({ dimensions: partial }) => partial.width !== null,
    ),
    false,
  );
  assert.ok(
    solution.warnings.includes(
      "The entered measurements cannot form a valid cuboid.",
    ),
  );
});

test("marks zero, negative, non-finite, and oversized measurements invalid", () => {
  const solution = solveCuboid({
    length: 0,
    width: -2,
    height: Number.POSITIVE_INFINITY,
    volume: 1e30,
  });

  assert.deepEqual(
    [...solution.invalidFields].sort(),
    ["height", "length", "volume", "width"],
  );
  assert.equal(solution.candidates.length, 0);
});

test("accepts values within floating-point tolerance", () => {
  const solution = solveCuboid({
    ...dimensions,
    volume: 120 * (1 + 5e-10),
  });

  assert.equal(solution.conflicts.length, 0);
  assert.equal(solution.isValid, true);
});

test("deduplicates candidates reached through multiple derivations", () => {
  const solution = solveCuboid({
    ...dimensions,
    volume: 120,
    lengthWidthArea: 30,
    lengthHeightArea: 20,
    widthHeightArea: 24,
  });

  assert.equal(solution.candidates.length, 1);
});

test("keeps labeled ambiguous face roots and requires explicit selection", () => {
  const solution = solveCuboid({
    lengthWidthArea: 12,
    lengthWidthPerimeter: 14,
    height: 2,
  });

  assert.equal(solution.candidates.length, 2);
  assert.equal(solution.isAmbiguous, true);
  assert.equal(solution.selectedCandidate, null);
  assert.deepEqual(
    solution.candidates
      .map(({ length, width, height }) => [length, width, height])
      .sort(),
    [
      [3, 4, 2],
      [4, 3, 2],
    ],
  );
});

test("recalculates from changed entered values without mutating either input", () => {
  const firstInput = { volume: 1, length: 3, width: 7 };
  const secondInput = { volume: 2, length: 3, width: 7 };
  const first = onlyCandidate(firstInput);
  const second = onlyCandidate(secondInput);

  approximatelyEqual(first.height, 1 / 21, 1e-14);
  approximatelyEqual(second.height, 2 / 21, 1e-14);
  approximatelyEqual(first.values.volume, 1, 1e-14);
  approximatelyEqual(second.values.volume, 2, 1e-14);
  assert.deepEqual(firstInput, { volume: 1, length: 3, width: 7 });
  assert.deepEqual(secondInput, { volume: 2, length: 3, width: 7 });
});

test("preserves tiny positive inverse dimensions without intermediate rounding", () => {
  const width = 1e-6;
  const diagonal = Math.hypot(2, width);
  const solution = solveCuboid({
    length: 2,
    lengthWidthDiagonal: diagonal,
  });
  const partial = solution.partialCandidates.find(
    ({ dimensions: candidate }) => candidate.width !== null,
  );

  assert.ok(partial);
  approximatelyEqual(partial.dimensions.width, width, 1e-4);
});

test("converts length, area, and volume through the shared unit layer", () => {
  const input = {
    length: toBaseUnit(5, "cm", 1),
    width: toBaseUnit(6, "cm", 1),
    volume: toBaseUnit(120, "cm", 3),
  };
  const candidate = onlyCandidate(input);

  approximatelyEqual(fromBaseUnit(candidate.height, "cm", 1), 4);
  approximatelyEqual(
    fromBaseUnit(candidate.values.lengthWidthArea, "cm", 2),
    30,
  );
  approximatelyEqual(
    fromBaseUnit(candidate.values.volume, "cm", 3),
    120,
  );
});
