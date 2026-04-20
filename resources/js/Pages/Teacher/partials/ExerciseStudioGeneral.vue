<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { cmsTranslate as __ } from "@/lib/i18n";
import axios from "axios";
import { computed, nextTick, onBeforeUnmount, onMounted, onUpdated, ref, watch } from "vue";
import { EditorContent, useEditor } from "@tiptap/vue-3";
import StarterKit from "@tiptap/starter-kit";
import Placeholder from "@tiptap/extension-placeholder";
import Link from "@tiptap/extension-link";
import TextAlign from "@tiptap/extension-text-align";
import Underline from "@tiptap/extension-underline";
import { Extension, Mark, Node, mergeAttributes } from "@tiptap/core";
import "mathlive";
import { MathfieldElement } from "mathlive";
import {
  Download,
  ImagePlus,
  IndentDecrease,
  IndentIncrease,
  Loader2,
  Redo2,
  RefreshCw,
  Sparkles,
  Underline as UnderlineIcon,
  Undo2,
  WandSparkles,
} from "lucide-vue-next";

type StudioStatus = "idle" | "processing" | "ready" | "failed";
type Difficulty = "easy" | "medium" | "hard";
type Locale = "en" | "hy" | "fr" | "ru";

type ExerciseItem = {
  id: number;
  item_order: number;
  type: string;
  question: string;
  options: string[];
  answer: string;
  explanation?: string | null;
  difficulty?: string | null;
  points: number;
  source: string;
};

type Segment = {
  type: "text" | "math";
  value: string;
};

type MathfieldLike = HTMLElement & {
  value?: string;
  getValue?: (format?: string) => string;
  setValue?: (value: string) => void;
};

MathfieldElement.fontsDirectory = "/fonts/mathlive";
MathfieldElement.soundsDirectory = "/sounds/mathlive";

type ExerciseDocument = {
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

type GeometryTemplate = {
  key: string;
  category: "2d" | "3d";
  labelKey: string;
  lines: string[];
};

type DrawnGeometryShape = {
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

declare module "@tiptap/core" {
  interface Commands<ReturnType> {
    blockFormatting: {
      indent: () => ReturnType;
      outdent: () => ReturnType;
      setLineHeight: (lineHeight: string) => ReturnType;
      unsetLineHeight: () => ReturnType;
    };
    imageBlock: {
      setImage: (options: { src: string; alt?: string; title?: string }) => ReturnType;
    };
  }
}

const EDITOR_INDENT_STEP = 2;
const EDITOR_MAX_INDENT = 12;
const LINE_HEIGHT_VALUES = ["1", "1.2", "1.5", "2"] as const;
type LineHeightValue = (typeof LINE_HEIGHT_VALUES)[number];

function parseIndentFromStyle(styleValue: string | null | undefined): number {
  if (!styleValue) return 0;

  const match = styleValue.match(/margin-left\s*:\s*([0-9.]+)\s*(em|px)/i);
  if (!match) return 0;

  const amount = Number(match[1] ?? 0);
  const unit = String(match[2] ?? "em").toLowerCase();
  if (!Number.isFinite(amount) || amount <= 0) return 0;

  const emValue = unit === "px" ? amount / 16 : amount;
  const indent = Math.round(emValue / EDITOR_INDENT_STEP);
  return Math.max(0, Math.min(EDITOR_MAX_INDENT, indent));
}

function parseIndentValue(value: unknown): number {
  const parsed = Number(value ?? 0);
  if (!Number.isFinite(parsed) || parsed <= 0) return 0;
  return Math.max(0, Math.min(EDITOR_MAX_INDENT, Math.round(parsed)));
}

function normalizeLineHeight(value: unknown): string | null {
  const normalized = String(value ?? "").trim();
  if (!normalized) return null;
  return LINE_HEIGHT_VALUES.includes(normalized as LineHeightValue)
    ? normalized
    : null;
}

function updateBlockAttributes(
  props: { state: any; dispatch?: ((tr: any) => void) | undefined },
  types: string[],
  updater: (attrs: Record<string, unknown>) => Record<string, unknown>,
): boolean {
  const { state, dispatch } = props;
  let tr = state.tr;
  let changed = false;
  const { from, to } = state.selection;

  state.doc.nodesBetween(from, to, (node: any, pos: number) => {
    if (!types.includes(node.type.name)) return;

    const currentAttrs = { ...(node.attrs ?? {}) };
    const nextAttrs = updater(currentAttrs);
    if (JSON.stringify(currentAttrs) === JSON.stringify(nextAttrs)) return;

    tr = tr.setNodeMarkup(pos, undefined, nextAttrs);
    changed = true;
  });

  if (changed && dispatch) {
    dispatch(tr);
  }

  return changed;
}

const ImageBlockNode = Node.create({
  name: "image",
  group: "block",
  draggable: true,
  selectable: true,
  atom: true,
  addAttributes() {
    return {
      src: { default: null },
      alt: { default: null },
      title: { default: null },
    };
  },
  parseHTML() {
    return [{ tag: "img[src]:not([data-geometry-drawing])" }];
  },
  renderHTML({ HTMLAttributes }) {
    return [
      "img",
      mergeAttributes(HTMLAttributes, {
        style:
          "max-width:100%;height:auto;border-radius:10px;margin:8px 0;border:1px solid rgba(148,163,184,.35);",
      }),
    ];
  },
  addCommands() {
    return {
      setImage:
        (options) =>
        ({ commands }) =>
          commands.insertContent({
            type: this.name,
            attrs: options,
          }),
    };
  },
});

const BlockFormattingExtension = Extension.create({
  name: "blockFormatting",
  addOptions() {
    return {
      types: ["paragraph", "heading"],
    };
  },
  addGlobalAttributes() {
    return [
      {
        types: this.options.types,
        attributes: {
          indent: {
            default: null,
            parseHTML: (element: HTMLElement) => {
              const fromData = parseIndentValue(element.getAttribute("data-indent"));
              if (fromData > 0) return fromData;
              return parseIndentFromStyle(element.getAttribute("style"));
            },
            renderHTML: (attributes: Record<string, unknown>) => {
              const indent = parseIndentValue(attributes.indent);
              if (indent <= 0) return {};
              return { "data-indent": String(indent) };
            },
          },
          lineHeight: {
            default: null,
            parseHTML: (element: HTMLElement) => {
              const fromData = normalizeLineHeight(element.getAttribute("data-line-height"));
              if (fromData) return fromData;
              return normalizeLineHeight(element.style.lineHeight || null);
            },
            renderHTML: (attributes: Record<string, unknown>) => {
              const lineHeight = normalizeLineHeight(attributes.lineHeight);
              if (!lineHeight) return {};
              return { "data-line-height": lineHeight };
            },
          },
        },
      },
    ];
  },
  addCommands() {
    return {
      indent:
        () =>
        ({ state, dispatch }) =>
          updateBlockAttributes(
            { state, dispatch },
            this.options.types,
            (attrs) => {
              const current = parseIndentValue(attrs.indent);
              const next = Math.min(EDITOR_MAX_INDENT, current + 1);
              return { ...attrs, indent: next > 0 ? next : null };
            },
          ),
      outdent:
        () =>
        ({ state, dispatch }) =>
          updateBlockAttributes(
            { state, dispatch },
            this.options.types,
            (attrs) => {
              const current = parseIndentValue(attrs.indent);
              const next = Math.max(0, current - 1);
              return { ...attrs, indent: next > 0 ? next : null };
            },
          ),
      setLineHeight:
        (lineHeight: string) =>
        ({ state, dispatch }) => {
          const normalized = normalizeLineHeight(lineHeight);
          if (!normalized) return false;
          return updateBlockAttributes(
            { state, dispatch },
            this.options.types,
            (attrs) => ({ ...attrs, lineHeight: normalized }),
          );
        },
      unsetLineHeight:
        () =>
        ({ state, dispatch }) =>
          updateBlockAttributes(
            { state, dispatch },
            this.options.types,
            (attrs) => ({ ...attrs, lineHeight: null }),
          ),
    };
  },
});

const GeometryDrawingNode = Node.create({
  name: "geometryDrawing",
  group: "block",
  atom: true,
  selectable: true,
  draggable: true,
  addAttributes() {
    return {
      src: { default: null },
      alt: { default: "Geometry drawing" },
      geometrySvg: { default: null },
    };
  },
  parseHTML() {
    return [{ tag: "img[data-geometry-drawing]" }];
  },
  renderHTML({ HTMLAttributes }) {
    const attrs = { ...HTMLAttributes } as Record<string, unknown>;
    if (typeof attrs.geometrySvg === "string" && attrs.geometrySvg.length > 0) {
      attrs["data-geometry-svg"] = attrs.geometrySvg;
    }
    delete attrs.geometrySvg;

    return [
      "img",
      mergeAttributes(attrs, {
        "data-geometry-drawing": "true",
        style:
          "max-width:100%;height:auto;border-radius:10px;margin:8px 0;border:1px solid rgba(148,163,184,.35);",
      }),
    ];
  },
});

const MathInlineNode = Node.create({
  name: "mathInline",
  group: "inline",
  inline: true,
  atom: true,
  selectable: true,
  addAttributes() {
    return {
      latex: { default: "" },
    };
  },
  parseHTML() {
    return [{ tag: "span[data-editor-math]" }];
  },
  renderHTML({ HTMLAttributes }) {
    const attrs = { ...HTMLAttributes } as Record<string, unknown>;
    if (typeof attrs.latex === "string") {
      attrs["data-latex"] = attrs.latex;
    }
    delete attrs.latex;

    return [
      "span",
      mergeAttributes(attrs, {
        "data-editor-math": "true",
        class: "inline-math editor-math-node",
        contenteditable: "false",
      }),
    ];
  },
});

const InlineStyleMark = Mark.create({
  name: "inlineStyle",

  addAttributes() {
    return {
      fontFamily: {
        default: null,
        parseHTML: (element) => (element as HTMLElement).style.fontFamily || null,
      },
      fontSize: {
        default: null,
        parseHTML: (element) => (element as HTMLElement).style.fontSize || null,
      },
    };
  },

  parseHTML() {
    return [{ tag: "span[style]" }];
  },

  renderHTML({ HTMLAttributes }) {
    const attrs = { ...HTMLAttributes } as Record<string, string | null | undefined>;
    const styleParts: string[] = [];

    if (attrs.fontFamily) {
      styleParts.push(`font-family:${attrs.fontFamily}`);
    }
    if (attrs.fontSize) {
      styleParts.push(`font-size:${attrs.fontSize}`);
    }

    delete attrs.fontFamily;
    delete attrs.fontSize;
    if (styleParts.length > 0) {
      attrs.style = styleParts.join("; ");
    } else {
      delete attrs.style;
    }

    return ["span", attrs, 0];
  },
});

const topic = ref("");
const grade = ref("");
const difficulty = ref<Difficulty>("medium");
const language = ref<Locale>("en");
const count = ref(10);
const instructions = ref("");
const replaceItems = ref(true);
const showCorrectAnswers = ref(true);
const selectedGeometryKey = ref("square");
const selectedFontFamily = ref("default");
const selectedFontSize = ref("16px");
const selectedLineHeight = ref<LineHeightValue | "default">("default");
const formulaInput = ref("\\int_0^1 x^2\\,dx");
const formulaFieldRef = ref<MathfieldLike | null>(null);
const imageInputRef = ref<HTMLInputElement | null>(null);
const showMathPanel = ref(false);
const showCanvasPanel = ref(false);

const fontFamilyOptions = [
  { value: "default", label: "Default" },
  { value: "Arial, sans-serif", label: "Arial" },
  { value: "'Times New Roman', serif", label: "Times New Roman" },
  { value: "Georgia, serif", label: "Georgia" },
  { value: "'Courier New', monospace", label: "Courier New" },
];

const fontSizeOptions = [
  { value: "12px", label: "12" },
  { value: "14px", label: "14" },
  { value: "16px", label: "16" },
  { value: "18px", label: "18" },
  { value: "20px", label: "20" },
  { value: "24px", label: "24" },
  { value: "28px", label: "28" },
  { value: "32px", label: "32" },
];

const lineHeightOptions: Array<{ value: LineHeightValue; label: string }> = [
  { value: "1", label: "1.0" },
  { value: "1.2", label: "1.2" },
  { value: "1.5", label: "1.5" },
  { value: "2", label: "2.0" },
];

const geometryTemplates: GeometryTemplate[] = [
  {
    key: "square",
    category: "2d",
    labelKey: "Square",
    lines: ["Given: a = ...", "Perimeter: P = 4a", "Area: S = a^2"],
  },
  {
    key: "rectangle",
    category: "2d",
    labelKey: "Rectangle",
    lines: ["Given: l = ..., w = ...", "Perimeter: P = 2(l + w)", "Area: S = lw"],
  },
  {
    key: "triangle",
    category: "2d",
    labelKey: "Triangle",
    lines: ["Given: b = ..., h = ...", "Area: S = (bh)/2", "Perimeter: P = a + b + c"],
  },
  {
    key: "circle",
    category: "2d",
    labelKey: "Circle",
    lines: ["Given: r = ...", "Circumference: C = 2pi*r", "Area: S = pi*r^2"],
  },
  {
    key: "trapezoid",
    category: "2d",
    labelKey: "Trapezoid",
    lines: ["Given: a = ..., b = ..., h = ...", "Area: S = ((a + b)h)/2", "Perimeter: P = a + b + c + d"],
  },
  {
    key: "cube",
    category: "3d",
    labelKey: "Cube",
    lines: ["Given: a = ...", "Volume: V = a^3", "Surface area: S = 6a^2"],
  },
  {
    key: "cuboid",
    category: "3d",
    labelKey: "Cuboid",
    lines: ["Given: l = ..., w = ..., h = ...", "Volume: V = lwh", "Surface area: S = 2(lw + lh + wh)"],
  },
  {
    key: "cylinder",
    category: "3d",
    labelKey: "Cylinder",
    lines: ["Given: r = ..., h = ...", "Volume: V = pi*r^2*h", "Surface area: S = 2pi*r(r + h)"],
  },
  {
    key: "cone",
    category: "3d",
    labelKey: "Cone",
    lines: ["Given: r = ..., h = ..., l = ...", "Volume: V = (pi*r^2*h)/3", "Surface area: S = pi*r(r + l)"],
  },
  {
    key: "sphere",
    category: "3d",
    labelKey: "Sphere",
    lines: ["Given: r = ...", "Volume: V = (4/3)pi*r^3", "Surface area: S = 4pi*r^2"],
  },
  {
    key: "pyramid",
    category: "3d",
    labelKey: "Pyramid",
    lines: ["Given: base area B = ..., h = ...", "Volume: V = (Bh)/3", "Surface area: S = B + lateral area"],
  },
];

const boardWidth = 794;
const minBoardHeight = 260;
const paintBoardRef = ref<SVGSVGElement | null>(null);
const drawnShapes = ref<DrawnGeometryShape[]>([]);
const selectedDrawnId = ref<number | null>(null);
const drawingCounter = ref(1);
const draggingShapeId = ref<number | null>(null);
const dragOffset = ref({ x: 0, y: 0 });
const boardHeight = computed(() => {
  if (drawnShapes.value.length === 0) return minBoardHeight;
  const lowestPoint = Math.max(
    ...drawnShapes.value.map((shape) => shape.y + shape.height / 2 + 40),
  );
  return Math.max(minBoardHeight, Math.ceil(lowestPoint));
});

const documentId = ref<number | null>(null);
const generationId = ref<number | null>(null);
const generationStatus = ref<StudioStatus>("idle");
const generationError = ref<string | null>(null);
const loadingDocument = ref(false);
const generating = ref(false);
const saving = ref(false);
const publishing = ref(false);
const downloadingPdf = ref(false);
const uploadingImage = ref(false);
const regeneratingItemId = ref<number | null>(null);
const insertingDrawing = ref(false);
const dirty = ref(false);
const lastSavedAt = ref<string | null>(null);

const items = ref<ExerciseItem[]>([]);
let pollTimer: ReturnType<typeof setInterval> | null = null;
let autosaveTimer: ReturnType<typeof setInterval> | null = null;
let detachFormulaInputListener: (() => void) | null = null;

const canGenerate = computed(() => topic.value.trim().length > 0 && !generating.value);
const canSave = computed(() => !saving.value && !!editor.value);
const canPublish = computed(() => !!documentId.value && !publishing.value);
const canDownloadPdf = computed(() => !!editor.value && !downloadingPdf.value);
const twoDGeometryTemplates = computed(() =>
  geometryTemplates.filter((item) => item.category === "2d"),
);
const threeDGeometryTemplates = computed(() =>
  geometryTemplates.filter((item) => item.category === "3d"),
);
const selectedGeometryTemplate = computed(
  () => geometryTemplates.find((item) => item.key === selectedGeometryKey.value) ?? null,
);
const selectedDrawnShape = computed(
  () => drawnShapes.value.find((item) => item.id === selectedDrawnId.value) ?? null,
);
const selectedShapeIsUniform = computed(() => {
  const kind = selectedDrawnShape.value?.kind ?? "";
  return kind === "square" || kind === "circle" || kind === "sphere" || kind === "cube";
});
const selectedShapeMaxWidth = computed(() => {
  return Math.max(30, boardWidth - 8);
});
const selectedShapeMaxHeight = computed(() => {
  return Math.max(120, Math.ceil(boardHeight.value * 2));
});
const selectedShapeMaxSize = computed(() =>
  Math.max(30, Math.min(selectedShapeMaxWidth.value, selectedShapeMaxHeight.value)),
);

const editor = useEditor({
  extensions: [
    StarterKit.configure({
      heading: { levels: [1, 2, 3] },
    }),
    Underline,
    BlockFormattingExtension,
    InlineStyleMark,
    GeometryDrawingNode,
    ImageBlockNode,
    MathInlineNode,
    Placeholder.configure({
      placeholder: __("Write, edit, and organize your exercises here..."),
    }),
    Link.configure({
      openOnClick: false,
      autolink: true,
    }),
    TextAlign.configure({
      types: ["heading", "paragraph"],
    }),
  ],
  content: {
    type: "doc",
    content: [
      {
        type: "paragraph",
        content: [],
      },
    ],
  },
  editorProps: {
    attributes: {
      class:
        "exercise-studio-editor rounded-xl border border-slate-200 bg-white text-sm leading-7 text-slate-800 focus:outline-none dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-100",
    },
  },
  onCreate: () => {
    syncLineHeightSelection();
  },
  onSelectionUpdate: () => {
    syncLineHeightSelection();
  },
  onUpdate: () => {
    dirty.value = true;
    syncLineHeightSelection();
  },
});

function startPolling(gId: number) {
  stopPolling();
  generationId.value = gId;
  generationStatus.value = "processing";

  void checkGeneration();
  pollTimer = setInterval(() => {
    void checkGeneration();
  }, 2500);
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
}

async function checkGeneration() {
  if (!generationId.value) return;

  try {
    const response = await axios.get(
      `/api/teacher/exercises/generations/${generationId.value}`,
    );

    const status = (response.data?.generation?.status ?? "processing") as StudioStatus;
    generationStatus.value = status;

    if (status === "ready") {
      generating.value = false;
      stopPolling();
      generationError.value = null;
      const documentPayload = response.data?.document as ExerciseDocument | null;
      if (documentPayload) {
        applyDocument(documentPayload);
      }
    }

    if (status === "failed") {
      generating.value = false;
      stopPolling();
      generationError.value =
        response.data?.generation?.error || "Generation failed. Please try again.";
    }
  } catch (error: any) {
    generating.value = false;
    stopPolling();
    generationStatus.value = "failed";
    generationError.value =
      error?.response?.data?.message ||
      error?.message ||
      "Failed to check generation status.";
  }
}

async function generateExercises() {
  if (!canGenerate.value) return;

  generating.value = true;
  generationError.value = null;

  try {
    const response = await axios.post("/api/teacher/exercises/generate", {
      topic: topic.value.trim(),
      grade: grade.value.trim() || null,
      difficulty: difficulty.value,
      count: count.value,
      language: language.value,
      instructions: instructions.value.trim() || null,
      replace_items: replaceItems.value,
      document_id: documentId.value,
    });

    const gId = Number(response.data?.generation_id || 0);
    if (!gId) {
      generating.value = false;
      generationStatus.value = "failed";
      generationError.value = "Generation id was not returned.";
      return;
    }

    const nextDocumentId = Number(response.data?.document_id || 0);
    if (nextDocumentId > 0) {
      documentId.value = nextDocumentId;
    }

    startPolling(gId);
  } catch (error: any) {
    generating.value = false;
    generationStatus.value = "failed";
    generationError.value =
      error?.response?.data?.message ||
      error?.message ||
      "Failed to start generation.";
  }
}

async function saveDraft(showSuccessTimestamp = true) {
  if (!editor.value || !canSave.value) return;

  saving.value = true;
  generationError.value = null;

  try {
    const response = await axios.post("/api/teacher/exercises/save-draft", {
      document_id: documentId.value,
      title: topic.value.trim() || "Untitled exercise document",
      language: language.value,
      content_json: editor.value.getJSON(),
      content_html: editor.value.getHTML(),
      meta: {
        grade: grade.value.trim() || null,
        difficulty: difficulty.value,
      },
      items: items.value.map((item, idx) => ({
        id: item.id,
        item_order: idx + 1,
        type: item.type,
        question: item.question,
        options: item.options,
        answer: item.answer,
        explanation: item.explanation,
        difficulty: item.difficulty,
        points: item.points,
        source: item.source,
      })),
    });

    const documentPayload = response.data?.document as ExerciseDocument;
    applyDocument(documentPayload);
    dirty.value = false;

    if (showSuccessTimestamp) {
      lastSavedAt.value = new Date().toLocaleTimeString();
    }
  } catch (error: any) {
    generationError.value =
      error?.response?.data?.message || error?.message || "Failed to save draft.";
  } finally {
    saving.value = false;
  }
}

async function publishDocument() {
  if (!documentId.value || !editor.value || publishing.value) return;

  publishing.value = true;
  generationError.value = null;

  try {
    const response = await axios.post("/api/teacher/exercises/publish", {
      document_id: documentId.value,
      title: topic.value.trim() || "Untitled exercise document",
      language: language.value,
      content_json: editor.value.getJSON(),
      content_html: editor.value.getHTML(),
      meta: {
        grade: grade.value.trim() || null,
        difficulty: difficulty.value,
      },
    });

    const documentPayload = response.data?.document as ExerciseDocument;
    applyDocument(documentPayload);
    dirty.value = false;
  } catch (error: any) {
    generationError.value =
      error?.response?.data?.message || error?.message || "Failed to publish document.";
  } finally {
    publishing.value = false;
  }
}

async function extractPdfErrorMessage(error: any): Promise<string> {
  const responseData = error?.response?.data;

  if (responseData instanceof Blob) {
    try {
      const text = await responseData.text();
      const parsed = JSON.parse(text);
      if (typeof parsed?.message === "string" && parsed.message.trim() !== "") {
        return parsed.message;
      }
    } catch {
      // ignore blob parsing failures and fall back below
    }
  }

  if (typeof responseData?.message === "string" && responseData.message.trim() !== "") {
    return responseData.message;
  }

  if (typeof error?.message === "string" && error.message.trim() !== "") {
    return error.message;
  }

  return __("Failed to export PDF.");
}

async function downloadPdf() {
  if (!editor.value || downloadingPdf.value) return;

  downloadingPdf.value = true;
  generationError.value = null;

  try {
    const response = await axios.post(
      "/api/teacher/exercises/pdf",
      {
        document_id: documentId.value,
        title: topic.value.trim() || __("Exercise Document"),
        content_html: editor.value.getHTML(),
        language: language.value,
        show_answers: showCorrectAnswers.value,
      },
      {
        responseType: "blob",
      },
    );

    const contentDisposition = String(response.headers["content-disposition"] ?? "");
    const filenameMatch = contentDisposition.match(/filename\*?=(?:UTF-8''|")?([^\";]+)/i);
    const filename = filenameMatch?.[1]
      ? decodeURIComponent(filenameMatch[1].replace(/"/g, "").trim())
      : "teacher_exercises.pdf";

    const blob = new Blob([response.data], { type: "application/pdf" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  } catch (error: any) {
    generationError.value = await extractPdfErrorMessage(error);
  } finally {
    downloadingPdf.value = false;
  }
}

async function regenerateItem(item: ExerciseItem) {
  if (!documentId.value || regeneratingItemId.value) return;
  regeneratingItemId.value = item.id;
  generationError.value = null;

  try {
    const response = await axios.post("/api/teacher/exercises/regenerate-item", {
      document_id: documentId.value,
      item_id: item.id,
      topic: topic.value.trim() || item.question,
      difficulty: difficulty.value,
      language: language.value,
      instructions: instructions.value.trim() || null,
    });

    const next = response.data?.item as ExerciseItem;
    const index = items.value.findIndex((current) => current.id === item.id);
    if (index >= 0) {
      items.value[index] = {
        ...items.value[index],
        ...sanitizeExerciseItem(next),
      };
      const doc = buildEditorDocFromItems();
      editor.value?.commands.setContent(doc, { emitUpdate: true });
      dirty.value = true;
    }
  } catch (error: any) {
    generationError.value =
      error?.response?.data?.message ||
      error?.message ||
      "Failed to regenerate item.";
  } finally {
    regeneratingItemId.value = null;
  }
}

function buildEditorDocFromItems(): Record<string, unknown> {
  const content: any[] = [];

  items.value
    .slice()
    .sort((a, b) => a.item_order - b.item_order)
    .forEach((item, idx) => {
      content.push({
        type: "heading",
        attrs: { level: 3 },
        content: [{ type: "text", text: `${__("Exercise")} ${idx + 1}` }],
      });

      content.push({
        type: "paragraph",
        content: buildInlineContent(item.question),
      });

      if (Array.isArray(item.options) && item.options.length > 0) {
        content.push({
          type: "orderedList",
          attrs: { start: 1 },
          content: item.options.map((option) => ({
            type: "listItem",
            content: [
              {
                type: "paragraph",
                content: buildInlineContent(option),
              },
            ],
          })),
        });
      }

      if (showCorrectAnswers.value) {
        content.push({
          type: "paragraph",
          content: [
            { type: "text", text: `${__("Answer")}: ` },
            ...buildInlineContent(item.answer),
          ],
        });
      }

      if (showCorrectAnswers.value && item.explanation) {
        content.push({
          type: "paragraph",
          content: [
            {
              type: "text",
              text: `${__("Explanation")}: `,
            },
            ...buildInlineContent(item.explanation ?? ""),
          ],
        });
      }
    });

  return {
    type: "doc",
    content,
  };
}

function applyDocument(documentPayload: ExerciseDocument) {
  documentId.value = documentPayload.id;
  topic.value = documentPayload.title || topic.value;
  language.value = (documentPayload.language || language.value) as Locale;
  items.value = Array.isArray(documentPayload.items)
    ? documentPayload.items.map((item) => sanitizeExerciseItem(item))
    : [];

  const meta = (documentPayload.meta ?? {}) as Record<string, unknown>;
  if (typeof meta.grade === "string" && grade.value.trim() === "") {
    grade.value = meta.grade;
  }
  if (
    typeof meta.difficulty === "string" &&
    ["easy", "medium", "hard"].includes(meta.difficulty.toLowerCase())
  ) {
    difficulty.value = meta.difficulty.toLowerCase() as Difficulty;
  }

  const jsonContent = documentPayload.content_json;
  if (editor.value) {
    const jsonSerialized = jsonContent ? JSON.stringify(jsonContent) : "";
    const hasBrokenMediaMarkdown =
      jsonSerialized.includes("![") ||
      jsonSerialized.includes("](") ||
      jsonSerialized.includes("http://") ||
      jsonSerialized.includes("https://");

    if (
      jsonContent &&
      Object.keys(jsonContent).length > 0 &&
      !hasBrokenMediaMarkdown
    ) {
      editor.value.commands.setContent(sanitizeEditorJsonText(jsonContent), { emitUpdate: false });
    } else {
      editor.value.commands.setContent(buildEditorDocFromItems(), { emitUpdate: false });
    }
  }
}

function sanitizeExerciseItem(item: ExerciseItem): ExerciseItem {
  return {
    ...item,
    question: String(item.question ?? ""),
    options: Array.isArray(item.options)
      ? item.options.map((option) => String(option ?? ""))
      : [],
    answer: String(item.answer ?? ""),
    explanation: item.explanation ? String(item.explanation) : item.explanation,
  };
}

function sanitizeEditorJsonText(value: unknown): unknown {
  return convertLatexTextNodesToMathNodes(value);
}

function splitMath(text: string): Segment[] {
  if (!text) return [];

  const segments: Segment[] = [];
  const regex =
    /\$\$([\s\S]+?)\$\$|\$([^$]+)\$|\\\((.+?)\\\)|\\\[(.+?)\\\]|\\begin\{cases\}[\s\S]+?\\end\{cases\}/g;

  let lastIndex = 0;
  let match: RegExpExecArray | null;

  while ((match = regex.exec(text)) !== null) {
    if (match.index > lastIndex) {
      segments.push({
        type: "text",
        value: text.slice(lastIndex, match.index),
      });
    }

    const latex =
      match[1] ?? match[2] ?? match[3] ?? match[4] ?? (match[0] ?? "").trim();
    if (latex) {
      segments.push({ type: "math", value: latex });
    }

    lastIndex = match.index + match[0].length;
  }

  if (lastIndex < text.length) {
    segments.push({ type: "text", value: text.slice(lastIndex) });
  }

  return segments;
}

function buildInlineContent(text: string | null | undefined): Array<Record<string, unknown>> {
  const source = String(text ?? "");
  if (source.trim() === "") {
    return [{ type: "text", text: "" }];
  }

  const segments = splitMath(source);
  const inline: Array<Record<string, unknown>> = [];

  for (const segment of segments) {
    if (segment.type === "text") {
      if (segment.value.length > 0) {
        inline.push({ type: "text", text: segment.value });
      }
      continue;
    }

    inline.push({
      type: "mathInline",
      attrs: { latex: segment.value },
    });
  }

  if (inline.length === 0) {
    inline.push({ type: "text", text: source });
  }

  return inline;
}

function convertLatexTextNodesToMathNodes(value: unknown): unknown {
  if (Array.isArray(value)) {
    const next: unknown[] = [];
    for (const entry of value) {
      const converted = convertLatexTextNodesToMathNodes(entry);
      if (Array.isArray(converted)) {
        next.push(...converted);
      } else {
        next.push(converted);
      }
    }
    return next;
  }

  if (value && typeof value === "object") {
    const source = value as Record<string, unknown>;
    const type = typeof source.type === "string" ? source.type : "";

    if (type === "text" && typeof source.text === "string") {
      const content = buildInlineContent(source.text);
      if (
        content.length === 1 &&
        content[0].type === "text" &&
        String(content[0].text ?? "") === source.text
      ) {
        return source;
      }
      return content;
    }

    const next: Record<string, unknown> = {};
    for (const [key, raw] of Object.entries(source)) {
      next[key] = convertLatexTextNodesToMathNodes(raw);
    }
    return next;
  }

  return value;
}

function injectMath(el: HTMLElement, latex: string) {
  el.innerHTML = "";
  const mf = document.createElement("math-field") as any;
  mf.setAttribute("read-only", "");
  mf.style.cssText =
    "background:transparent;border:none;pointer-events:none;display:inline-block;color:inherit;";
  el.appendChild(mf);

  requestAnimationFrame(() => {
    if (typeof mf.setValue === "function") mf.setValue(latex);
    else mf.value = latex;
  });
}

function renderMath() {
  document.querySelectorAll<HTMLElement>(".inline-math").forEach((el) => {
    if (el.dataset.latex && !el.querySelector("math-field")) {
      injectMath(el, el.dataset.latex);
    }
  });
}

function setMathFieldValue(field: MathfieldLike, value: string) {
  if (typeof field.setValue === "function") {
    field.setValue(value);
  } else {
    field.value = value;
  }
}

function syncFormulaInputFromField() {
  const field = formulaFieldRef.value;
  if (!field) return;

  if (typeof field.getValue === "function") {
    formulaInput.value = field.getValue("latex");
  } else {
    formulaInput.value = field.value ?? "";
  }
}

function mountFormulaInput() {
  detachFormulaInputListener?.();
  detachFormulaInputListener = null;

  const field = formulaFieldRef.value;
  if (!field) return;

  setMathFieldValue(field, formulaInput.value);
  const onInput = () => syncFormulaInputFromField();
  field.addEventListener("input", onInput);
  detachFormulaInputListener = () => field.removeEventListener("input", onInput);
}

function normalizeInlineLatex(value: string): string {
  let normalized = value.trim();
  if (!normalized) return "";

  if (normalized.startsWith("$$") && normalized.endsWith("$$") && normalized.length > 4) {
    normalized = normalized.slice(2, -2).trim();
  } else if (normalized.startsWith("$") && normalized.endsWith("$") && normalized.length > 2) {
    normalized = normalized.slice(1, -1).trim();
  }

  return normalized;
}

function insertFormulaInline() {
  if (!editor.value) return;

  const latex = normalizeInlineLatex(formulaInput.value);
  if (!latex) return;

  editor.value
    .chain()
    .focus()
    .insertContent({
      type: "mathInline",
      attrs: { latex },
    })
    .run();
  dirty.value = true;
  nextTick(renderMath);
}

function insertFormulaBlock() {
  if (!editor.value) return;

  const latex = normalizeInlineLatex(formulaInput.value);
  if (!latex) return;

  editor.value
    .chain()
    .focus()
    .insertContent({
      type: "paragraph",
      content: [{ type: "mathInline", attrs: { latex } }],
    })
    .run();
  dirty.value = true;
  nextTick(renderMath);
}

async function loadDocument(id: number) {
  loadingDocument.value = true;
  generationError.value = null;
  try {
    const response = await axios.get(`/api/teacher/exercises/${id}`);
    const documentPayload = response.data?.document as ExerciseDocument;
    if (documentPayload) {
      applyDocument(documentPayload);
      generationStatus.value = "ready";
      dirty.value = false;
    }
  } catch (error: any) {
    generationError.value =
      error?.response?.data?.message || error?.message || "Failed to load document.";
  } finally {
    loadingDocument.value = false;
  }
}

const canUndo = computed(
  () => !!editor.value && editor.value.can().chain().focus().undo().run(),
);
const canRedo = computed(
  () => !!editor.value && editor.value.can().chain().focus().redo().run(),
);
const canIndent = computed(
  () => !!editor.value && editor.value.can().chain().focus().indent().run(),
);
const canOutdent = computed(
  () => !!editor.value && editor.value.can().chain().focus().outdent().run(),
);

function isEditorActive(name: string, attrs?: Record<string, unknown>) {
  return !!editor.value?.isActive(name, attrs);
}

function isTextAlignActive(alignment: "left" | "center" | "right" | "justify") {
  return !!editor.value?.isActive({ textAlign: alignment });
}

function syncLineHeightSelection() {
  if (!editor.value) {
    selectedLineHeight.value = "default";
    return;
  }

  const paragraphAttrs = (editor.value.getAttributes("paragraph") ?? {}) as Record<string, unknown>;
  const headingAttrs = (editor.value.getAttributes("heading") ?? {}) as Record<string, unknown>;
  const raw = paragraphAttrs.lineHeight ?? headingAttrs.lineHeight ?? null;
  const normalized = normalizeLineHeight(raw);
  selectedLineHeight.value = normalized ?? "default";
}

function applySelectedLineHeight() {
  if (!editor.value) return;

  if (selectedLineHeight.value === "default") {
    editor.value.chain().focus().unsetLineHeight().run();
    return;
  }

  editor.value.chain().focus().setLineHeight(selectedLineHeight.value).run();
}

function toggleMathPanel() {
  showMathPanel.value = !showMathPanel.value;
  if (showMathPanel.value) {
    showCanvasPanel.value = false;
    nextTick(mountFormulaInput);
  }
}

function toggleCanvasPanel() {
  showCanvasPanel.value = !showCanvasPanel.value;
  if (showCanvasPanel.value) {
    showMathPanel.value = false;
  }
}

function openImagePicker() {
  imageInputRef.value?.click();
}

async function uploadAndInsertImage(file: File) {
  if (!editor.value || uploadingImage.value) return;

  uploadingImage.value = true;
  generationError.value = null;

  try {
    const form = new FormData();
    form.append("image", file);

    const response = await axios.post("/api/teacher/exercises/images", form, {
      headers: {
        "Content-Type": "multipart/form-data",
      },
    });

    const uploadedUrl = String(response.data?.url ?? "").trim();
    if (!uploadedUrl) {
      throw new Error(__("Image upload failed."));
    }

    editor.value
      .chain()
      .focus()
      .setImage({
        src: uploadedUrl,
        alt: file.name || __("Uploaded image"),
      })
      .run();
    dirty.value = true;
  } catch (error: any) {
    generationError.value =
      error?.response?.data?.message ||
      error?.message ||
      __("Image upload failed.");
  } finally {
    uploadingImage.value = false;
  }
}

async function onImageFileChange(event: Event) {
  const target = event.target as HTMLInputElement | null;
  const file = target?.files?.[0];

  if (target) {
    target.value = "";
  }

  if (!file) return;
  await uploadAndInsertImage(file);
}

function runEditorCommand(action: () => void) {
  if (!editor.value) return;
  action();
}

function applyInlineStyle(partial: { fontFamily?: string | null; fontSize?: string | null }) {
  if (!editor.value) return;

  const current = (editor.value.getAttributes("inlineStyle") ?? {}) as {
    fontFamily?: string | null;
    fontSize?: string | null;
  };

  const next = {
    fontFamily: partial.fontFamily !== undefined ? partial.fontFamily : current.fontFamily ?? null,
    fontSize: partial.fontSize !== undefined ? partial.fontSize : current.fontSize ?? null,
  };

  if (!next.fontFamily && !next.fontSize) {
    editor.value.chain().focus().unsetMark("inlineStyle").run();
    return;
  }

  editor.value.chain().focus().setMark("inlineStyle", next).run();
}

function applySelectedFontFamily() {
  if (selectedFontFamily.value === "default") {
    applyInlineStyle({ fontFamily: null });
    return;
  }

  applyInlineStyle({ fontFamily: selectedFontFamily.value });
}

function applySelectedFontSize() {
  applyInlineStyle({ fontSize: selectedFontSize.value });
}

function mountAutosave() {
  if (autosaveTimer) clearInterval(autosaveTimer);
  autosaveTimer = setInterval(() => {
    if (dirty.value && !saving.value && !!documentId.value) {
      void saveDraft(false);
    }
  }, 10000);
}

function insertGeometryTemplate() {
  const last = drawnShapes.value.at(-1);
  if (!last) {
    addSelectedShapeAt(boardWidth / 2, boardHeight.value / 2);
    return;
  }

  const nextX = last.x + 42;
  const nextY = last.y + 22;
  addSelectedShapeAt(nextX, nextY);
}

function shapeDefaultSize(kind: string): { width: number; height: number } {
  if (kind === "square" || kind === "circle") return { width: 90, height: 90 };
  if (kind === "rectangle" || kind === "trapezoid") return { width: 120, height: 80 };
  if (kind === "triangle") return { width: 110, height: 90 };
  if (kind === "cube") return { width: 100, height: 90 };
  if (kind === "cuboid") return { width: 130, height: 90 };
  if (kind === "cylinder") return { width: 100, height: 120 };
  if (kind === "cone") return { width: 100, height: 120 };
  if (kind === "sphere") return { width: 100, height: 100 };
  if (kind === "pyramid") return { width: 120, height: 100 };
  return { width: 100, height: 80 };
}

function clampValue(value: number, min: number, max: number): number {
  return Math.max(min, Math.min(max, value));
}

function keepShapeInBounds(shape: DrawnGeometryShape) {
  const halfW = shape.width / 2;
  const halfH = shape.height / 2;
  shape.x = clampValue(shape.x, halfW + 4, boardWidth - halfW - 4);
  shape.y = clampValue(shape.y, halfH + 4, boardHeight.value - halfH - 4);
}

function readInputNumber(event: Event, fallback: number): number {
  const target = event.target as HTMLInputElement | null;
  const next = Number(target?.value ?? fallback);
  return Number.isFinite(next) ? next : fallback;
}

function addSelectedShapeAt(x: number, y: number) {
  const kind = selectedGeometryKey.value;
  const size = shapeDefaultSize(kind);
  const halfW = size.width / 2;
  const halfH = size.height / 2;

  const safeX = clampValue(x, halfW + 4, boardWidth - halfW - 4);
  const safeY = clampValue(y, halfH + 4, boardHeight.value - halfH - 4);

  const next: DrawnGeometryShape = {
    id: drawingCounter.value++,
    kind,
    x: safeX,
    y: safeY,
    width: size.width,
    height: size.height,
    fill: "rgba(99, 102, 241, 0.14)",
    stroke: "#4f46e5",
    strokeWidth: 2,
  };

  drawnShapes.value.push(next);
  selectedDrawnId.value = next.id;
  dirty.value = true;
}

function getBoardPoint(event: MouseEvent | PointerEvent) {
  const svg = paintBoardRef.value;
  if (!svg) return { x: boardWidth / 2, y: boardHeight.value / 2 };
  const rect = svg.getBoundingClientRect();
  const x = ((event.clientX - rect.left) / rect.width) * boardWidth;
  const y = ((event.clientY - rect.top) / rect.height) * boardHeight.value;
  return { x, y };
}

function placeShapeOnBoard(event: MouseEvent) {
  const target = event.target as HTMLElement | null;
  if (target?.closest("[data-shape-id]")) return;
  const point = getBoardPoint(event);
  addSelectedShapeAt(point.x, point.y);
}

function selectDrawnShape(id: number) {
  selectedDrawnId.value = id;
}

function startDraggingShape(
  shape: DrawnGeometryShape,
  event: PointerEvent | MouseEvent,
) {
  const point = getBoardPoint(event);
  draggingShapeId.value = shape.id;
  selectedDrawnId.value = shape.id;
  dragOffset.value = {
    x: point.x - shape.x,
    y: point.y - shape.y,
  };
}

function onBoardPointerMove(event: PointerEvent | MouseEvent) {
  if (!draggingShapeId.value) return;
  const shape = drawnShapes.value.find((item) => item.id === draggingShapeId.value);
  if (!shape) return;

  const point = getBoardPoint(event);
  const halfW = shape.width / 2;
  const halfH = shape.height / 2;
  shape.x = clampValue(point.x - dragOffset.value.x, halfW + 4, boardWidth - halfW - 4);
  shape.y = clampValue(
    point.y - dragOffset.value.y,
    halfH + 4,
    boardHeight.value - halfH - 4,
  );
}

function stopDraggingShape() {
  draggingShapeId.value = null;
}

function removeSelectedShape() {
  if (!selectedDrawnId.value) return;
  drawnShapes.value = drawnShapes.value.filter((item) => item.id !== selectedDrawnId.value);
  selectedDrawnId.value = null;
  dirty.value = true;
}

function clearDrawingBoard() {
  drawnShapes.value = [];
  selectedDrawnId.value = null;
  dirty.value = true;
}

function setSelectedShapeSize(value: number) {
  const shape = selectedDrawnShape.value;
  if (!shape) return;
  const safe = clampValue(Math.round(value), 30, selectedShapeMaxSize.value);
  shape.width = safe;
  shape.height = safe;
  keepShapeInBounds(shape);
  dirty.value = true;
}

function setSelectedShapeWidth(value: number) {
  const shape = selectedDrawnShape.value;
  if (!shape) return;
  const safe = clampValue(Math.round(value), 30, selectedShapeMaxWidth.value);
  shape.width = safe;
  if (selectedShapeIsUniform.value) {
    shape.height = safe;
  }
  keepShapeInBounds(shape);
  dirty.value = true;
}

function setSelectedShapeHeight(value: number) {
  const shape = selectedDrawnShape.value;
  if (!shape) return;
  const safe = clampValue(Math.round(value), 30, selectedShapeMaxHeight.value);
  shape.height = safe;
  if (selectedShapeIsUniform.value) {
    shape.width = safe;
  }
  keepShapeInBounds(shape);
  dirty.value = true;
}

function onSelectedSizeInput(event: Event) {
  setSelectedShapeSize(readInputNumber(event, selectedDrawnShape.value?.width ?? 90));
}

function onSelectedWidthInput(event: Event) {
  setSelectedShapeWidth(readInputNumber(event, selectedDrawnShape.value?.width ?? 100));
}

function onSelectedHeightInput(event: Event) {
  setSelectedShapeHeight(readInputNumber(event, selectedDrawnShape.value?.height ?? 80));
}

function isSelectedShape(shapeId: number): boolean {
  return selectedDrawnId.value === shapeId;
}

function shapeSvgMarkup(
  shape: DrawnGeometryShape,
  offsetX = 0,
  offsetY = 0,
): string {
  const stroke = shape.stroke;
  const fill = shape.fill;
  const sw = shape.strokeWidth;
  const x = shape.x - offsetX;
  const y = shape.y - offsetY;
  const w = shape.width;
  const h = shape.height;
  const left = x - w / 2;
  const top = y - h / 2;

  if (shape.kind === "square" || shape.kind === "rectangle") {
    return `<rect x="${left}" y="${top}" width="${w}" height="${h}" fill="${fill}" stroke="${stroke}" stroke-width="${sw}" />`;
  }
  if (shape.kind === "triangle") {
    const points = `${x},${top} ${left},${top + h} ${left + w},${top + h}`;
    return `<polygon points="${points}" fill="${fill}" stroke="${stroke}" stroke-width="${sw}" />`;
  }
  if (shape.kind === "circle" || shape.kind === "sphere") {
    const r = Math.min(w, h) / 2;
    const base = `<circle cx="${x}" cy="${y}" r="${r}" fill="${fill}" stroke="${stroke}" stroke-width="${sw}" />`;
    if (shape.kind === "sphere") {
      return `${base}<ellipse cx="${x}" cy="${y}" rx="${r * 0.75}" ry="${r * 0.3}" fill="none" stroke="${stroke}" stroke-width="1.2" /><ellipse cx="${x}" cy="${y}" rx="${r * 0.35}" ry="${r * 0.95}" fill="none" stroke="${stroke}" stroke-width="1.2" />`;
    }
    return base;
  }
  if (shape.kind === "trapezoid") {
    const topW = w * 0.55;
    const points = `${x - topW / 2},${top} ${x + topW / 2},${top} ${x + w / 2},${top + h} ${x - w / 2},${top + h}`;
    return `<polygon points="${points}" fill="${fill}" stroke="${stroke}" stroke-width="${sw}" />`;
  }
  if (shape.kind === "cube" || shape.kind === "cuboid") {
    const shift = shape.kind === "cube" ? 20 : 24;
    const rw = shape.kind === "cube" ? w * 0.72 : w * 0.78;
    const rh = h * 0.72;
    const x1 = x - rw / 2;
    const y1 = y - rh / 2;
    const x2 = x1 + shift;
    const y2 = y1 - shift;
    return [
      `<rect x="${x1}" y="${y1}" width="${rw}" height="${rh}" fill="${fill}" stroke="${stroke}" stroke-width="${sw}" />`,
      `<rect x="${x2}" y="${y2}" width="${rw}" height="${rh}" fill="none" stroke="${stroke}" stroke-width="${sw}" />`,
      `<line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" stroke="${stroke}" stroke-width="${sw}" />`,
      `<line x1="${x1 + rw}" y1="${y1}" x2="${x2 + rw}" y2="${y2}" stroke="${stroke}" stroke-width="${sw}" />`,
      `<line x1="${x1}" y1="${y1 + rh}" x2="${x2}" y2="${y2 + rh}" stroke="${stroke}" stroke-width="${sw}" />`,
      `<line x1="${x1 + rw}" y1="${y1 + rh}" x2="${x2 + rw}" y2="${y2 + rh}" stroke="${stroke}" stroke-width="${sw}" />`,
    ].join("");
  }
  if (shape.kind === "cylinder") {
    const rx = w * 0.36;
    const ry = h * 0.12;
    const topY = y - h * 0.35;
    const bottomY = y + h * 0.35;
    return [
      `<ellipse cx="${x}" cy="${topY}" rx="${rx}" ry="${ry}" fill="${fill}" stroke="${stroke}" stroke-width="${sw}" />`,
      `<line x1="${x - rx}" y1="${topY}" x2="${x - rx}" y2="${bottomY}" stroke="${stroke}" stroke-width="${sw}" />`,
      `<line x1="${x + rx}" y1="${topY}" x2="${x + rx}" y2="${bottomY}" stroke="${stroke}" stroke-width="${sw}" />`,
      `<ellipse cx="${x}" cy="${bottomY}" rx="${rx}" ry="${ry}" fill="${fill}" stroke="${stroke}" stroke-width="${sw}" />`,
    ].join("");
  }
  if (shape.kind === "cone") {
    const rx = w * 0.36;
    const ry = h * 0.12;
    const topY = y - h * 0.45;
    const bottomY = y + h * 0.35;
    return [
      `<line x1="${x}" y1="${topY}" x2="${x - rx}" y2="${bottomY}" stroke="${stroke}" stroke-width="${sw}" />`,
      `<line x1="${x}" y1="${topY}" x2="${x + rx}" y2="${bottomY}" stroke="${stroke}" stroke-width="${sw}" />`,
      `<ellipse cx="${x}" cy="${bottomY}" rx="${rx}" ry="${ry}" fill="${fill}" stroke="${stroke}" stroke-width="${sw}" />`,
    ].join("");
  }
  if (shape.kind === "pyramid") {
    const apexY = y - h * 0.45;
    const baseY = y + h * 0.35;
    const leftX = x - w * 0.35;
    const rightX = x + w * 0.35;
    const backX = x;
    const backY = y + h * 0.05;
    return [
      `<polygon points="${leftX},${baseY} ${rightX},${baseY} ${backX},${backY}" fill="${fill}" stroke="${stroke}" stroke-width="${sw}" />`,
      `<line x1="${x}" y1="${apexY}" x2="${leftX}" y2="${baseY}" stroke="${stroke}" stroke-width="${sw}" />`,
      `<line x1="${x}" y1="${apexY}" x2="${rightX}" y2="${baseY}" stroke="${stroke}" stroke-width="${sw}" />`,
      `<line x1="${x}" y1="${apexY}" x2="${backX}" y2="${backY}" stroke="${stroke}" stroke-width="${sw}" />`,
    ].join("");
  }

  return `<rect x="${left}" y="${top}" width="${w}" height="${h}" fill="${fill}" stroke="${stroke}" stroke-width="${sw}" />`;
}

function drawingBounds() {
  if (drawnShapes.value.length === 0) {
    return { minX: 0, minY: 0, width: boardWidth, height: boardHeight.value };
  }

  let minX = Number.POSITIVE_INFINITY;
  let minY = Number.POSITIVE_INFINITY;
  let maxX = Number.NEGATIVE_INFINITY;
  let maxY = Number.NEGATIVE_INFINITY;

  for (const shape of drawnShapes.value) {
    let left = shape.x - shape.width / 2;
    let right = shape.x + shape.width / 2;
    let top = shape.y - shape.height / 2;
    let bottom = shape.y + shape.height / 2;

    if (shape.kind === "cube" || shape.kind === "cuboid") {
      const shift = shape.kind === "cube" ? 20 : 24;
      top -= shift;
      right += shift;
    }

    minX = Math.min(minX, left);
    minY = Math.min(minY, top);
    maxX = Math.max(maxX, right);
    maxY = Math.max(maxY, bottom);
  }

  const padding = 14;
  minX -= padding;
  minY -= padding;
  maxX += padding;
  maxY += padding;

  const width = Math.max(40, Math.ceil(maxX - minX));
  const height = Math.max(40, Math.ceil(maxY - minY));

  return { minX, minY, width, height };
}

function buildDrawingSvg(): string {
  const bounds = drawingBounds();
  const body = drawnShapes.value
    .map((shape) => shapeSvgMarkup(shape, bounds.minX, bounds.minY))
    .join("");

  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${bounds.width} ${bounds.height}" width="${bounds.width}" height="${bounds.height}"><rect width="${bounds.width}" height="${bounds.height}" fill="white"/>${body}</svg>`;
}

function encodeSvgBase64(svg: string): string {
  return btoa(unescape(encodeURIComponent(svg)));
}

async function insertDrawingIntoEditor() {
  if (!editor.value || drawnShapes.value.length === 0 || insertingDrawing.value) return;

  insertingDrawing.value = true;
  try {
    const svg = buildDrawingSvg();
    const geometrySvg = encodeSvgBase64(svg);
    const src = `data:image/svg+xml;base64,${geometrySvg}`;

    editor.value
      .chain()
      .focus()
      .insertContent({
        type: "geometryDrawing",
        attrs: {
          src,
          alt: __("Geometry Drawing"),
          geometrySvg,
        },
      })
      .run();

    showCanvasPanel.value = false;
    dirty.value = true;
  } finally {
    insertingDrawing.value = false;
  }
}

onMounted(() => {
  const queryId = Number(new URLSearchParams(window.location.search).get("document") || 0);
  if (queryId > 0) {
    void loadDocument(queryId);
  }
  mountAutosave();
  nextTick(renderMath);
});

onBeforeUnmount(() => {
  stopPolling();
  if (autosaveTimer) clearInterval(autosaveTimer);
  detachFormulaInputListener?.();
});

watch([topic, grade, difficulty, language, instructions, count, replaceItems], () => {
  dirty.value = true;
});

watch(showMathPanel, (isOpen) => {
  if (isOpen) {
    nextTick(mountFormulaInput);
    return;
  }

  detachFormulaInputListener?.();
  detachFormulaInputListener = null;
});

onUpdated(() => {
  nextTick(renderMath);
});
</script>

<template>
  <Head :title="__('Teacher Exercise Studio')" />

  <div class="mx-auto flex w-full max-w-[1500px] flex-col gap-6">
    <div
      class="rounded-2xl border border-slate-200/70 bg-white/90 p-6 shadow-sm dark:border-slate-800/60 dark:bg-[#0B0F19]/60"
    >
      <div class="flex flex-wrap items-center gap-4">
        <div
          class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 text-white"
        >
          <WandSparkles :size="18" />
        </div>
        <div>
          <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">
            {{ __("Teacher Exercise Studio") }}
          </h1>
          <p class="text-sm text-slate-500 dark:text-slate-300">
            {{
              __(
                "Generate, edit, and publish classroom-ready exercises from one workspace.",
              )
            }}
          </p>
        </div>
        <div class="ml-auto flex flex-wrap items-center gap-2">
          <span
            class="rounded-full border px-3 py-1 text-xs font-semibold"
            :class="
              generationStatus === 'processing'
                ? 'border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300'
                : generationStatus === 'ready'
                  ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300'
                  : generationStatus === 'failed'
                    ? 'border-rose-300 bg-rose-50 text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300'
                    : 'border-slate-300 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
            "
          >
            {{
              generationStatus === "processing"
                ? __("Processing")
                : generationStatus === "ready"
                  ? __("Ready")
                  : generationStatus === "failed"
                    ? __("Failed")
                    : __("Idle")
            }}
          </span>
          <span
            v-if="lastSavedAt"
            class="rounded-full border border-slate-300 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
          >
            {{ __("Saved at") }} {{ lastSavedAt }}
          </span>
        </div>
      </div>
    </div>

    <div
      v-if="generationError"
      class="rounded-xl border border-rose-400/40 bg-rose-500/10 p-4 text-sm text-rose-600 dark:text-rose-300"
    >
      {{ generationError }}
    </div>

    <div class="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)_340px]">
      <section
        class="space-y-4 overflow-y-auto rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm xl:h-[calc(297mm+140px)] dark:border-slate-800/60 dark:bg-[#0B0F19]/60"
      >
        <h2 class="text-base font-semibold text-slate-900 dark:text-white">
          {{ __("Generate with AI") }}
        </h2>

        <div class="space-y-1">
          <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">
            {{ __("Topic") }}
          </label>
          <input
            v-model="topic"
            type="text"
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 outline-none ring-indigo-300 transition focus:ring dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-100"
            :placeholder="__('Linear equations, percentages, geometry...')"
          />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div class="space-y-1">
            <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">
              {{ __("Grade") }}
            </label>
            <input
              v-model="grade"
              type="text"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 outline-none ring-indigo-300 transition focus:ring dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-100"
              placeholder="9"
            />
          </div>
          <div class="space-y-1">
            <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">
              {{ __("Count") }}
            </label>
            <input
              v-model.number="count"
              type="number"
              min="1"
              max="20"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 outline-none ring-indigo-300 transition focus:ring dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-100"
            />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div class="space-y-1">
            <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">
              {{ __("Difficulty") }}
            </label>
            <select
              v-model="difficulty"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 outline-none ring-indigo-300 transition focus:ring dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-100"
            >
              <option value="easy">{{ __("Easy") }}</option>
              <option value="medium">{{ __("Medium") }}</option>
              <option value="hard">{{ __("Hard") }}</option>
            </select>
          </div>
          <div class="space-y-1">
            <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">
              {{ __("Language") }}
            </label>
            <select
              v-model="language"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 outline-none ring-indigo-300 transition focus:ring dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-100"
            >
              <option value="en">{{ __("English") }}</option>
              <option value="hy">{{ __("Armenian") }}</option>
              <option value="fr">{{ __("French") }}</option>
              <option value="ru">{{ __("Russian") }}</option>
            </select>
          </div>
        </div>

        <div class="space-y-1">
          <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">
            {{ __("Teacher instructions") }}
          </label>
          <textarea
            v-model="instructions"
            rows="4"
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 outline-none ring-indigo-300 transition focus:ring dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-100"
            :placeholder="
              __(
                'Focus on real-life contexts, include one word problem, keep distractors realistic...',
              )
            "
          />
        </div>

        <label
          class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:text-slate-300"
        >
          <input v-model="replaceItems" type="checkbox" />
          {{ __("Replace existing generated items") }}
        </label>

        <label
          class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:text-slate-300"
        >
          <input v-model="showCorrectAnswers" type="checkbox" />
          {{ __("Show correct answers") }}
        </label>

        <button
          type="button"
          class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="!canGenerate"
          @click="generateExercises"
        >
          <Loader2 v-if="generating" :size="16" class="animate-spin" />
          <Sparkles v-else :size="16" />
          {{ generating ? __("Generating...") : __("Generate Exercises") }}
        </button>

        <div class="grid grid-cols-1 gap-2">
          <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 transition hover:border-indigo-400 hover:bg-indigo-100 disabled:opacity-50 dark:border-indigo-500/40 dark:bg-indigo-500/20 dark:text-indigo-200"
            :disabled="!canDownloadPdf"
            @click="downloadPdf"
          >
            <Loader2 v-if="downloadingPdf" :size="15" class="animate-spin" />
            <Download v-else :size="15" />
            {{ downloadingPdf ? __("Preparing PDF...") : __("Download PDF") }}
          </button>
        </div>
      </section>

      <section
        class="space-y-4 overflow-y-auto rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm xl:h-[calc(297mm+140px)] dark:border-slate-800/60 dark:bg-[#0B0F19]/60"
      >
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-base font-semibold text-slate-900 dark:text-white">
            {{ __("Editor") }}
          </h2>
          <div class="flex flex-wrap items-center gap-1">
            <select
              v-model="selectedFontFamily"
              class="rounded border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
              @change="applySelectedFontFamily"
            >
              <option
                v-for="option in fontFamilyOptions"
                :key="option.value"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
            <select
              v-model="selectedFontSize"
              class="rounded border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
              @change="applySelectedFontSize"
            >
              <option
                v-for="option in fontSizeOptions"
                :key="option.value"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
            <select
              v-model="selectedLineHeight"
              class="rounded border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
              @change="applySelectedLineHeight"
            >
              <option value="default">{{ __("Line") }}</option>
              <option
                v-for="option in lineHeightOptions"
                :key="option.value"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
            <button
              type="button"
              class="rounded border px-2 py-1 text-xs font-semibold"
              :class="
                isEditorActive('bold')
                  ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/20 dark:text-indigo-200'
                  : 'border-slate-300 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'
              "
              @click="runEditorCommand(() => editor?.chain().focus().toggleBold().run())"
            >
              B
            </button>
            <button
              type="button"
              class="rounded border px-2 py-1 text-xs font-semibold italic font-serif"
              :class="
                isEditorActive('italic')
                  ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/20 dark:text-indigo-200'
                  : 'border-slate-300 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'
              "
              @click="runEditorCommand(() => editor?.chain().focus().toggleItalic().run())"
            >
              I
            </button>
            <button
              type="button"
              class="rounded border px-2 py-1 text-xs font-semibold"
              :class="
                isEditorActive('underline')
                  ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/20 dark:text-indigo-200'
                  : 'border-slate-300 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'
              "
              @click="runEditorCommand(() => editor?.chain().focus().toggleUnderline().run())"
            >
              <UnderlineIcon :size="13" />
            </button>
            <button
              type="button"
              class="rounded border px-2 py-1 text-xs font-semibold"
              :class="
                isEditorActive('bulletList')
                  ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/20 dark:text-indigo-200'
                  : 'border-slate-300 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'
              "
              @click="runEditorCommand(() => editor?.chain().focus().toggleBulletList().run())"
            >
              {{ __("List") }}
            </button>
            <button
              type="button"
              class="rounded border px-2 py-1 text-xs font-semibold"
              :class="
                isTextAlignActive('left')
                  ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/20 dark:text-indigo-200'
                  : 'border-slate-300 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'
              "
              @click="runEditorCommand(() => editor?.chain().focus().setTextAlign('left').run())"
            >
              {{ __("Left") }}
            </button>
            <button
              type="button"
              class="rounded border px-2 py-1 text-xs font-semibold"
              :class="
                isTextAlignActive('center')
                  ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/20 dark:text-indigo-200'
                  : 'border-slate-300 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'
              "
              @click="runEditorCommand(() => editor?.chain().focus().setTextAlign('center').run())"
            >
              {{ __("Center") }}
            </button>
            <button
              type="button"
              class="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
              :disabled="!canOutdent"
              @click="runEditorCommand(() => editor?.chain().focus().outdent().run())"
            >
              <IndentDecrease :size="14" />
            </button>
            <button
              type="button"
              class="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
              :disabled="!canIndent"
              @click="runEditorCommand(() => editor?.chain().focus().indent().run())"
            >
              <IndentIncrease :size="14" />
            </button>
            <button
              type="button"
              class="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
              :disabled="!canUndo"
              @click="runEditorCommand(() => editor?.chain().focus().undo().run())"
            >
              <Undo2 :size="14" />
            </button>
            <button
              type="button"
              class="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
              :disabled="!canRedo"
              @click="runEditorCommand(() => editor?.chain().focus().redo().run())"
            >
              <Redo2 :size="14" />
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-1 rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:border-indigo-300 hover:text-indigo-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
              :disabled="uploadingImage"
              @click="openImagePicker"
            >
              <Loader2 v-if="uploadingImage" :size="13" class="animate-spin" />
              <ImagePlus v-else :size="13" />
              {{ __("Image") }}
            </button>
            <button
              type="button"
              class="rounded border px-2 py-1 text-xs font-semibold"
              :class="
                showMathPanel
                  ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/20 dark:text-indigo-200'
                  : 'border-slate-300 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'
              "
              @click="toggleMathPanel"
            >
              {{ __("Math") }}
            </button>
            <button
              type="button"
              class="rounded border px-2 py-1 text-xs font-semibold"
              :class="
                showCanvasPanel
                  ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/20 dark:text-indigo-200'
                  : 'border-slate-300 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'
              "
              @click="toggleCanvasPanel"
            >
              {{ __("Canvas") }}
            </button>
            <input
              ref="imageInputRef"
              type="file"
              accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml"
              class="hidden"
              @change="onImageFileChange"
            />
          </div>
        </div>

        <div
          v-if="showMathPanel"
          class="space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-900/50"
        >
          <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">
              {{ __("Math Input") }}
            </span>
            <div class="flex flex-wrap items-center gap-2">
              <button
                type="button"
                class="rounded border border-indigo-300 bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 hover:border-indigo-400 hover:bg-indigo-100 dark:border-indigo-500/40 dark:bg-indigo-500/20 dark:text-indigo-200"
                @click="insertFormulaInline"
              >
                {{ __("Insert Inline Math") }}
              </button>
              <button
                type="button"
                class="rounded border border-indigo-300 bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 hover:border-indigo-400 hover:bg-indigo-100 dark:border-indigo-500/40 dark:bg-indigo-500/20 dark:text-indigo-200"
                @click="insertFormulaBlock"
              >
                {{ __("Insert Block Math") }}
              </button>
            </div>
          </div>

          <math-field
            ref="formulaFieldRef"
            class="editor-math-field block min-h-[52px] w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
          >
            \int_0^1 x^2\,dx
          </math-field>
        </div>

        <div v-if="showCanvasPanel" class="mx-auto flex w-full max-w-[210mm] flex-col gap-3">
          <div
            class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 p-2 dark:border-slate-700 dark:bg-slate-900/50"
          >
            <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">
              {{ __("Geometry Builder") }}
            </span>
            <select
              v-model="selectedGeometryKey"
              class="min-w-[190px] rounded border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
            >
              <optgroup :label="__('2D Shapes')">
                <option
                  v-for="shape in twoDGeometryTemplates"
                  :key="shape.key"
                  :value="shape.key"
                >
                  {{ __(shape.labelKey) }}
                </option>
              </optgroup>
              <optgroup :label="__('3D Shapes')">
                <option
                  v-for="shape in threeDGeometryTemplates"
                  :key="shape.key"
                  :value="shape.key"
                >
                  {{ __(shape.labelKey) }}
                </option>
              </optgroup>
            </select>
            <button
              type="button"
              class="rounded border border-indigo-300 bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 hover:border-indigo-400 hover:bg-indigo-100 dark:border-indigo-500/40 dark:bg-indigo-500/20 dark:text-indigo-200"
              @click="insertGeometryTemplate"
            >
              {{ __("Add Selected Shape") }}
            </button>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <button
              type="button"
              class="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:border-indigo-300 hover:text-indigo-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
              :disabled="!selectedDrawnId"
              @click="removeSelectedShape"
            >
              {{ __("Delete Selected") }}
            </button>
            <button
              type="button"
              class="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:border-indigo-300 hover:text-indigo-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
              :disabled="drawnShapes.length === 0"
              @click="clearDrawingBoard"
            >
              {{ __("Clear Canvas") }}
            </button>
            <button
              type="button"
              class="rounded border border-emerald-300 bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 hover:border-emerald-400 hover:bg-emerald-100 dark:border-emerald-500/40 dark:bg-emerald-500/20 dark:text-emerald-200"
              :disabled="drawnShapes.length === 0 || !editor || insertingDrawing"
              @click="insertDrawingIntoEditor"
            >
              {{
                insertingDrawing
                  ? __("Inserting Drawing...")
                  : __("Insert Drawing Into Editor")
              }}
            </button>
          </div>

          <div
            v-if="selectedDrawnShape"
            class="grid gap-2 rounded-md border border-slate-200 bg-white p-2 dark:border-slate-700 dark:bg-slate-900"
          >
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
              {{ __("Resize Selected Shape") }}
            </p>

            <div v-if="selectedShapeIsUniform" class="grid grid-cols-[1fr_72px] items-center gap-2">
              <label class="text-xs text-slate-600 dark:text-slate-300">{{ __("Size") }}</label>
              <input
                type="number"
                min="30"
                :max="selectedShapeMaxSize"
                :value="Math.round(selectedDrawnShape.width)"
                class="rounded border border-slate-300 px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                @input="onSelectedSizeInput"
              />
              <input
                type="range"
                min="30"
                :max="selectedShapeMaxSize"
                :value="Math.round(selectedDrawnShape.width)"
                class="col-span-2 accent-indigo-600"
                @input="onSelectedSizeInput"
              />
            </div>

            <div v-else class="grid grid-cols-[1fr_72px] items-center gap-2">
              <label class="text-xs text-slate-600 dark:text-slate-300">{{ __("Width") }}</label>
              <input
                type="number"
                min="30"
                :max="selectedShapeMaxWidth"
                :value="Math.round(selectedDrawnShape.width)"
                class="rounded border border-slate-300 px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                @input="onSelectedWidthInput"
              />
              <input
                type="range"
                min="30"
                :max="selectedShapeMaxWidth"
                :value="Math.round(selectedDrawnShape.width)"
                class="col-span-2 accent-indigo-600"
                @input="onSelectedWidthInput"
              />

              <label class="text-xs text-slate-600 dark:text-slate-300">{{ __("Height") }}</label>
              <input
                type="number"
                min="30"
                :max="selectedShapeMaxHeight"
                :value="Math.round(selectedDrawnShape.height)"
                class="rounded border border-slate-300 px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                @input="onSelectedHeightInput"
              />
              <input
                type="range"
                min="30"
                :max="selectedShapeMaxHeight"
                :value="Math.round(selectedDrawnShape.height)"
                class="col-span-2 accent-indigo-600"
                @input="onSelectedHeightInput"
              />
            </div>
          </div>

          <div
            class="mx-auto w-full max-w-[210mm] overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900/50"
          >
            <svg
              ref="paintBoardRef"
              :viewBox="`0 0 ${boardWidth} ${boardHeight}`"
              class="w-full cursor-crosshair select-none"
              :style="{ height: `${boardHeight}px` }"
              @click="placeShapeOnBoard"
              @pointermove="onBoardPointerMove"
              @pointerup="stopDraggingShape"
              @pointerleave="stopDraggingShape"
            >
              <rect x="0" y="0" :width="boardWidth" :height="boardHeight" fill="transparent" />

              <g
                v-for="shape in drawnShapes"
                :key="shape.id"
                :data-shape-id="shape.id"
                @click.stop="selectDrawnShape(shape.id)"
                @pointerdown.stop.prevent="startDraggingShape(shape, $event)"
              >
                <template v-if="shape.kind === 'square' || shape.kind === 'rectangle'">
                  <rect
                    :x="shape.x - shape.width / 2"
                    :y="shape.y - shape.height / 2"
                    :width="shape.width"
                    :height="shape.height"
                    :fill="shape.fill"
                    :stroke="shape.stroke"
                    :stroke-width="shape.strokeWidth"
                  />
                </template>

                <template v-else-if="shape.kind === 'triangle'">
                  <polygon
                    :points="`${shape.x},${shape.y - shape.height / 2} ${shape.x - shape.width / 2},${shape.y + shape.height / 2} ${shape.x + shape.width / 2},${shape.y + shape.height / 2}`"
                    :fill="shape.fill"
                    :stroke="shape.stroke"
                    :stroke-width="shape.strokeWidth"
                  />
                </template>

                <template v-else-if="shape.kind === 'circle'">
                  <circle
                    :cx="shape.x"
                    :cy="shape.y"
                    :r="Math.min(shape.width, shape.height) / 2"
                    :fill="shape.fill"
                    :stroke="shape.stroke"
                    :stroke-width="shape.strokeWidth"
                  />
                </template>

                <template v-else-if="shape.kind === 'trapezoid'">
                  <polygon
                    :points="`${shape.x - (shape.width * 0.55) / 2},${shape.y - shape.height / 2} ${shape.x + (shape.width * 0.55) / 2},${shape.y - shape.height / 2} ${shape.x + shape.width / 2},${shape.y + shape.height / 2} ${shape.x - shape.width / 2},${shape.y + shape.height / 2}`"
                    :fill="shape.fill"
                    :stroke="shape.stroke"
                    :stroke-width="shape.strokeWidth"
                  />
                </template>

                <template v-else-if="shape.kind === 'cube' || shape.kind === 'cuboid'">
                  <rect
                    :x="shape.x - (shape.kind === 'cube' ? shape.width * 0.72 : shape.width * 0.78) / 2"
                    :y="shape.y - (shape.height * 0.72) / 2"
                    :width="shape.kind === 'cube' ? shape.width * 0.72 : shape.width * 0.78"
                    :height="shape.height * 0.72"
                    :fill="shape.fill"
                    :stroke="shape.stroke"
                    :stroke-width="shape.strokeWidth"
                  />
                  <rect
                    :x="shape.x - (shape.kind === 'cube' ? shape.width * 0.72 : shape.width * 0.78) / 2 + (shape.kind === 'cube' ? 20 : 24)"
                    :y="shape.y - (shape.height * 0.72) / 2 - (shape.kind === 'cube' ? 20 : 24)"
                    :width="shape.kind === 'cube' ? shape.width * 0.72 : shape.width * 0.78"
                    :height="shape.height * 0.72"
                    fill="none"
                    :stroke="shape.stroke"
                    :stroke-width="shape.strokeWidth"
                  />
                </template>

                <template v-else-if="shape.kind === 'cylinder'">
                  <ellipse
                    :cx="shape.x"
                    :cy="shape.y - shape.height * 0.35"
                    :rx="shape.width * 0.36"
                    :ry="shape.height * 0.12"
                    :fill="shape.fill"
                    :stroke="shape.stroke"
                    :stroke-width="shape.strokeWidth"
                  />
                  <line
                    :x1="shape.x - shape.width * 0.36"
                    :y1="shape.y - shape.height * 0.35"
                    :x2="shape.x - shape.width * 0.36"
                    :y2="shape.y + shape.height * 0.35"
                    :stroke="shape.stroke"
                    :stroke-width="shape.strokeWidth"
                  />
                  <line
                    :x1="shape.x + shape.width * 0.36"
                    :y1="shape.y - shape.height * 0.35"
                    :x2="shape.x + shape.width * 0.36"
                    :y2="shape.y + shape.height * 0.35"
                    :stroke="shape.stroke"
                    :stroke-width="shape.strokeWidth"
                  />
                  <ellipse
                    :cx="shape.x"
                    :cy="shape.y + shape.height * 0.35"
                    :rx="shape.width * 0.36"
                    :ry="shape.height * 0.12"
                    :fill="shape.fill"
                    :stroke="shape.stroke"
                    :stroke-width="shape.strokeWidth"
                  />
                </template>

                <template v-else-if="shape.kind === 'cone'">
                  <line
                    :x1="shape.x"
                    :y1="shape.y - shape.height * 0.45"
                    :x2="shape.x - shape.width * 0.36"
                    :y2="shape.y + shape.height * 0.35"
                    :stroke="shape.stroke"
                    :stroke-width="shape.strokeWidth"
                  />
                  <line
                    :x1="shape.x"
                    :y1="shape.y - shape.height * 0.45"
                    :x2="shape.x + shape.width * 0.36"
                    :y2="shape.y + shape.height * 0.35"
                    :stroke="shape.stroke"
                    :stroke-width="shape.strokeWidth"
                  />
                  <ellipse
                    :cx="shape.x"
                    :cy="shape.y + shape.height * 0.35"
                    :rx="shape.width * 0.36"
                    :ry="shape.height * 0.12"
                    :fill="shape.fill"
                    :stroke="shape.stroke"
                    :stroke-width="shape.strokeWidth"
                  />
                </template>

                <template v-else-if="shape.kind === 'sphere'">
                  <circle
                    :cx="shape.x"
                    :cy="shape.y"
                    :r="Math.min(shape.width, shape.height) / 2"
                    :fill="shape.fill"
                    :stroke="shape.stroke"
                    :stroke-width="shape.strokeWidth"
                  />
                  <ellipse
                    :cx="shape.x"
                    :cy="shape.y"
                    :rx="Math.min(shape.width, shape.height) * 0.38"
                    :ry="Math.min(shape.width, shape.height) * 0.16"
                    fill="none"
                    :stroke="shape.stroke"
                    stroke-width="1.2"
                  />
                  <ellipse
                    :cx="shape.x"
                    :cy="shape.y"
                    :rx="Math.min(shape.width, shape.height) * 0.2"
                    :ry="Math.min(shape.width, shape.height) * 0.47"
                    fill="none"
                    :stroke="shape.stroke"
                    stroke-width="1.2"
                  />
                </template>

                <template v-else-if="shape.kind === 'pyramid'">
                  <polygon
                    :points="`${shape.x - shape.width * 0.35},${shape.y + shape.height * 0.35} ${shape.x + shape.width * 0.35},${shape.y + shape.height * 0.35} ${shape.x},${shape.y + shape.height * 0.05}`"
                    :fill="shape.fill"
                    :stroke="shape.stroke"
                    :stroke-width="shape.strokeWidth"
                  />
                  <line
                    :x1="shape.x"
                    :y1="shape.y - shape.height * 0.45"
                    :x2="shape.x - shape.width * 0.35"
                    :y2="shape.y + shape.height * 0.35"
                    :stroke="shape.stroke"
                    :stroke-width="shape.strokeWidth"
                  />
                  <line
                    :x1="shape.x"
                    :y1="shape.y - shape.height * 0.45"
                    :x2="shape.x + shape.width * 0.35"
                    :y2="shape.y + shape.height * 0.35"
                    :stroke="shape.stroke"
                    :stroke-width="shape.strokeWidth"
                  />
                  <line
                    :x1="shape.x"
                    :y1="shape.y - shape.height * 0.45"
                    :x2="shape.x"
                    :y2="shape.y + shape.height * 0.05"
                    :stroke="shape.stroke"
                    :stroke-width="shape.strokeWidth"
                  />
                </template>

                <rect
                  v-if="isSelectedShape(shape.id)"
                  :x="shape.x - shape.width / 2 - 4"
                  :y="shape.y - shape.height / 2 - 4"
                  :width="shape.width + 8"
                  :height="shape.height + 8"
                  fill="none"
                  stroke="#f59e0b"
                  stroke-width="1.5"
                  stroke-dasharray="5 4"
                />
              </g>

              <text
                v-if="drawnShapes.length === 0"
                x="50%"
                y="50%"
                text-anchor="middle"
                dominant-baseline="middle"
                class="fill-slate-400 text-[14px]"
              >
                {{ __("No shapes yet. Choose a shape and click the canvas.") }}
              </text>
            </svg>
          </div>

          <p class="text-xs text-slate-500 dark:text-slate-400">
            {{ __("Click on empty canvas to place selected shape. Drag any shape to move.") }}
          </p>
        </div>

        <div v-if="loadingDocument" class="py-10 text-center text-sm text-slate-500">
          {{ __("Loading...") }}
        </div>

        <EditorContent v-else :editor="editor" />

        <p
          v-if="dirty"
          class="text-xs font-medium text-amber-600 dark:text-amber-400"
        >
          {{ __("Unsaved changes") }}
        </p>
      </section>

      <section
        class="space-y-3 overflow-y-auto rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm xl:h-[calc(297mm+140px)] dark:border-slate-800/60 dark:bg-[#0B0F19]/60"
      >
        <h2 class="text-base font-semibold text-slate-900 dark:text-white">
          {{ __("Generated Items") }}
        </h2>

        <div
          v-if="items.length === 0"
          class="rounded-lg border border-dashed border-slate-200 p-4 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
        >
          {{
            __(
              "No generated items yet. Run generation from the left panel.",
            )
          }}
        </div>

        <div v-else class="space-y-3 pr-1">
          <article
            v-for="item in items"
            :key="item.id"
            class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-900/40"
          >
            <div class="mb-2 flex items-center justify-between gap-2">
              <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                {{ __("Exercise") }} {{ item.item_order }}
              </p>
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded border border-slate-300 px-2 py-1 text-[11px] font-semibold text-slate-700 hover:border-indigo-300 hover:text-indigo-700 disabled:opacity-50 dark:border-slate-700 dark:text-slate-300"
                :disabled="regeneratingItemId === item.id"
                @click="regenerateItem(item)"
              >
                <Loader2
                  v-if="regeneratingItemId === item.id"
                  :size="12"
                  class="animate-spin"
                />
                <RefreshCw v-else :size="12" />
                {{ __("Regenerate") }}
              </button>
            </div>
            <p class="text-sm text-slate-800 dark:text-slate-100">
              <template
                v-for="(segment, idx) in splitMath(item.question)"
                :key="`gq-${item.id}-${idx}`"
              >
                <span v-if="segment.type === 'text'">{{ segment.value }}</span>
                <span v-else class="inline-math" :data-latex="segment.value"></span>
              </template>
            </p>
            <ul class="mt-2 space-y-1 text-xs text-slate-600 dark:text-slate-300">
              <li v-for="(option, index) in item.options" :key="`${item.id}-${index}`">
                {{ index + 1 }}.
                <template
                  v-for="(segment, idx) in splitMath(option)"
                  :key="`go-${item.id}-${index}-${idx}`"
                >
                  <span v-if="segment.type === 'text'">{{ segment.value }}</span>
                  <span v-else class="inline-math" :data-latex="segment.value"></span>
                </template>
              </li>
            </ul>
            <p
              v-if="showCorrectAnswers"
              class="mt-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400"
            >
              {{ __("Answer") }}:
              <template
                v-for="(segment, idx) in splitMath(item.answer)"
                :key="`ga-${item.id}-${idx}`"
              >
                <span v-if="segment.type === 'text'">{{ segment.value }}</span>
                <span v-else class="inline-math" :data-latex="segment.value"></span>
              </template>
            </p>
          </article>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
:deep(.exercise-studio-editor) {
  box-sizing: border-box;
  width: min(210mm, 100%);
  max-width: 210mm;
  min-height: 297mm;
  margin-inline: auto;
  padding: 14mm 12mm;
  overflow-wrap: anywhere;
}

:deep(.exercise-studio-editor ol) {
  list-style: decimal;
  margin: 0.5rem 0;
  padding-left: 1.35rem;
}

:deep(.exercise-studio-editor ul) {
  list-style: disc;
  margin: 0.5rem 0;
  padding-left: 1.35rem;
}

:deep(.exercise-studio-editor li) {
  margin: 0.2rem 0;
}

:deep(.exercise-studio-editor [data-line-height="1"]) {
  line-height: 1;
}

:deep(.exercise-studio-editor [data-line-height="1.2"]) {
  line-height: 1.2;
}

:deep(.exercise-studio-editor [data-line-height="1.5"]) {
  line-height: 1.5;
}

:deep(.exercise-studio-editor [data-line-height="2"]) {
  line-height: 2;
}

:deep(.exercise-studio-editor [data-indent="1"]) { margin-left: 2em; }
:deep(.exercise-studio-editor [data-indent="2"]) { margin-left: 4em; }
:deep(.exercise-studio-editor [data-indent="3"]) { margin-left: 6em; }
:deep(.exercise-studio-editor [data-indent="4"]) { margin-left: 8em; }
:deep(.exercise-studio-editor [data-indent="5"]) { margin-left: 10em; }
:deep(.exercise-studio-editor [data-indent="6"]) { margin-left: 12em; }
:deep(.exercise-studio-editor [data-indent="7"]) { margin-left: 14em; }
:deep(.exercise-studio-editor [data-indent="8"]) { margin-left: 16em; }
:deep(.exercise-studio-editor [data-indent="9"]) { margin-left: 18em; }
:deep(.exercise-studio-editor [data-indent="10"]) { margin-left: 20em; }
:deep(.exercise-studio-editor [data-indent="11"]) { margin-left: 22em; }
:deep(.exercise-studio-editor [data-indent="12"]) { margin-left: 24em; }

@media (max-width: 960px) {
  :deep(.exercise-studio-editor) {
    width: 100%;
    max-width: 100%;
    min-height: 520px;
    padding: 1rem;
  }
}

:deep(.editor-math-field) {
  --caret-color: #0f172a;
  --primary: #4f46e5;
  --selection-background-color: rgba(79, 70, 229, 0.2);
  --keyboard-zindex: 50;
}

:deep(.dark .editor-math-field) {
  --caret-color: #e2e8f0;
}
</style>

