<script setup lang="ts">
/** One-shot CSS confetti shower. Mount it (v-if) when you want it to fire. */
const props = withDefaults(defineProps<{ count?: number }>(), { count: 60 });

const palette = [
  "var(--primary)",
  "var(--chart-2)",
  "var(--chart-3)",
  "var(--chart-4)",
  "var(--chart-5)",
  "var(--rank-first)",
];

const pieces = Array.from({ length: props.count }, (_, index) => ({
  id: index,
  style: {
    left: `${Math.random() * 100}%`,
    background: palette[index % palette.length],
    width: `${6 + Math.random() * 6}px`,
    height: `${8 + Math.random() * 10}px`,
    borderRadius: Math.random() > 0.6 ? "9999px" : "2px",
    "--delay": `${Math.random() * 0.6}s`,
    "--fall": `${2.2 + Math.random() * 1.8}s`,
    "--drift": `${(Math.random() - 0.5) * 240}px`,
    "--spin": `${(Math.random() > 0.5 ? 1 : -1) * (360 + Math.random() * 720)}deg`,
  },
}));
</script>

<template>
  <div class="confetti" aria-hidden="true">
    <span v-for="piece in pieces" :key="piece.id" class="confetti-piece" :style="piece.style" />
  </div>
</template>
