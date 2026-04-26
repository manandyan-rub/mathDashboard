import "mathlive";
import { MathfieldElement } from "mathlive";
import {
  ref,
  computed,
  watch,
  onMounted,
  onUpdated,
  onBeforeUnmount,
  nextTick,
} from "vue";
import type { GameState, Mode, Bubble, Problem } from "@/types/game";
import { MODES, BUBBLE_COLORS, TICK } from "@/types/game";
import { pick } from "./mathUtils";
import { genChild } from "./genChild";
import { genStudent } from "./genStudent";
import { genEinstein } from "./genEinstein";
import { awardGameXp } from "./gameXp";
import { useTheme } from "@/composables/useTheme";

MathfieldElement.fontsDirectory = "/fonts/mathlive";
MathfieldElement.soundsDirectory = "/sounds/mathlive";

export function useGameEngine() {
  const gameState = ref<GameState>("idle");
  const mode = ref<Mode>("student");
  const score = ref(0);
  const highScore = ref(Number(localStorage.getItem("mq_hs") ?? 0));
  const streak = ref(0);
  const bestStreak = ref(0);
  const timeLeft = ref(6);
  const timeLimit = ref(6);
  const bubbles = ref<Bubble[]>([]);
  const problem = ref<Problem>({
    display: "",
    latex: "",
    answer: "",
    answerLatex: "",
  });
  const feedback = ref<{ text: string; good: boolean } | null>(null);
  const { isDark } = useTheme();
  const arenaSz = ref({ w: 600, h: 400 });

  let tickId: ReturnType<typeof setInterval> | null = null;
  let feedbackId: ReturnType<typeof setTimeout> | null = null;
  let bubbleCount = 0;

  const cfg = computed(() => MODES[mode.value]);
  const timerPct = computed(() =>
    Math.max(0, (timeLeft.value / timeLimit.value) * 100),
  );
  const timerColor = computed(() =>
    timerPct.value > 50
      ? "#22c55e"
      : timerPct.value > 25
        ? "#f59e0b"
        : "#ef4444",
  );
  const isEinstein = computed(() => mode.value === "einstein");
  const timerDisplay = computed(() => timeLeft.value.toFixed(1) + "վ");

  onMounted(() => {
    updateArena();
    window.addEventListener("resize", updateArena);
  });
  onBeforeUnmount(() => {
    clearTick();
    window.removeEventListener("resize", updateArena);
  });
  onUpdated(() => renderAll());
  watch(isDark, () => nextTick(() => renderAll()));

  function updateArena() {
    const el = document.getElementById("arena");
    if (el) arenaSz.value = { w: el.clientWidth, h: el.clientHeight };
  }

  function injectMath(container: HTMLElement, latex: string, color: string) {
    const isBubble = container.hasAttribute("data-mf-bubble");
    let mf = container.querySelector("math-field") as any;
    if (!mf) {
      mf = document.createElement("math-field");
      mf.setAttribute("read-only", "");
      mf.style.cssText = isBubble
        ? "background:transparent;border:none;pointer-events:none;display:flex;align-items:center;justify-content:center;width:100%;height:100%;padding:0;margin:0;"
        : "background:transparent;border:none;pointer-events:none;display:block;width:100%;";
      container.appendChild(mf);
    }
    mf.style.color = color;
    requestAnimationFrame(() => {
      if (typeof mf.setValue === "function") mf.setValue(latex);
      else mf.value = latex;
    });
  }

  function renderAll() {
    const textColor = isDark.value ? "#f1f5f9" : "#0f172a";
    const qEl = document.querySelector<HTMLElement>("[data-mf-question]");
    if (qEl && problem.value.latex)
      injectMath(qEl, problem.value.latex, textColor);
    document.querySelectorAll<HTMLElement>("[data-mf-bubble]").forEach((el) => {
      const lat = el.dataset.mfBubble;
      if (lat) injectMath(el, lat, "#ffffff");
    });
    const aEl = document.querySelector<HTMLElement>("[data-mf-answer]");
    if (aEl && problem.value.answerLatex)
      injectMath(aEl, problem.value.answerLatex, "#3b82f6");
  }

  function genWrongs(correct: string, count: number): string[] {
    const num = Number(correct);
    const isNumeric =
      !isNaN(num) && correct !== "" && !/[a-zA-Z\\{}π+C]/.test(correct);
    if (isNumeric) {
      const wrongs = new Set<number>();
      const spread = Math.max(5, Math.abs(num) * 0.3);
      let attempts = 0;
      while (wrongs.size < count && attempts < 300) {
        const offset = Math.ceil(spread * (Math.random() * 2 - 1));
        const w = Math.round(num + offset);
        if (w !== num && w >= 0) wrongs.add(w);
        attempts++;
      }
      return [...wrongs].map(String);
    }
    const numMatch = correct.match(/\d+/g);
    if (numMatch) {
      const wrongs: string[] = [];
      for (let i = 0; i < count * 4 && wrongs.length < count; i++) {
        const modified = correct.replace(/\d+/g, (m) => {
          const n = parseInt(m);
          return String(Math.max(1, n + pick([-2, -1, 1, 2, 3])));
        });
        if (modified !== correct && !wrongs.includes(modified))
          wrongs.push(modified);
      }
      if (wrongs.length >= count) return wrongs.slice(0, count);
    }
    const fallbacks: Record<string, string[]> = {
      "-\\cos x+C": ["\\cos x+C", "\\sin x+C", "-\\sin x+C", "\\tan x+C"],
      "\\sin x+C": ["-\\sin x+C", "\\cos x+C", "-\\cos x+C", "\\tan x+C"],
      "e^x+C": ["xe^x+C", "e^{x+1}+C", "\\ln x+C", "\\frac{e^x}{x}+C"],
      "\\ln|x|+C": ["\\frac{1}{x}+C", "x+C", "\\ln x^2+C", "\\frac{1}{x^2}+C"],
      "\\cos x": ["-\\cos x", "-\\sin x", "\\sin x", "\\tan x"],
      "-\\sin x": ["\\sin x", "-\\cos x", "\\cos x", "\\tan x"],
      "e^x": ["xe^x", "e^{x-1}", "\\ln x", "x e^x"],
      "\\frac{1}{x}": ["\\ln x", "x", "\\frac{1}{x^2}", "-\\frac{1}{x}"],
      "\\sec^2 x": [
        "\\cos^2 x",
        "-\\sin^2 x",
        "\\tan^2 x",
        "\\frac{1}{\\cos x}",
      ],
      "1": ["0", "2", "-1", "\\frac{1}{2}"],
      "0": ["1", "-1", "\\frac{1}{2}", "2"],
    };
    for (const [key, ws] of Object.entries(fallbacks)) {
      if (correct === key)
        return ws.filter((w) => w !== correct).slice(0, count);
    }
    return ["A", "B", "C", "D", "E"].slice(0, count);
  }

  function placeBubbles(prob: Problem) {
    const { w, h } = arenaSz.value;
    const m = mode.value;
    const size = m === "einstein" ? 82 : 92;
    const extraFromScore = Math.floor(score.value / 150);
    const count = Math.min(
      cfg.value.bubbleBase + extraFromScore,
      m === "einstein" ? 10 : 7,
    );
    const wrongCount = count - 1;
    const wrongAnswers = genWrongs(prob.answer, wrongCount * 2).slice(
      0,
      wrongCount,
    );
    const values = [
      ...wrongAnswers.map((v) => ({ v, correct: false })),
      { v: prob.answer, correct: true },
    ].sort(() => Math.random() - 0.5);

    const colors = BUBBLE_COLORS[m];
    const colorStart = Math.floor(Math.random() * colors.length);
    const placed: Bubble[] = [];
    const topPad = 185;

    values.forEach(({ v, correct }, index) => {
      let cx = 0,
        cy = 0,
        ok = false,
        tries = 0;
      while (!ok && tries < 150) {
        cx = size / 2 + 16 + Math.random() * (w - size - 32);
        cy = topPad + size / 2 + Math.random() * (h - topPad - size - 20);
        ok = placed.every((b) => Math.hypot(b.cx - cx, b.cy - cy) > size + 12);
        tries++;
      }
      placed.push({
        id: bubbleCount++,
        value: v,
        latex: v,
        cx,
        cy,
        size,
        color: colors[(colorStart + index) % colors.length],
        isCorrect: correct,
        state: "idle",
      });
    });
    bubbles.value = placed;
  }

  function startGame(m: Mode) {
    mode.value = m;
    score.value = 0;
    streak.value = 0;
    bestStreak.value = 0;
    timeLimit.value = MODES[m].startTimer;
    timeLeft.value = timeLimit.value;
    gameState.value = "playing";
    nextRound();
  }

  async function nextRound() {
    clearTick();
    const p =
      mode.value === "child"
        ? genChild()
        : mode.value === "student"
          ? genStudent()
          : genEinstein();
    problem.value = p;
    timeLeft.value = timeLimit.value;
    if (mode.value === "einstein" && /[\\^{]/.test(p.answerLatex)) {
      timeLeft.value = Math.max(timeLimit.value, 7);
    }
    placeBubbles(p);
    startTick();
    await nextTick();
    renderAll();
  }

  function startTick() {
    tickId = setInterval(() => {
      timeLeft.value = Math.max(0, timeLeft.value - TICK / 1000);
      if (timeLeft.value <= 0) endGame();
    }, TICK);
  }

  function clearTick() {
    if (tickId) {
      clearInterval(tickId);
      tickId = null;
    }
  }

  function handleBubble(bubble: Bubble) {
    if (gameState.value !== "playing") return;
    if (bubble.state !== "idle") return;
    clearTick();
    if (bubble.isCorrect) {
      bubble.state = "correct";
      const ratio = timeLeft.value / timeLimit.value;
      const speed =
        ratio > 0.8 ? "ՀԱՆՃԱՐ!" : ratio > 0.5 ? "ՀԻԱՆԱԼԻ" : "ՓՐԿՎԵՑ";
      score.value += cfg.value.pointsPerCorrect;
      streak.value++;
      void awardGameXp("oneplusone", mode.value, 1);
      if (streak.value > bestStreak.value) bestStreak.value = streak.value;
      timeLeft.value = Math.min(
        timeLimit.value + 5,
        timeLeft.value + cfg.value.bonusSecs,
      );
      timeLimit.value = Math.max(
        cfg.value.minTimer,
        timeLimit.value - cfg.value.decrement,
      );
      showFeedback(speed, true);
      setTimeout(nextRound, 400);
    } else {
      bubble.state = "wrong";
      timeLeft.value = Math.max(0, timeLeft.value - cfg.value.penaltySecs);
      showFeedback("ՍԽԱԼ!", false);
      setTimeout(() => {
        bubble.state = "idle";
        startTick();
      }, 420);
    }
  }

  function showFeedback(text: string, good: boolean) {
    if (feedbackId) clearTimeout(feedbackId);
    feedback.value = { text, good };
    feedbackId = setTimeout(() => {
      feedback.value = null;
    }, 700);
  }

  function endGame() {
    clearTick();
    gameState.value = "ended";
    if (score.value > highScore.value) {
      highScore.value = score.value;
      localStorage.setItem("mq_hs", String(score.value));
    }
    bubbles.value = [];
    nextTick(() => renderAll());
  }

  return {
    gameState,
    mode,
    score,
    highScore,
    streak,
    bestStreak,
    timeLeft,
    timeLimit,
    bubbles,
    problem,
    feedback,
    isDark,
    cfg,
    timerPct,
    timerColor,
    isEinstein,
    timerDisplay,
    startGame,
    handleBubble,
    renderAll,
    MODES,
  };
}
