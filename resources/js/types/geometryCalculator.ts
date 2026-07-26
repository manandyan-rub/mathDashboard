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
