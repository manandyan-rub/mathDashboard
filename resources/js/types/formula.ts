export type Formula = {
  id: string;
  name: string;
  latex: string;
  description?: string;
  example?: string;
};

export type FormulaSection = {
  id: string;
  title: string;
  formulas: Formula[];
};

export type FormulaCategory = {
  id: string;
  label: string;
  emoji: string;
  color: string;
  sections: FormulaSection[];
};
