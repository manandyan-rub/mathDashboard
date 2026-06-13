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
};

export type TriangleInputs = {
  sideA: NumericLike;
  sideB: NumericLike;
  sideC: NumericLike;
  base: NumericLike;
  height: NumericLike;
};

export type CircleInputs = {
  radius: NumericLike;
  diameter: NumericLike;
};

export type TrapezoidInputs = {
  baseA: NumericLike;
  baseB: NumericLike;
  height: NumericLike;
  sideC: NumericLike;
  sideD: NumericLike;
};

export type ParallelogramInputs = {
  base: NumericLike;
  side: NumericLike;
  height: NumericLike;
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
