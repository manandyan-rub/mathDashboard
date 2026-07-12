export type NullableNumber = number | null;
export type NumericLike = number | string | null | undefined;

export type TwoDShapeKey =
  | "rectangle"
  | "triangle"
  | "circle"
  | "trapezoid"
  | "parallelogram";

export type RectangleInputs = {
  length: NumericLike;
  width: NumericLike;
  area: NumericLike;
  perimeter: NumericLike;
  diagonal: NumericLike;
};

export type RectangleMeasureKey = keyof RectangleInputs;

export type RectangleMeasurements = Record<RectangleMeasureKey, NullableNumber>;

export type RectangleDimensions = {
  length: NullableNumber;
  width: NullableNumber;
};

export type SolvedRectangle = {
  length: number;
  width: number;
  area: number;
  perimeter: number;
  diagonal: number;
  source: [RectangleMeasureKey, RectangleMeasureKey];
};

export type RectangleSolveError =
  | "not-enough-information"
  | "impossible-measurements"
  | "solution-out-of-range";

export type RectangleSolveResult = {
  solution: SolvedRectangle | null;
  error: RectangleSolveError | null;
  mismatches: RectangleMeasureKey[];
  equivalentAssignments: boolean;
};

export type TriangleInputs = {
  sideA: NumericLike;
  sideB: NumericLike;
  sideC: NumericLike;
  angleA: NumericLike;
  angleB: NumericLike;
  angleC: NumericLike;
  base: NumericLike;
  height: NumericLike;
};

export type TriangleMeasureKey = keyof TriangleInputs;

export type TriangleMeasurements = Record<
  TriangleMeasureKey,
  NullableNumber
>;

export type TriangleSolveMethod = "SSS" | "SAS" | "ASA/AAS" | "SSA";

export type TriangleGeometry = {
  sideA: NullableNumber;
  sideB: NullableNumber;
  sideC: NullableNumber;
  angleA: NullableNumber;
  angleB: NullableNumber;
  angleC: NullableNumber;
  height: NullableNumber;
};

export type SolvedTriangle = {
  sideA: number;
  sideB: number;
  sideC: number;
  angleA: number;
  angleB: number;
  angleC: number;
  base: number;
  height: number;
  area: number;
  perimeter: number;
  semiperimeter: number;
  method: TriangleSolveMethod;
  source: TriangleMeasureKey[];
};

export type TriangleSolutionCandidate = {
  solution: SolvedTriangle;
  mismatches: TriangleMeasureKey[];
};

export type TriangleSolveError =
  | "not-enough-information"
  | "insufficient-independent-information"
  | "impossible-measurements"
  | "solution-out-of-range";

export type TriangleSolveResult = {
  candidates: TriangleSolutionCandidate[];
  error: TriangleSolveError | null;
};

export type CircleInputs = {
  radius: NumericLike;
  diameter: NumericLike;
  circumference: NumericLike;
  area: NumericLike;
};

export type CircleMeasureKey = keyof CircleInputs;

export type CircleMeasurements = Record<CircleMeasureKey, NullableNumber>;

export type CircleDimensions = {
  radius: NullableNumber;
  diameter: NullableNumber;
};

export type SolvedCircle = {
  radius: number;
  diameter: number;
  circumference: number;
  area: number;
  source: CircleMeasureKey;
};

export type CircleSolveError =
  | "not-enough-information"
  | "solution-out-of-range";

export type CircleSolveResult = {
  solution: SolvedCircle | null;
  error: CircleSolveError | null;
  mismatches: CircleMeasureKey[];
};

export type TrapezoidInputs = {
  baseA: NumericLike;
  baseB: NumericLike;
  height: NumericLike;
  sideC: NumericLike;
  sideD: NumericLike;
};

export type TrapezoidMeasureKey = keyof TrapezoidInputs;

export type TrapezoidMeasurements = Record<
  TrapezoidMeasureKey,
  NullableNumber
>;

export type TrapezoidGeometry = {
  baseA: NullableNumber;
  baseB: NullableNumber;
  height: NullableNumber;
  sideC: NullableNumber;
  sideD: NullableNumber;
  offset: NullableNumber;
};

export type SolvedTrapezoid = {
  baseA: number;
  baseB: number;
  height: number;
  sideC: number;
  sideD: number;
  offset: number;
  area: number;
  perimeter: number;
  midline: number;
  source: TrapezoidMeasureKey[];
};

export type TrapezoidSolutionCandidate = {
  solution: SolvedTrapezoid;
  mismatches: TrapezoidMeasureKey[];
};

export type TrapezoidSolveError =
  | "not-enough-information"
  | "impossible-measurements"
  | "near-parallelogram-underdetermined"
  | "solution-out-of-range";

export type TrapezoidSolveResult = {
  candidates: TrapezoidSolutionCandidate[];
  error: TrapezoidSolveError | null;
};

export type ParallelogramInputs = {
  base: NumericLike;
  side: NumericLike;
  height: NumericLike;
  area: NumericLike;
  perimeter: NumericLike;
  angle: NumericLike;
  diagonalA: NumericLike;
  diagonalB: NumericLike;
};

export type ParallelogramMeasureKey = keyof ParallelogramInputs;

export type ParallelogramMeasurements = Record<
  ParallelogramMeasureKey,
  NullableNumber
>;

export type ParallelogramGeometry = {
  base: NullableNumber;
  side: NullableNumber;
  height: NullableNumber;
  angle: NullableNumber;
};

export type SolvedParallelogram = {
  base: number;
  side: number;
  height: number;
  area: number;
  perimeter: number;
  angle: number;
  diagonalA: number;
  diagonalB: number;
  offset: number;
  source: ParallelogramMeasureKey[];
};

export type ParallelogramSolutionCandidate = {
  solution: SolvedParallelogram;
  mismatches: ParallelogramMeasureKey[];
};

export type ParallelogramSolveError =
  | "not-enough-information"
  | "insufficient-independent-information"
  | "impossible-measurements"
  | "solution-out-of-range";

export type ParallelogramSolveResult = {
  candidates: ParallelogramSolutionCandidate[];
  error: ParallelogramSolveError | null;
};

export type TwoDInputs = {
  rectangle: RectangleInputs;
  triangle: TriangleInputs;
  circle: CircleInputs;
  trapezoid: TrapezoidInputs;
  parallelogram: ParallelogramInputs;
};

export type TwoDResultItem = {
  label: string;
  value: NullableNumber;
  helper?: string;
};

export type TwoDCalculation = {
  title: string;
  formula: string;
  note?: string;
  items: TwoDResultItem[];
};

export type TwoDGeometryPreviewProps = {
  shape: TwoDShapeKey;
  inputs: TwoDInputs;
  accentColor: string;
  rectangleDimensions?: RectangleDimensions;
  triangleGeometry?: TriangleGeometry;
  circleDimensions?: CircleDimensions;
  trapezoidGeometry?: TrapezoidGeometry;
  parallelogramGeometry?: ParallelogramGeometry;
};
