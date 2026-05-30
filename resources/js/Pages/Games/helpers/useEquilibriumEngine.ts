import { computed, onBeforeUnmount, ref } from "vue";
import { awardGameXp } from "./gameXp";

type GameState = "idle" | "playing" | "ended";
type Mode = "scholastic" | "student" | "einstein";

interface HandItem {
  id: string;
  val: number;
  used: boolean;
}

const MODE_CONFIG: Record<
  Mode,
  { label: string; color: string; pointsMultiplier: number }
> = {
  scholastic: {
    label: "Scholastic",
    color: "bg-chart-2",
    pointsMultiplier: 50,
  },
  student: {
    label: "Student",
    color: "bg-chart-5",
    pointsMultiplier: 100,
  },
  einstein: {
    label: "Einstein",
    color: "bg-destructive",
    pointsMultiplier: 250,
  },
};

function randomInt(min: number, max: number): number {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

function shuffle<T>(list: T[]): T[] {
  const cloned = [...list];
  for (let i = cloned.length - 1; i > 0; i -= 1) {
    const j = Math.floor(Math.random() * (i + 1));
    [cloned[i], cloned[j]] = [cloned[j], cloned[i]];
  }
  return cloned;
}

export function useEquilibriumEngine() {
  const gameState = ref<GameState>("idle");
  const score = ref(0);
  const level = ref(1);
  const mode = ref<Mode>("student");
  const timeLeft = ref(30);
  const maxTime = ref(30);
  const leftItems = ref<HandItem[]>([]);
  const rightItems = ref<number[]>([]);
  const hand = ref<HandItem[]>([]);

  let timer: ReturnType<typeof setInterval> | null = null;

  const leftSum = computed(() =>
    leftItems.value.reduce((acc, item) => acc + item.val, 0),
  );
  const rightSum = computed(() =>
    rightItems.value.reduce((acc, item) => acc + item, 0),
  );

  const modeLabel = computed(() => MODE_CONFIG[mode.value].label);
  const modeColor = computed(() => MODE_CONFIG[mode.value].color);

  const scaleClass = computed(() => {
    if (leftSum.value === rightSum.value && leftItems.value.length > 0) {
      return "scale-perfect";
    }
    return leftSum.value > rightSum.value ? "scale-tilt-left" : "scale-tilt-right";
  });

  const canBalance = computed(
    () => leftItems.value.length > 0 && leftSum.value === rightSum.value,
  );

  function clearTimer() {
    if (timer) {
      clearInterval(timer);
      timer = null;
    }
  }

  function endGame() {
    clearTimer();
    gameState.value = "ended";
  }

  function startTimer() {
    clearTimer();
    timer = setInterval(() => {
      timeLeft.value = Math.max(0, timeLeft.value - 0.05);
      if (timeLeft.value <= 0) {
        endGame();
      }
    }, 50);
  }

  function generateRound() {
    leftItems.value = [];

    let baseTime = 30;
    if (mode.value === "student") baseTime = 20;
    if (mode.value === "einstein") baseTime = 15;

    maxTime.value = Math.max(5, baseTime - level.value * 0.4);
    timeLeft.value = maxTime.value;

    let complexity = 2;
    let maxNumValue = 10;

    if (mode.value === "student") {
      complexity = 3;
      maxNumValue = 20;
    }
    if (mode.value === "einstein") {
      complexity = 4;
      maxNumValue = 25;
    }
    if (mode.value === "scholastic") {
      complexity = 2;
      maxNumValue = 9;
    }

    rightItems.value = Array.from({ length: complexity }, () =>
      randomInt(1, maxNumValue),
    );

    const target = rightItems.value.reduce((acc, item) => acc + item, 0);
    const newHandRaw: number[] = [];
    let current = 0;

    while (current < target) {
      let step = randomInt(1, 12);
      if (current + step > target) step = target - current;
      newHandRaw.push(step);
      current += step;
    }

    while (newHandRaw.length < 8) {
      newHandRaw.push(randomInt(1, maxNumValue));
    }

    hand.value = shuffle(newHandRaw).map((val, idx) => ({
      id: `h-${idx}-${Date.now()}-${Math.round(Math.random() * 10000)}`,
      val,
      used: false,
    }));

    startTimer();
  }

  function start(selectedMode: Mode) {
    mode.value = selectedMode;
    score.value = 0;
    level.value = 1;
    gameState.value = "playing";
    generateRound();
  }

  function addToScale(item: HandItem) {
    if (item.used) return;
    item.used = true;
    leftItems.value.push(item);
  }

  function removeFromScale(index: number) {
    const item = leftItems.value[index];
    if (!item) return;
    item.used = false;
    leftItems.value.splice(index, 1);
  }

  function resetCurrent() {
    leftItems.value.forEach((item) => {
      item.used = false;
    });
    leftItems.value = [];
  }

  function balance() {
    if (!canBalance.value) return;
    score.value += MODE_CONFIG[mode.value].pointsMultiplier * level.value;
    void awardGameXp("equilibrium", mode.value, 1);
    level.value += 1;
    generateRound();
  }

  onBeforeUnmount(() => {
    clearTimer();
  });

  return {
    MODE_CONFIG,
    gameState,
    score,
    level,
    mode,
    modeLabel,
    modeColor,
    timeLeft,
    maxTime,
    leftItems,
    rightItems,
    hand,
    leftSum,
    rightSum,
    scaleClass,
    canBalance,
    start,
    addToScale,
    removeFromScale,
    resetCurrent,
    balance,
  };
}
