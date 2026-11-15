<script setup lang="ts">
import { cmsTranslate as __ } from "@/lib/i18n";
import type { TeacherClass } from "@/types/teacher";
import axios from "axios";
import { GraduationCap, Loader2 } from "lucide-vue-next";
import { onMounted, ref } from "vue";

const props = withDefaults(
  defineProps<{
    modelValue: number | null;
    disabled?: boolean;
  }>(),
  {
    disabled: false,
  },
);

const emit = defineEmits<{
  "update:modelValue": [value: number | null];
}>();

const classes = ref<TeacherClass[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

async function loadClasses() {
  loading.value = true;
  error.value = null;

  try {
    const response = await axios.get<{ classes?: TeacherClass[] }>(
      "/api/teacher/exercises/classes",
    );
    classes.value = response.data?.classes ?? [];
  } catch (caught: any) {
    error.value =
      caught?.response?.data?.message ||
      caught?.message ||
      __("Failed to load classes.");
  } finally {
    loading.value = false;
  }
}

function updateSelection(event: Event) {
  const value = (event.target as HTMLSelectElement).value;
  emit("update:modelValue", value === "" ? null : Number(value));
}

onMounted(loadClasses);
</script>

<template>
  <div class="space-y-1">
    <label
      class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground"
    >
      <GraduationCap :size="14" />
      {{ __("Class") }}
    </label>

    <div class="relative">
      <select
        :value="modelValue ?? ''"
        :disabled="disabled || loading"
        class="w-full rounded-lg border border-border bg-card px-3 py-2 pr-9 text-sm text-muted-foreground outline-none ring-ring transition focus:ring disabled:cursor-not-allowed disabled:opacity-60 dark:border-border dark:bg-card/60 dark:text-foreground"
        @change="updateSelection"
      >
        <option value="">
          {{ loading ? __("Loading classes...") : __("Unassigned") }}
        </option>
        <option
          v-for="schoolClass in classes"
          :key="schoolClass.id"
          :value="schoolClass.id"
        >
          {{ schoolClass.name }} · {{ schoolClass.school.name }} ·
          {{ schoolClass.academic_year }}
        </option>
      </select>

      <Loader2
        v-if="loading"
        :size="15"
        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 animate-spin text-muted-foreground"
      />
    </div>

    <p v-if="error" class="text-xs text-destructive">
      {{ error }}
    </p>
    <p
      v-else-if="!loading && classes.length === 0"
      class="text-xs text-muted-foreground"
    >
      {{ __("No classes assigned.") }}
    </p>
  </div>
</template>
