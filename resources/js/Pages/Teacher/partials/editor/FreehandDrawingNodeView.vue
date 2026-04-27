<script setup lang="ts">
import { cmsTranslate as __ } from "@/lib/i18n";
import type { FreehandDrawingLine } from "@/types/teacher";
import { computed, ref } from "vue";
import { NodeViewWrapper, nodeViewProps } from "@tiptap/vue-3";

const props = defineProps(nodeViewProps);

const BOARD_WIDTH = 500;
const BOARD_HEIGHT = 250;
const DEFAULT_COLORS = [
  "#4f46e5",
  "#ef4444",
  "#0ea5e9",
  "#10b981",
  "#f59e0b",
  "#a855f7",
];

const svgRef = ref<SVGSVGElement | null>(null);
const color = ref(
  DEFAULT_COLORS[Math.floor(Math.random() * DEFAULT_COLORS.length)] ??
    "#4f46e5",
);
const size = ref(3);
const drawing = ref(false);
const activePointerId = ref<number | null>(null);
const activeLineId = ref<string | null>(null);
const points = ref<Array<[number, number]>>([]);

function normalizeStrokeSize(value: unknown): number {
  const parsed = Number(value);
  if (!Number.isFinite(parsed)) return 3;
  return Math.max(1, Math.min(12, Math.round(parsed)));
}

function normalizeLines(value: unknown): FreehandDrawingLine[] {
  if (!Array.isArray(value)) return [];

  return value
    .map((line) => {
      if (!line || typeof line !== "object") return null;
      const raw = line as Record<string, unknown>;
      const id = String(raw.id ?? "").trim();
      const path = String(raw.path ?? "").trim();
      const stroke = String(raw.color ?? "#4f46e5").trim() || "#4f46e5";
      const strokeSize = normalizeStrokeSize(raw.size);

      if (!id || !path) return null;

      return {
        id,
        path,
        color: stroke,
        size: strokeSize,
      } as FreehandDrawingLine;
    })
    .filter((line): line is FreehandDrawingLine => !!line);
}

const lines = computed<FreehandDrawingLine[]>(() =>
  normalizeLines(props.node.attrs.lines),
);

function clamp(value: number, min: number, max: number): number {
  return Math.max(min, Math.min(max, value));
}

function pointerToBoardPoint(event: PointerEvent): [number, number] | null {
  const svg = svgRef.value;
  if (!svg) return null;

  const rect = svg.getBoundingClientRect();
  if (rect.width <= 0 || rect.height <= 0) return null;

  const x = ((event.clientX - rect.left) / rect.width) * BOARD_WIDTH;
  const y = ((event.clientY - rect.top) / rect.height) * BOARD_HEIGHT;

  return [clamp(x, 0, BOARD_WIDTH), clamp(y, 0, BOARD_HEIGHT)];
}

function pointsToPath(rawPoints: Array<[number, number]>): string {
  if (rawPoints.length === 0) return "";

  const [startX, startY] = rawPoints[0];
  if (rawPoints.length === 1) {
    return `M ${startX} ${startY} L ${startX + 0.01} ${startY + 0.01}`;
  }

  const segments = rawPoints
    .slice(1)
    .map(([x, y]) => `L ${x} ${y}`)
    .join(" ");

  return `M ${startX} ${startY} ${segments}`;
}

function createLineId(): string {
  if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
    return crypto.randomUUID();
  }

  return `line-${Date.now()}-${Math.round(Math.random() * 1_000_000)}`;
}

function updateLinePreview() {
  const lineId = activeLineId.value;
  if (!lineId || points.value.length === 0) return;

  const linePath = pointsToPath(points.value);
  if (!linePath) return;

  const nextLines = lines.value.filter((line) => line.id !== lineId);
  nextLines.push({
    id: lineId,
    color: color.value,
    size: normalizeStrokeSize(size.value),
    path: linePath,
  });

  props.updateAttributes({ lines: nextLines });
}

function finishDrawing(pointerId?: number) {
  if (
    pointerId !== undefined &&
    activePointerId.value !== null &&
    pointerId !== activePointerId.value
  ) {
    return;
  }

  const svg = svgRef.value;
  if (
    svg &&
    activePointerId.value !== null &&
    typeof svg.hasPointerCapture === "function" &&
    svg.hasPointerCapture(activePointerId.value)
  ) {
    svg.releasePointerCapture(activePointerId.value);
  }

  drawing.value = false;
  activePointerId.value = null;
  activeLineId.value = null;
  points.value = [];
}

function onPointerDown(event: PointerEvent) {
  const svg = svgRef.value;
  if (!svg) return;

  event.preventDefault();
  svg.setPointerCapture(event.pointerId);

  const startPoint = pointerToBoardPoint(event);
  if (!startPoint) return;

  drawing.value = true;
  activePointerId.value = event.pointerId;
  activeLineId.value = createLineId();
  points.value = [startPoint];

  updateLinePreview();
}

function onPointerMove(event: PointerEvent) {
  if (!drawing.value || activePointerId.value !== event.pointerId) return;

  event.preventDefault();
  const point = pointerToBoardPoint(event);
  if (!point) return;

  const previous = points.value[points.value.length - 1];
  if (previous) {
    const distance = Math.hypot(point[0] - previous[0], point[1] - previous[1]);
    if (distance < 0.7) return;
  }

  points.value = [...points.value, point];
  updateLinePreview();
}

function onPointerUp(event: PointerEvent) {
  finishDrawing(event.pointerId);
}

function onPointerCancel(event: PointerEvent) {
  finishDrawing(event.pointerId);
}

function clearLines() {
  props.updateAttributes({ lines: [] });
}
</script>

<template>
  <NodeViewWrapper class="freehand-draw-node" contenteditable="false">
    <div class="freehand-controls">
      <label class="freehand-label">{{ __("Stroke") }}</label>
      <input v-model="color" class="freehand-color" type="color" />
      <input
        v-model.number="size"
        class="freehand-size"
        type="number"
        min="1"
        max="12"
      />
      <button type="button" class="freehand-clear-btn" @click="clearLines">
        {{ __("Clear Drawing") }}
      </button>
    </div>

    <svg
      ref="svgRef"
      class="freehand-canvas"
      viewBox="0 0 500 250"
      @pointerdown="onPointerDown"
      @pointermove="onPointerMove"
      @pointerup="onPointerUp"
      @pointercancel="onPointerCancel"
      @lostpointercapture="onPointerCancel"
    >
      <path
        v-for="line in lines"
        :key="line.id"
        :d="line.path"
        :stroke="line.color"
        :stroke-width="line.size"
        fill="none"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
      <text
        v-if="lines.length === 0"
        x="50%"
        y="50%"
        dominant-baseline="middle"
        text-anchor="middle"
        class="freehand-placeholder"
      >
        {{ __("Draw here") }}
      </text>
    </svg>
  </NodeViewWrapper>
</template>

<style scoped>
.freehand-draw-node {
  margin: 0.6rem 0;
  border: 1px solid rgba(148, 163, 184, 0.35);
  border-radius: 12px;
  padding: 10px;
  background: rgba(248, 250, 252, 0.8);
}

.dark .freehand-draw-node {
  border-color: rgba(71, 85, 105, 0.8);
  background: rgba(15, 23, 42, 0.5);
}

.freehand-controls {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}

.freehand-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #475569;
}

.dark .freehand-label {
  color: #cbd5e1;
}

.freehand-color {
  height: 1.9rem;
  width: 2.4rem;
  border: 1px solid rgba(148, 163, 184, 0.45);
  border-radius: 8px;
  background: transparent;
  padding: 2px;
}

.freehand-size {
  width: 3.25rem;
  border: 1px solid rgba(148, 163, 184, 0.45);
  border-radius: 8px;
  background: #fff;
  color: #1e293b;
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.25rem;
  padding: 0.25rem 0.4rem;
}

.dark .freehand-size {
  border-color: rgba(71, 85, 105, 0.9);
  background: rgba(15, 23, 42, 0.85);
  color: #e2e8f0;
}

.freehand-clear-btn {
  border: 1px solid rgba(148, 163, 184, 0.55);
  border-radius: 8px;
  background: #fff;
  color: #334155;
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.1rem;
  padding: 0.32rem 0.58rem;
  cursor: pointer;
}

.freehand-clear-btn:hover {
  border-color: rgba(79, 70, 229, 0.55);
  color: #4338ca;
}

.dark .freehand-clear-btn {
  border-color: rgba(71, 85, 105, 0.9);
  background: rgba(15, 23, 42, 0.85);
  color: #cbd5e1;
}

.dark .freehand-clear-btn:hover {
  border-color: rgba(99, 102, 241, 0.7);
  color: #c7d2fe;
}

.freehand-canvas {
  width: 100%;
  min-height: 220px;
  background: #f8fafc;
  border: 1px solid rgba(148, 163, 184, 0.35);
  border-radius: 10px;
  cursor: crosshair;
  touch-action: none;
}

.dark .freehand-canvas {
  background: rgba(15, 23, 42, 0.8);
  border-color: rgba(71, 85, 105, 0.8);
}

.freehand-placeholder {
  fill: #94a3b8;
  font-size: 14px;
}

.dark .freehand-placeholder {
  fill: #64748b;
}
</style>
