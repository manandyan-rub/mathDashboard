<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { FormComponentProps } from '@/types/index';


const props = defineProps<Omit<FormComponentProps, "inputClass">>();
</script>

<template>
  <div :class="cn('group/input flex flex-col gap-1.5', props.class)">
    <Label
      v-if="label"
      :class="[error && 'text-destructive', 'capitalize']"
      :for="id"
    >
      {{ label }}
    </Label>
    <slot />
    <p v-if="description">{{ description }}</p>
  </div>
</template>
