export type Formula = {
  id: string;
  name: string;
  latex: string;
  description?: string;
  example?: string;
};

export type FormulaApiRecord = {
  id: number;
  name: string;
  category: string;
  formula: string;
  description?: string | null;
  example?: string | null;
};

export type FormulaGroupApiRecord = {
  id: string;
  label: string;
  color: string;
  sections: Array<{
    id: string;
    title: string;
  }>;
};

export type FormulaStep = {
  latex: string;
  note?: string;
};

export type FormulaStepsResponse = {
  formula_id: number;
  has_steps: boolean;
  steps: FormulaStep[];
};

export type FormulaSection = {
  id: string;
  title: string;
  formulas: Formula[];
};

export type FormulaCategory = {
  id: string;
  label: string;
  color: string;
  sections: FormulaSection[];
};
