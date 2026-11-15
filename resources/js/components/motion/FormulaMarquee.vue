<script setup lang="ts">
/** Endless, edge-faded strip of formulas. Pauses on hover. */
const props = withDefaults(defineProps<{ reverse?: boolean; speed?: number }>(), {
  reverse: false,
  speed: 40,
});

const formulas = [
  "a² + b² = c²",
  "e^{iπ} + 1 = 0",
  "∫ x dx = x²⁄2 + C",
  "x = (−b ± √(b² − 4ac)) ⁄ 2a",
  "sin²θ + cos²θ = 1",
  "A = πr²",
  "d⁄dx eˣ = eˣ",
  "V = ⁴⁄₃ πr³",
  "Σ 1⁄n² = π²⁄6",
  "log(ab) = log a + log b",
];
</script>

<template>
  <div class="marquee" aria-hidden="true">
    <div
      class="marquee-track"
      :class="{ 'marquee-track--reverse': props.reverse }"
      :style="{ '--marquee-duration': `${props.speed}s` }"
    >
      <template v-for="copy in 2" :key="copy">
        <span v-for="formula in formulas" :key="`${copy}-${formula}`" class="marquee-item">
          {{ formula }}
        </span>
      </template>
    </div>
  </div>
</template>

<style scoped>
.marquee {
  overflow: hidden;
  -webkit-mask-image: linear-gradient(90deg, transparent, #000 12%, #000 88%, transparent);
  mask-image: linear-gradient(90deg, transparent, #000 12%, #000 88%, transparent);
}
.marquee-track {
  display: flex;
  width: max-content;
  gap: 1rem;
  animation: marquee var(--marquee-duration, 40s) linear infinite;
}
.marquee-track--reverse {
  animation-direction: reverse;
}
.marquee:hover .marquee-track {
  animation-play-state: paused;
}
.marquee-item {
  flex: none;
  border: 1px solid var(--border);
  border-radius: 9999px;
  background: color-mix(in srgb, var(--card) 80%, transparent);
  padding: 0.45rem 1rem;
  font-family: ui-serif, Georgia, serif;
  font-style: italic;
  font-size: 0.95rem;
  color: var(--muted-foreground);
  white-space: nowrap;
  transition:
    color 0.3s ease,
    border-color 0.3s ease;
}
.marquee-item:hover {
  color: var(--primary);
  border-color: color-mix(in srgb, var(--primary) 50%, var(--border));
}
@keyframes marquee {
  to { transform: translateX(calc(-50% - 0.5rem)); }
}
@media (prefers-reduced-motion: reduce) {
  .marquee-track {
    animation: none;
  }
}
</style>
