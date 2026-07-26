import type { LengthUnit } from "@/types/geometryCalculator";
import type { TwoDShapeKey } from "@/types/twoDGeometry";

export type WorkspaceTopicKey =
  | "rectangle"
  | "circle"
  | "triangle"
  | "trapezoid"
  | "parallelogram"
  | "cube";

export type WorkspaceValueSource = "entered" | "calculated";
export type WorkspaceDimension = 0 | 1 | 2 | 3;

export type WorkspaceFieldDefinition = {
  key: string;
  label: string;
  symbol: string;
  dimension: WorkspaceDimension;
  placeholder?: string;
};

export type WorkspaceFormula = {
  key: string;
  label: string;
  expression: string;
  resultKey: string;
};

export type WorkspaceValue = {
  key: string;
  label: string;
  symbol: string;
  value: number;
  dimension: WorkspaceDimension;
  source: WorkspaceValueSource;
  unit: string;
};

export type WorkspaceCalculationStep = {
  id: string;
  title: string;
  formula: string;
  substitution: string;
  result: string;
  rule?: string;
};

export type WorkspaceConflict = {
  field: string;
  label: string;
  enteredValue: number;
  expectedValue: number | null;
  message: string;
};

export type WorkspaceGraphConfiguration = {
  title: string;
  description: string;
  labels: string[];
  values: number[];
  unit: string;
} | null;

export type WorkspaceVisualizationConfiguration = {
  kind: "geometry-2d" | "geometry-3d";
  shape: TwoDShapeKey | "cube";
  values: Record<string, number>;
};

export type MathWorkspaceState = {
  version: 1;
  selectedTopic: WorkspaceTopicKey;
  solveFor: string | null;
  userEnteredValues: WorkspaceValue[];
  calculatedValues: WorkspaceValue[];
  units: {
    length: LengthUnit;
    area: string;
    volume: string;
    angle: string;
  };
  formulas: WorkspaceFormula[];
  calculationSteps: WorkspaceCalculationStep[];
  warnings: string[];
  conflicts: WorkspaceConflict[];
  graphConfiguration: WorkspaceGraphConfiguration;
  visualizationConfiguration: WorkspaceVisualizationConfiguration | null;
};

export type WorkspaceTopicDefinition = {
  key: WorkspaceTopicKey;
  label: string;
  category: string;
  calculatorRoute: string;
  minimumKnownValues: number;
  fields: WorkspaceFieldDefinition[];
  formulas: WorkspaceFormula[];
};

export type WorkspaceSolveInput = {
  topic: WorkspaceTopicKey;
  values: Record<string, number | null>;
  unit: LengthUnit;
  solveFor?: string | null;
};

export type WorkspaceInterpretation = {
  topic: WorkspaceTopicKey | null;
  values: Record<string, number>;
  unit: LengthUnit;
  solveFor: string | null;
  warnings: string[];
};

export type WorkspacePractice = {
  topic: WorkspaceTopicKey;
  promptKey: string;
  replacements: Record<string, string>;
  answerLabel: string;
  answer: string;
  state: MathWorkspaceState;
};

export type SavedMathWorkspaceCalculation = {
  id: number;
  title: string;
  topic: WorkspaceTopicKey;
  problem_text: string | null;
  state: MathWorkspaceState;
  created_at: string;
};
