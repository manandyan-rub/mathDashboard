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
    label: "Child",
    emoji: "Kid",
    desc: "Simple arithmetic with friendly timing and no wrong-answer penalty.",
    startTimer: 12,
    minTimer: 4,
    decrement: 0.3,
    bubbleBase: 5,
    pointsPerCorrect: 10,
    bonusSecs: 3,
    penaltySecs: 0,
  },
  student: {
    label: "Student",
    emoji: "Math",
    desc: "Mixed practice with arithmetic, fractions, percentages, geometry, and logic.",
    startTimer: 6,
    minTimer: 1.5,
    decrement: 0.2,
    bubbleBase: 6,
    pointsPerCorrect: 20,
    bonusSecs: 2,
    penaltySecs: 3,
  },
  einstein: {
    label: "Einstein",
    emoji: "Atom",
    desc: "Advanced calculus, geometry, algebra, and fast-response challenges.",
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
  child: [
    "--chart-5",
    "--chart-4",
    "--destructive",
    "--chart-3",
    "--chart-2",
  ],
  student: [
    "--primary",
    "--chart-5",
    "--chart-2",
    "--chart-3",
    "--chart-4",
  ],
  einstein: [
    "--destructive",
    "--destructive",
    "--destructive",
    "--destructive",
    "--destructive",
  ],
};

export const TICK = 50;
