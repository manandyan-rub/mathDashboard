<script setup lang="ts">
import { computed, ref } from "vue";
import type { FormComponentProps } from "@/types/index";
import type { DateRange } from "reka-ui";
import { Button } from "@/components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { RangeCalendar } from "@/components/ui/range-calendar";
import { Calendar, X } from "lucide-vue-next";
import { cn } from "@/lib/utils";
import FormInputWrapper from "./FormInputWrapper.vue";

defineOptions({
  inheritAttrs: false,
});

type CalendarRange = DateRange | null;

const props = withDefaults(
  defineProps<
    Omit<FormComponentProps, "inputClass"> & {
      modelValue?: CalendarRange;
      placeholder?: string;
      disabled?: boolean;
      numberOfMonths?: number;
      clearable?: boolean;
      triggerClass?: string;
      calendarClass?: string;
    }
  >(),
  {
    modelValue: null,
    placeholder: "Select date range",
    disabled: false,
    numberOfMonths: 2,
    clearable: true,
    triggerClass: "",
    calendarClass: "",
  },
);

const emit = defineEmits<{
  (e: "update:modelValue", value: CalendarRange): void;
  (e: "change", value: CalendarRange): void;
}>();

const open = ref(false);

const hasValue = computed(() => {
  return Boolean(props.modelValue?.start || props.modelValue?.end);
});

const displayValue = computed(() => {
  const start = datePartToString(props.modelValue?.start);
  const end = datePartToString(props.modelValue?.end);

  if (!start && !end) {
    return props.placeholder;
  }

  if (start && end) {
    return start === end ? start : `${start} - ${end}`;
  }

  if (start) {
    return `${start} - ...`;
  }

  return `... - ${end}`;
});

function datePartToString(value: unknown): string {
  if (!value) {
    return "";
  }

  if (typeof value === "string") {
    return value;
  }

  if (typeof value === "object" && value !== null && "toString" in value) {
    const asString = String(value);
    return asString;
  }

  return String(value);
}

function onRangeChange(value: CalendarRange): void {
  emit("update:modelValue", value);
  emit("change", value);
}

function clearValue(): void {
  onRangeChange(null);
}
</script>

<template>
  <FormInputWrapper
    :id="id"
    :class="props.class"
    :description="description"
    :label="label"
    :error="error"
  >
    <div class="flex items-center gap-2">
      <Popover v-model:open="open">
        <PopoverTrigger as-child>
          <Button
            :id="id"
            type="button"
            variant="outline"
            :disabled="disabled"
            :class="
              cn(
                "w-full justify-start text-left font-normal",
                "border border-border bg-background",
                !hasValue && "text-muted-foreground",
                triggerClass,
              )
            "
          >
            <Calendar class="mr-2 h-4 w-4" />
            <span class="truncate">{{ displayValue }}</span>
          </Button>
        </PopoverTrigger>

        <PopoverContent class="w-auto p-0" align="start">
          <RangeCalendar
            v-bind="$attrs"
            :model-value="modelValue"
            :number-of-months="numberOfMonths"
            :class="cn('rounded-md', calendarClass)"
            @update:model-value="onRangeChange"
          />
        </PopoverContent>
      </Popover>

      <Button
        v-if="clearable && hasValue"
        type="button"
        variant="outline"
        size="icon"
        :disabled="disabled"
        class="shrink-0"
        @click="clearValue"
      >
        <X class="h-4 w-4" />
      </Button>
    </div>
  </FormInputWrapper>
</template>
