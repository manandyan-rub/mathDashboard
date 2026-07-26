import { fromBaseUnit, toBaseUnit } from "@/lib/geometry/units";
import { workspaceTopic } from "@/lib/mathWorkspace/topics";
import type { LengthUnit } from "@/types/geometryCalculator";
import type {
  WorkspaceDimension,
  WorkspaceInterpretation,
  WorkspaceTopicKey,
} from "@/types/mathWorkspace";

const TOPIC_ALIASES: Record<WorkspaceTopicKey, string[]> = {
  rectangle: ["rectangle", "rectangular", "прямоугольник", "ուղղանկյուն"],
  circle: ["circle", "cercle", "круг", "окружность", "շրջան", "շրջագիծ"],
  triangle: ["triangle", "треугольник", "եռանկյուն"],
  trapezoid: ["trapezoid", "trapezium", "trapèze", "трапеция", "սեղան"],
  parallelogram: [
    "parallelogram",
    "parallélogramme",
    "параллелограмм",
    "զուգահեռագիծ",
  ],
  cube: ["cube", "куб", "խորանարդ"],
};

const FIELD_ALIASES: Record<string, string[]> = {
  length: [
    "length",
    "rectangle height",
    "height",
    "longueur",
    "hauteur",
    "длина",
    "высота",
    "երկարություն",
    "բարձրություն",
  ],
  width: ["width", "largeur", "ширина", "լայնություն"],
  area: ["area", "surface area", "aire", "площадь", "մակերես"],
  perimeter: ["perimeter", "périmètre", "периметр", "պարագիծ"],
  diagonal: ["diagonal", "diagonale", "диагональ", "անկյունագիծ"],
  radius: ["radius", "rayon", "радиус", "շառավիղ"],
  diameter: ["diameter", "diamètre", "диаметр", "տրամագիծ"],
  circumference: [
    "circumference",
    "circonférence",
    "длина окружности",
    "շրջագծի երկարություն",
  ],
  sideA: ["side a", "côté a", "сторона a", "կող a"],
  sideB: ["side b", "côté b", "сторона b", "կող b"],
  sideC: ["side c", "côté c", "сторона c", "կող c"],
  sideD: ["side d", "côté d", "сторона d", "կող d"],
  baseA: ["base a", "основание a", "հիմք a"],
  baseB: ["base b", "основание b", "հիմք b"],
  base: ["base", "основание", "հիմք"],
  side: ["side", "côté", "сторона", "կող"],
  height: ["height", "hauteur", "высота", "բարձրություն"],
  angle: ["angle", "угол", "անկյուն"],
  angleA: ["angle a", "угол a", "անկյուն a"],
  angleB: ["angle b", "угол b", "անկյուն b"],
  angleC: ["angle c", "угол c", "անկյուն c"],
  diagonalA: ["diagonal 1", "diagonal a", "диагональ 1", "անկյունագիծ 1"],
  diagonalB: ["diagonal 2", "diagonal b", "диагональ 2", "անկյունագիծ 2"],
  faceArea: ["face area", "площадь грани", "նիստի մակերես"],
  surfaceArea: [
    "total surface area",
    "surface area",
    "полная площадь поверхности",
    "մակերևույթի ընդհանուր մակերես",
  ],
  volume: ["volume", "объем", "объём", "ծավալ"],
  faceDiagonal: ["face diagonal", "диагональ грани", "նիստի անկյունագիծ"],
  spaceDiagonal: [
    "space diagonal",
    "пространственная диагональ",
    "տարածական անկյունագիծ",
  ],
  facePerimeter: ["face perimeter", "периметр грани", "նիստի պարագիծ"],
  totalEdgeLength: [
    "total edge length",
    "суммарная длина ребер",
    "կողերի ընդհանուր երկարություն",
  ],
  circumsphereRadius: [
    "circumsphere radius",
    "радиус описанной сферы",
    "արտագծված գնդի շառավիղ",
  ],
  insphereRadius: [
    "insphere radius",
    "радиус вписанной сферы",
    "ներգծված գնդի շառավիղ",
  ],
};

const UNIT_ALIASES: Record<string, LengthUnit> = {
  mm: "mm",
  millimeter: "mm",
  millimeters: "mm",
  millimetre: "mm",
  millimetres: "mm",
  cm: "cm",
  centimeter: "cm",
  centimeters: "cm",
  centimetre: "cm",
  centimetres: "cm",
  m: "m",
  meter: "m",
  meters: "m",
  metre: "m",
  metres: "m",
  km: "km",
  kilometer: "km",
  kilometers: "km",
  kilometre: "km",
  kilometres: "km",
  in: "in",
  inch: "in",
  inches: "in",
  ft: "ft",
  foot: "ft",
  feet: "ft",
};

const CONNECTOR =
  "(?:is|are|=|of|:|est|vaut|равна|равен|равняется|է|կազմում է)?";
const NUMBER = "(-?\\d+(?:[.,]\\d+)?)";
const UNIT =
  "(mm|cm|km|m|in|ft|millimeters?|millimetres?|centimeters?|centimetres?|meters?|metres?|kilometers?|kilometres?|inches?|feet|foot)?";

export function interpretWorkspaceProblem(
  text: string,
  fallbackUnit: LengthUnit = "cm",
): WorkspaceInterpretation {
  const normalized = text.replace(/\s+/g, " ").trim().toLocaleLowerCase();
  const topic = detectTopic(normalized);

  if (!topic) {
    return {
      topic: null,
      values: {},
      unit: fallbackUnit,
      solveFor: null,
      warnings: [
        "Choose a topic manually, then enter the measurements you know.",
      ],
    };
  }

  const definition = workspaceTopic(topic);
  const matches = definition.fields.flatMap((fieldValue) => {
    const aliases = FIELD_ALIASES[fieldValue.key] ?? [fieldValue.label];
    const aliasPattern = aliases
      .sort((left, right) => right.length - left.length)
      .map(escapeRegex)
      .join("|");
    const pattern = new RegExp(
      `(?:^|[^\\p{L}\\p{N}])(?:${aliasPattern})(?![\\p{L}\\p{N}])\\s*${CONNECTOR}\\s*${NUMBER}\\s*${UNIT}(?:\\s*(?:\\^?[23]|[²³]))?`,
      "iu",
    );
    const match = normalized.match(pattern);
    if (!match) return [];

    return [
      {
        key: fieldValue.key,
        value: Number(match[1].replace(",", ".")),
        unit: normalizeUnit(match[2]),
        dimension: fieldValue.dimension,
      },
    ];
  });
  const selectedUnit =
    matches.find((match) => match.unit !== null)?.unit ?? fallbackUnit;
  const values = Object.fromEntries(
    matches.map((match) => [
      match.key,
      convertMeasurement(
        match.value,
        match.unit ?? selectedUnit,
        selectedUnit,
        match.dimension,
      ),
    ]),
  );
  const solveFor = detectSolveFor(
    normalized,
    definition.fields.map(({ key }) => key),
  );
  const warnings: string[] = [];

  if (!matches.length) {
    warnings.push(
      "The topic was detected, but no measurements were recognized. Enter them manually.",
    );
  } else if (matches.length < definition.minimumKnownValues) {
    warnings.push("More independent measurements are needed to solve this topic.");
  }

  return {
    topic,
    values,
    unit: selectedUnit,
    solveFor,
    warnings,
  };
}

function detectTopic(text: string): WorkspaceTopicKey | null {
  for (const [topic, aliases] of Object.entries(TOPIC_ALIASES) as Array<
    [WorkspaceTopicKey, string[]]
  >) {
    if (aliases.some((alias) => text.includes(alias))) return topic;
  }
  return null;
}

function detectSolveFor(text: string, keys: string[]): string | null {
  const requestSegment =
    text.match(
      /(?:find|calculate|solve for|determine|trouver|calculer|найти|вычислить|գտնել|հաշվել)\s+(.+)$/iu,
    )?.[1] ?? "";

  if (!requestSegment) return null;

  for (const key of keys) {
    const aliases = FIELD_ALIASES[key] ?? [key];
    if (aliases.some((alias) => requestSegment.includes(alias))) return key;
  }
  return null;
}

function convertMeasurement(
  value: number,
  from: LengthUnit,
  to: LengthUnit,
  dimension: WorkspaceDimension,
): number {
  if (dimension === 0 || from === to) return value;
  return fromBaseUnit(toBaseUnit(value, from, dimension), to, dimension);
}

function normalizeUnit(value?: string): LengthUnit | null {
  if (!value) return null;
  return UNIT_ALIASES[value.toLocaleLowerCase()] ?? null;
}

function escapeRegex(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}
