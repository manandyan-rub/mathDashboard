export type StudioStatus = "idle" | "processing" | "ready" | "failed";
export type Difficulty = "easy" | "medium" | "hard";
export type Locale = "en" | "hy" | "fr" | "ru";

export type ExerciseItem = {
  id: number;
  item_order: number;
  type: string;
  question: string;
  question_html?: string | null;
  options: string[];
  options_html?: string[] | null;
  answer: string;
  answer_html?: string | null;
  explanation?: string | null;
  explanation_html?: string | null;
  difficulty?: string | null;
  points: number;
  source: string;
};

export type Segment = {
  type: "text" | "math";
  value: string;
};

export type MathfieldLike = HTMLElement & {
  value?: string;
  getValue?: (format?: string) => string;
  setValue?: (value: string) => void;
};

export type ExerciseDocument = {
  id: number;
  title: string;
  status: string;
  language: Locale;
  meta?: Record<string, unknown> | null;
  content_json?: Record<string, unknown> | null;
  content_html?: string | null;
  generated_at?: string | null;
  published_at?: string | null;
  items: ExerciseItem[];
};

export type GeometryTemplate = {
  key: string;
  category: "2d" | "3d";
  labelKey: string;
  lines: string[];
};

export type DrawnGeometryShape = {
  id: number;
  kind: string;
  x: number;
  y: number;
  width: number;
  height: number;
  fill: string;
  stroke: string;
  strokeWidth: number;
};

export type FreehandDrawingLine = {
  id: string;
  path: string;
  color: string;
  size: number;
};
