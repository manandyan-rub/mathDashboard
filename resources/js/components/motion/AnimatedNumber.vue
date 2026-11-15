<script setup lang="ts">
import { tween } from "@/lib/motion";
import { onBeforeUnmount, onMounted, ref, watch } from "vue";

const props = withDefaults(
  defineProps<{
    value: number;
    duration?: number;
    format?: (value: number) => string;
  }>(),
  {
    duration: 1400,
    format: (value: number) => new Intl.NumberFormat().format(value),
  },
);

const display = ref(0);
const bump = ref(false);
let cancel: () => void = () => {};
let bumpTimer = 0;

function animateTo(target: number) {
  cancel();
  cancel = tween(display.value, target, props.duration, (value) => {
    display.value = Math.round(value);
  });
  bump.value = false;
  window.clearTimeout(bumpTimer);
  requestAnimationFrame(() => {
    bump.value = true;
    bumpTimer = window.setTimeout(() => (bump.value = false), props.duration);
  });
}

onMounted(() => animateTo(props.value));
watch(
  () => props.value,
  (value) => animateTo(value),
);
onBeforeUnmount(() => {
  cancel();
  window.clearTimeout(bumpTimer);
});
</script>

<template>
  <span class="animated-number" :class="{ 'is-counting': bump }">{{
    format(display)
  }}</span>
</template>
