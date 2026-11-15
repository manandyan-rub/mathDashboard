<script setup lang="ts">
import { computed } from "vue";

/**
 * Decorative SVG "live graph": axes, a sine wave and a parabola that draw
 * themselves in a loop, with a point riding along the sine curve.
 */
const width = 420;
const height = 240;
const midY = height / 2;

function buildPath(fn: (x: number) => number, from: number, to: number) {
  const steps = 120;
  let d = "";
  for (let i = 0; i <= steps; i += 1) {
    const t = from + ((to - from) * i) / steps;
    const x = ((t - from) / (to - from)) * width;
    const y = midY - fn(t);
    d += `${i === 0 ? "M" : "L"}${x.toFixed(1)},${y.toFixed(1)} `;
  }
  return d.trim();
}

const sinePath = computed(() =>
  buildPath((t) => Math.sin(t) * 62, -Math.PI * 2, Math.PI * 2),
);
const parabolaPath = computed(() => buildPath((t) => 95 - t * t * 9, -4, 4));

const gridX = Array.from({ length: 11 }, (_, i) => (i * width) / 10);
const gridY = Array.from({ length: 7 }, (_, i) => (i * height) / 6);
</script>

<template>
  <svg
    :viewBox="`0 0 ${width} ${height}`"
    class="graph-sketch h-auto w-full"
    role="img"
    aria-label="Animated graphs of a sine wave and a parabola"
  >
    <g class="graph-grid">
      <line v-for="x in gridX" :key="`x${x}`" :x1="x" :x2="x" y1="0" :y2="height" />
      <line v-for="y in gridY" :key="`y${y}`" x1="0" :x2="width" :y1="y" :y2="y" />
    </g>
    <line class="graph-axis" x1="0" :x2="width" :y1="midY" :y2="midY" />
    <line class="graph-axis" :x1="width / 2" :x2="width / 2" y1="0" :y2="height" />

    <path class="graph-curve graph-curve--b" :d="parabolaPath" pathLength="1" />
    <path id="graph-sine" class="graph-curve graph-curve--a" :d="sinePath" pathLength="1" />

    <circle class="graph-dot-halo" r="10">
      <animateMotion dur="6s" repeatCount="indefinite" :path="sinePath" />
    </circle>
    <circle class="graph-dot" r="4.5">
      <animateMotion dur="6s" repeatCount="indefinite" :path="sinePath" />
    </circle>

    <text class="graph-label graph-label--a" x="12" y="24">y = sin(x)</text>
    <text class="graph-label graph-label--b" :x="width - 110" y="24">y = 10 − x²</text>
  </svg>
</template>

<style scoped>
.graph-grid line {
  stroke: color-mix(in srgb, var(--border) 70%, transparent);
  stroke-width: 1;
}
.graph-axis {
  stroke: color-mix(in srgb, var(--muted-foreground) 55%, transparent);
  stroke-width: 1.25;
}
.graph-curve {
  fill: none;
  stroke-width: 3;
  stroke-linecap: round;
  stroke-dasharray: 1;
  stroke-dashoffset: 1;
  animation: graph-draw 6s var(--ease-out-expo, ease-out) infinite;
}
.graph-curve--a {
  stroke: var(--primary);
  filter: drop-shadow(0 0 6px color-mix(in srgb, var(--primary) 60%, transparent));
}
.graph-curve--b {
  stroke: var(--chart-2);
  animation-delay: 0.6s;
  opacity: 0.85;
}
.graph-dot {
  fill: var(--primary-foreground);
  stroke: var(--primary);
  stroke-width: 2.5;
}
.graph-dot-halo {
  fill: color-mix(in srgb, var(--primary) 30%, transparent);
  animation: halo 1.6s ease-in-out infinite alternate;
  transform-box: fill-box;
  transform-origin: center;
}
.graph-label {
  font-family: var(--font-mono);
  font-size: 13px;
  font-weight: 600;
}
.graph-label--a {
  fill: var(--primary);
}
.graph-label--b {
  fill: var(--chart-2);
}
@keyframes graph-draw {
  0% { stroke-dashoffset: 1; }
  45%, 80% { stroke-dashoffset: 0; }
  100% { stroke-dashoffset: -1; }
}
@keyframes halo {
  from { transform: scale(0.8); }
  to { transform: scale(1.5); }
}
@media (prefers-reduced-motion: reduce) {
  .graph-curve {
    animation: none;
    stroke-dashoffset: 0;
  }
  .graph-dot-halo {
    animation: none;
  }
}
</style>
