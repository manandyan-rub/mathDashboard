<script setup lang="ts">
import type { FormComponentProps } from "@/types/index";
import FormInputWrapper from "./FormInputWrapper.vue";
import { InputGroup, InputGroupInput } from "@/components/ui/input-group";

defineOptions({
  inheritAttrs: false,
});

const props = defineProps<FormComponentProps>();
</script>

<template>
  <FormInputWrapper
    :id="id"
    :class="props.class"
    :description="description"
    :label="label"
    :error="error"
  >
    <InputGroup
      :class="[
        'border border-border rounded-md bg-background',
        'focus-within:ring-2 focus-within:ring-primary/30',
        inputClass,
      ]"
    >
      <InputGroupInput
        :id="id"
        v-bind="$attrs"
        class="border-0 focus-visible:ring-0 focus-visible:outline-none"
      />

      <slot />
    </InputGroup>
  </FormInputWrapper>
</template>
