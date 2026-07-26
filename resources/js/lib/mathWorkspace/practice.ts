import { formatNumber, solveWorkspace } from "@/lib/mathWorkspace/topics";
import type { LengthUnit } from "@/types/geometryCalculator";
import type {
  WorkspacePractice,
  WorkspaceTopicKey,
} from "@/types/mathWorkspace";

export function generateWorkspacePractice(
  topic: WorkspaceTopicKey,
  unit: LengthUnit,
): WorkspacePractice {
  if (topic === "circle") {
    const radius = randomInteger(2, 12);
    const state = solveWorkspace({
      topic,
      unit,
      solveFor: "area",
      values: { radius },
    });
    return practice(
      topic,
      "A circle has radius :radius :unit. Find its area.",
      { radius: String(radius), unit },
      "Area",
      state,
      "area",
    );
  }

  if (topic === "cube") {
    const side = randomInteger(2, 10);
    const state = solveWorkspace({
      topic,
      unit,
      solveFor: "volume",
      values: { side },
    });
    return practice(
      topic,
      "A cube has side length :side :unit. Find its volume.",
      { side: String(side), unit },
      "Volume",
      state,
      "volume",
    );
  }

  if (topic === "triangle") {
    const scale = randomInteger(1, 4);
    const values = { sideA: 3 * scale, sideB: 4 * scale, sideC: 5 * scale };
    const state = solveWorkspace({
      topic,
      unit,
      solveFor: "area",
      values,
    });
    return practice(
      topic,
      "A triangle has sides :a :unit, :b :unit, and :c :unit. Find its area.",
      {
        a: String(values.sideA),
        b: String(values.sideB),
        c: String(values.sideC),
        unit,
      },
      "Area",
      state,
      "area",
    );
  }

  if (topic === "trapezoid") {
    const height = randomInteger(3, 7);
    const baseA = randomInteger(4, 8);
    const baseB = baseA + randomInteger(2, 6);
    const sideC = Math.hypot((baseB - baseA) / 2, height);
    const state = solveWorkspace({
      topic,
      unit,
      solveFor: "area",
      values: { baseA, baseB, height, sideC },
    });
    return practice(
      topic,
      "A trapezoid has bases :a :unit and :b :unit with height :height :unit. Find its area.",
      {
        a: String(baseA),
        b: String(baseB),
        height: String(height),
        unit,
      },
      "Area",
      state,
      "area",
    );
  }

  if (topic === "parallelogram") {
    const base = randomInteger(5, 12);
    const side = randomInteger(3, 9);
    const angle = [30, 45, 60][randomInteger(0, 2)];
    const state = solveWorkspace({
      topic,
      unit,
      solveFor: "area",
      values: { base, side, angle },
    });
    return practice(
      topic,
      "A parallelogram has base :base :unit, side :side :unit, and included angle :angle°. Find its area.",
      {
        base: String(base),
        side: String(side),
        angle: String(angle),
        unit,
      },
      "Area",
      state,
      "area",
    );
  }

  const length = randomInteger(5, 12);
  const width = randomInteger(3, 9);
  const state = solveWorkspace({
    topic: "rectangle",
    unit,
    solveFor: "area",
    values: { length, width },
  });
  return practice(
    "rectangle",
    "A rectangle has length :length :unit and width :width :unit. Find its area.",
    { length: String(length), width: String(width), unit },
    "Area",
    state,
    "area",
  );
}

function practice(
  topic: WorkspaceTopicKey,
  promptKey: string,
  replacements: Record<string, string>,
  answerLabel: string,
  state: WorkspacePractice["state"],
  answerKey: string,
): WorkspacePractice {
  const answer =
    [...state.userEnteredValues, ...state.calculatedValues].find(
      ({ key }) => key === answerKey,
    ) ?? null;

  return {
    topic,
    promptKey,
    replacements,
    answerLabel,
    answer: answer
      ? `${formatNumber(answer.value)} ${answer.unit}`.trim()
      : "-",
    state,
  };
}

function randomInteger(minimum: number, maximum: number): number {
  return Math.floor(Math.random() * (maximum - minimum + 1)) + minimum;
}
