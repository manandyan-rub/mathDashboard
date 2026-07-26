export type LengthUnit = "mm" | "cm" | "m" | "km" | "in" | "ft";

export type GeometryDimension = 1 | 2 | 3;

export type CalculatorFieldStatus =
  | "empty"
  | "entered"
  | "calculated"
  | "invalid"
  | "conflicting";

export type CubeFieldKey =
  | "side"
  | "faceArea"
  | "surfaceArea"
  | "volume"
  | "faceDiagonal"
  | "spaceDiagonal"
  | "facePerimeter"
  | "totalEdgeLength"
  | "circumsphereRadius"
  | "insphereRadius";

export type CubeEnteredValues = Partial<
  Record<CubeFieldKey, number | null | undefined>
>;

export type CubeConflict = {
  field: CubeFieldKey;
  enteredValue: number;
  expectedValue: number;
  difference: number;
  message: string;
};

export type CubeSolution = {
  side: number | null;
  sourceField: CubeFieldKey | null;
  values: Partial<Record<CubeFieldKey, number>>;
  calculatedFields: CubeFieldKey[];
  formulas: Partial<Record<CubeFieldKey, string>>;
  conflicts: CubeConflict[];
  warnings: string[];
  invalidFields: CubeFieldKey[];
  isValid: boolean;
};

export type CubeFieldDefinition = {
  key: CubeFieldKey;
  label: string;
  symbol: string;
  dimension: GeometryDimension;
  maximum: number;
  inverseFormula: string;
  forwardFormula: string;
};

export type CubeCalculationStep = {
  field: CubeFieldKey;
  label: string;
  formula: string;
  substitution: string;
  result: number;
  dimension: GeometryDimension;
};

export type CuboidDimensionKey = "length" | "width" | "height";

export type CuboidFieldKey =
  | CuboidDimensionKey
  | "volume"
  | "surfaceArea"
  | "lateralSurface"
  | "spaceDiagonal"
  | "circumsphereRadius"
  | "lengthWidthArea"
  | "lengthHeightArea"
  | "widthHeightArea"
  | "lengthWidthDiagonal"
  | "lengthHeightDiagonal"
  | "widthHeightDiagonal"
  | "lengthWidthPerimeter"
  | "lengthHeightPerimeter"
  | "widthHeightPerimeter"
  | "totalEdgeLength";

export type CuboidEnteredValues = Partial<
  Record<CuboidFieldKey, number | null | undefined>
>;

export type CuboidDimensions = Record<CuboidDimensionKey, number>;

export type PartialCuboidDimensions = Record<
  CuboidDimensionKey,
  number | null
>;

export type CuboidConflict = {
  field: CuboidFieldKey;
  enteredValue: number;
  expectedValue: number;
  absoluteDifference: number;
  relativeDifference: number;
  message: string;
};

export type CuboidDerivation = {
  id: string;
  target: CuboidDimensionKey;
  label: string;
  formula: string;
  sourceFields: CuboidFieldKey[];
  sourceValues: Partial<Record<CuboidFieldKey, number>>;
  result: number;
};

export type CuboidCandidate = CuboidDimensions & {
  values: Record<CuboidFieldKey, number>;
  formulasUsed: string[];
  derivations: CuboidDerivation[];
  conflicts: CuboidConflict[];
  matchedFields: CuboidFieldKey[];
  totalError: number;
  assumptions: number;
};

export type CuboidPartialCandidate = {
  dimensions: PartialCuboidDimensions;
  values: Partial<Record<CuboidFieldKey, number>>;
  derivations: CuboidDerivation[];
  conflicts: CuboidConflict[];
  matchedFields: CuboidFieldKey[];
  totalError: number;
  assumptions: number;
};

export type CuboidSolution = {
  candidates: CuboidCandidate[];
  partialCandidates: CuboidPartialCandidate[];
  selectedCandidate: CuboidCandidate | null;
  values: Partial<Record<CuboidFieldKey, number>>;
  calculatedFields: CuboidFieldKey[];
  conflicts: CuboidConflict[];
  warnings: string[];
  invalidFields: CuboidFieldKey[];
  isValid: boolean;
  isAmbiguous: boolean;
};

export type CuboidFieldDefinition = {
  key: CuboidFieldKey;
  label: string;
  symbol: string;
  dimension: GeometryDimension;
  maximum: number;
  forwardFormula: string;
  group: "dimensions" | "core" | "face-areas" | "face-diagonals" | "face-perimeters";
  editable: boolean;
};

export type CuboidCalculationStep = {
  field: CuboidFieldKey;
  label: string;
  formula: string;
  substitution: string;
  result: number;
  dimension: GeometryDimension;
};
