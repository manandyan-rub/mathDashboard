import { mergeAttributes, Node } from "@tiptap/core";
import { VueNodeViewRenderer } from "@tiptap/vue-3";
import type { FreehandDrawingLine } from "@/types/teacher";
import FreehandDrawingNodeView from "./FreehandDrawingNodeView.vue";

const BOARD_WIDTH = 500;
const BOARD_HEIGHT = 250;

function normalizeStrokeSize(value: unknown): number {
  const size = Number(value);
  if (!Number.isFinite(size)) return 3;
  return Math.max(1, Math.min(12, Math.round(size)));
}

function normalizeLines(value: unknown): FreehandDrawingLine[] {
  if (!Array.isArray(value)) return [];

  return value
    .map((line) => {
      if (!line || typeof line !== "object") return null;
      const raw = line as Record<string, unknown>;
      const id = String(raw.id ?? "").trim();
      const path = String(raw.path ?? "").trim();
      const color = String(raw.color ?? "#4f46e5").trim() || "#4f46e5";
      const size = normalizeStrokeSize(raw.size);

      if (!id || !path) return null;

      return { id, path, color, size } as FreehandDrawingLine;
    })
    .filter((line): line is FreehandDrawingLine => !!line);
}

function lineDomSpec(line: FreehandDrawingLine) {
  return [
    "path",
    {
      d: line.path,
      stroke: line.color,
      "stroke-width": String(line.size),
      fill: "none",
      "stroke-linecap": "round",
      "stroke-linejoin": "round",
    },
  ];
}

const FreehandDrawingNode = Node.create({
  name: "freehandDrawing",
  group: "block",
  atom: true,
  selectable: true,
  draggable: true,

  addAttributes() {
    return {
      lines: {
        default: [],
        parseHTML: (element: HTMLElement) => {
          const raw = element.getAttribute("data-lines");
          if (!raw) return [];
          try {
            return normalizeLines(JSON.parse(raw));
          } catch {
            return [];
          }
        },
        renderHTML: (attributes: Record<string, unknown>) => {
          const lines = normalizeLines(attributes.lines);
          return {
            "data-lines": JSON.stringify(lines),
          };
        },
      },
      locked: {
        default: false,
        parseHTML: (element: HTMLElement) =>
          element.getAttribute("data-locked") === "true",
        renderHTML: (attributes: Record<string, unknown>) => ({
          "data-locked": attributes.locked ? "true" : "false",
        }),
      },
    };
  },

  parseHTML() {
    return [{ tag: 'div[data-type="freehand-drawing"]' }];
  },

  renderHTML({ HTMLAttributes }) {
    const attrs = { ...HTMLAttributes } as Record<string, unknown>;
    const lines = normalizeLines(attrs.lines);
    delete attrs.lines;
    delete attrs.locked;

    return [
      "div",
      mergeAttributes(attrs, {
        "data-type": "freehand-drawing",
        class: "editor-freehand-drawing",
        contenteditable: "false",
      }),
      [
        "svg",
        {
          xmlns: "http://www.w3.org/2000/svg",
          viewBox: `0 0 ${BOARD_WIDTH} ${BOARD_HEIGHT}`,
          width: "100%",
          height: String(BOARD_HEIGHT),
          style:
            "background:var(--card);border:1px solid var(--border);border-radius:10px;",
          "aria-label": "Freehand drawing",
        },
        ...lines.map((line) => lineDomSpec(line)),
      ],
    ];
  },

  addCommands() {
    return {
      insertFreehandDrawing:
        () =>
        ({ commands }) =>
          commands.insertContent({
            type: this.name,
            attrs: { lines: [], locked: false },
          }),
    };
  },

  addNodeView() {
    return VueNodeViewRenderer(FreehandDrawingNodeView);
  },
});

export default FreehandDrawingNode;
