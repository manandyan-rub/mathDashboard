<script setup lang="ts">
import { computed } from "vue";

/**
 * Splits text into words that fly in one after another (blur + rise).
 * Keeps the full sentence available to screen readers.
 */
const props = withDefaults(
  defineProps<{
    text: string;
    delay?: number;
    stagger?: number;
    wordClass?: string;
  }>(),
  {
    delay: 0,
    stagger: 70,
    wordClass: "",
  },
);

const words = computed(() => props.text.split(/\s+/).filter(Boolean));
</script>

<template>
  <span :aria-label="text">
    <span
      v-for="(word, index) in words"
      :key="`${index}-${word}`"
      aria-hidden="true"
      class="split-word"
      :class="wordClass"
      :style="{ '--word-delay': `${delay + index * stagger}ms` }"
      >{{ word }}</span
    >{{ " " }}
  </span>
</template>
