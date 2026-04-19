export type GameState = "idle" | "playing" | "ended";
export type Mode = "child" | "student" | "einstein";

export type Bubble = {
  id: number;
  value: string;
  latex: string;
  cx: number;
  cy: number;
  size: number;
  color: string;
  isCorrect: boolean;
  state: "idle" | "correct" | "wrong";
};

export type Problem = {
  display: string;
  latex: string;
  answer: string;
  answerLatex: string;
};

export type ModeConfig = {
  label: string;
  emoji: string;
  desc: string;
  startTimer: number;
  minTimer: number;
  decrement: number;
  bubbleBase: number;
  pointsPerCorrect: number;
  bonusSecs: number;
  penaltySecs: number;
};

export const MODES: Record<Mode, ModeConfig> = {
  child: {
    label: "Երեխա",
    emoji: "🧒",
    desc: "Պարզ հաշվարկներ և հեշտ խնդիրներ սկսնակների համար։",
    startTimer: 12,
    minTimer: 4,
    decrement: 0.3,
    bubbleBase: 5,
    pointsPerCorrect: 10,
    bonusSecs: 3,
    penaltySecs: 0,
  },
  student: {
    label: "Ուսանող",
    emoji: "📐",
    desc: "Միջին բարդության խնդիրներ՝ գումարում, հանում, բազմապատկում, բաժանում և այլ։",
    startTimer: 6,
    minTimer: 1.5,
    decrement: 0.2,
    bubbleBase: 6,
    pointsPerCorrect: 20,
    bonusSecs: 2,
    penaltySecs: 3,
  },
  einstein: {
    label: "Էյնշտեյն",
    emoji: "⚛️",
    desc: "Ավելի բարդ խնդիրներ՝ արագ մտածողության և ճշգրտության համար։",
    startTimer: 8,
    minTimer: 2,
    decrement: 0.15,
    bubbleBase: 7,
    pointsPerCorrect: 50,
    bonusSecs: 2,
    penaltySecs: 4,
  },
};

export const BUBBLE_COLORS: Record<Mode, string[]> = {
  child: ["#3b82f6", "#8b5cf6", "#ec4899", "#f97316", "#10b981"],
  student: ["#6366f1", "#0ea5e9", "#14b8a6", "#f59e0b", "#8b5cf6"],
  einstein: ["#ef4444", "#b91c1c", "#dc2626", "#991b1b", "#7f1d1d"],
};

export const TICK = 50;

