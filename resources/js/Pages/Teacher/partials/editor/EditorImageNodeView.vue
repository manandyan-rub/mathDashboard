<script setup lang="ts">
import { cmsTranslate as __ } from "@/lib/i18n";
import { computed, onBeforeUnmount, ref } from "vue";
import { NodeViewWrapper, nodeViewProps } from "@tiptap/vue-3";

const props = defineProps(nodeViewProps);

const IMAGE_FRAME_VALUES = [
  "clean",
  "soft-card",
  "polaroid",
  "blueprint",
  "ink-border",
  "gallery",
  "tape",
  "spotlight",
] as const;
const IMAGE_ALIGN_VALUES = ["left", "center", "right", "full"] as const;
type ImageFrameValue = (typeof IMAGE_FRAME_VALUES)[number];
type ImageAlignValue = (typeof IMAGE_ALIGN_VALUES)[number];

const MIN_WIDTH = 20;
const MAX_WIDTH = 100;

const resizing = ref(false);
const startX = ref(0);
const startWidth = ref(75);
const parentWidth = ref(1);

function clamp(value: number, min: number, max: number): number {
  return Math.max(min, Math.min(max, value));
}

function normalizeImageFrame(value: unknown): ImageFrameValue {
  const normalized = String(value ?? "").trim();
  return IMAGE_FRAME_VALUES.includes(normalized as ImageFrameValue)
    ? (normalized as ImageFrameValue)
    : "soft-card";
}

function normalizeImageAlign(value: unknown): ImageAlignValue {
  const normalized = String(value ?? "").trim();
  return IMAGE_ALIGN_VALUES.includes(normalized as ImageAlignValue)
    ? (normalized as ImageAlignValue)
    : "center";
}

function normalizeWidthPercent(value: unknown): number {
  const normalized = String(value ?? "").trim();
  const match = normalized.match(/^([0-9]+(?:\.[0-9]+)?)%$/);
  const parsed = match ? Number(match[1]) : Number(normalized);

  return Number.isFinite(parsed) ? clamp(Math.round(parsed), MIN_WIDTH, MAX_WIDTH) : 75;
}

const frame = computed(() => normalizeImageFrame(props.node.attrs.frame));
const align = computed(() => normalizeImageAlign(props.node.attrs.align));
const widthPercent = computed(() => normalizeWidthPercent(props.node.attrs.width));
const widthLabel = computed(() => `${widthPercent.value}%`);
const caption = computed(() => String(props.node.attrs.caption ?? "").trim());
const sourceName = computed(() => String(props.node.attrs.sourceName ?? "").trim());
const title = computed(() => String(props.node.attrs.title ?? sourceName.value).trim());
const isEditable = computed(() => props.editor.isEditable);

const figureStyle = computed(() => {
  if (align.value === "full") {
    return "width:100%;max-width:100%;margin:14px 0;";
  }

  const margin =
    align.value === "left"
      ? "14px auto 14px 0"
      : align.value === "right"
        ? "14px 0 14px auto"
        : "14px auto";

  return `width:${widthLabel.value};max-width:100%;margin:${margin};`;
});

function updateWidth(width: number) {
  const next = clamp(Math.round(width), MIN_WIDTH, MAX_WIDTH);
  props.updateAttributes({
    width: `${next}%`,
    align: next >= MAX_WIDTH ? "full" : align.value === "full" ? "center" : align.value,
  });
}

function nudgeWidth(amount: number) {
  updateWidth(widthPercent.value + amount);
}

function startResize(event: PointerEvent) {
  if (!isEditable.value) return;

  const wrapper = (event.currentTarget as HTMLElement).closest(
    "[data-editor-image]",
  ) as HTMLElement | null;
  const parent = wrapper?.parentElement;
  const rect = parent?.getBoundingClientRect();

  event.preventDefault();
  event.stopPropagation();
  resizing.value = true;
  startX.value = event.clientX;
  startWidth.value = widthPercent.value;
  parentWidth.value = Math.max(
    1,
    rect?.width ?? wrapper?.getBoundingClientRect().width ?? 1,
  );

  window.addEventListener("pointermove", onResizeMove);
  window.addEventListener("pointerup", stopResize);
  window.addEventListener("pointercancel", stopResize);
}

function onResizeMove(event: PointerEvent) {
  if (!resizing.value) return;

  const deltaPercent = ((event.clientX - startX.value) / parentWidth.value) * 100;
  updateWidth(startWidth.value + deltaPercent);
}

function stopResize() {
  resizing.value = false;
  window.removeEventListener("pointermove", onResizeMove);
  window.removeEventListener("pointerup", stopResize);
  window.removeEventListener("pointercancel", stopResize);
}

onBeforeUnmount(stopResize);
</script>

<template>
  <NodeViewWrapper
    as="figure"
    data-editor-image="true"
    :data-editor-image-frame="frame"
    :data-editor-image-align="align"
    :data-editor-image-width="widthLabel"
    :data-caption="caption || undefined"
    :data-source-name="sourceName || undefined"
    class="editor-image-node-view editor-image-frame"
    :class="[
      `editor-image-frame--${frame}`,
      `editor-image-align--${align}`,
      { 'editor-image-node-view--resizing': resizing },
    ]"
    :style="figureStyle"
    contenteditable="false"
  >
    <div class="editor-image-node-view__shell">
      <img
        :src="String(props.node.attrs.src ?? '')"
        :alt="String(props.node.attrs.alt ?? '')"
        :title="title || undefined"
        draggable="false"
        loading="lazy"
      />

      <div v-if="isEditable" class="editor-image-node-view__toolbar">
        <button type="button" @click.stop="nudgeWidth(-5)">-</button>
        <span>{{ widthLabel }}</span>
        <button type="button" @click.stop="nudgeWidth(5)">+</button>
      </div>

      <button
        v-if="isEditable"
        type="button"
        class="editor-image-node-view__resize"
        :aria-label="__('Resize image')"
        @pointerdown="startResize"
      ></button>
    </div>

    <figcaption v-if="caption" data-editor-image-caption="true">
      {{ caption }}
    </figcaption>
  </NodeViewWrapper>
</template>

<style scoped>
.editor-image-node-view {
  user-select: none;
}

.editor-image-node-view__shell {
  position: relative;
  border-radius: inherit;
}

.editor-image-node-view__shell img {
  display: block;
  width: 100%;
  height: auto;
  border-radius: inherit;
}

.editor-image-node-view__toolbar {
  position: absolute;
  right: 0.5rem;
  top: 0.5rem;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  border: 1px solid color-mix(in srgb, var(--border) 80%, var(--primary));
  border-radius: 999px;
  background: color-mix(in srgb, var(--card) 92%, transparent);
  box-shadow: 0 10px 24px color-mix(in srgb, var(--foreground) 12%, transparent);
  color: var(--foreground);
  font-size: 0.72rem;
  font-weight: 800;
  line-height: 1;
  padding: 0.25rem;
}

.editor-image-node-view__toolbar button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.35rem;
  height: 1.35rem;
  border: 1px solid var(--border);
  border-radius: 999px;
  background: var(--card);
  color: var(--foreground);
  cursor: pointer;
}

.editor-image-node-view__toolbar button:hover {
  border-color: var(--primary);
  color: var(--primary);
}

.editor-image-node-view__resize {
  position: absolute;
  right: -0.45rem;
  bottom: -0.45rem;
  width: 1.15rem;
  height: 1.15rem;
  border: 2px solid var(--card);
  border-radius: 999px;
  background: var(--primary);
  box-shadow: 0 8px 18px color-mix(in srgb, var(--primary) 32%, transparent);
  cursor: nwse-resize;
}

.editor-image-node-view__resize::before {
  content: "";
  position: absolute;
  inset: 0.22rem;
  border-right: 2px solid var(--primary-foreground);
  border-bottom: 2px solid var(--primary-foreground);
}

.editor-image-node-view--resizing {
  outline: 2px solid color-mix(in srgb, var(--primary) 48%, transparent);
  outline-offset: 4px;
}
</style>
