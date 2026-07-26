<script setup lang="ts">
import { cmsTranslate as __ } from "@/lib/i18n";
import type {
  WorkspaceFieldDefinition,
  WorkspaceTopicDefinition,
} from "@/types/mathWorkspace";

defineProps<{
  definition: WorkspaceTopicDefinition;
  drafts: Record<string, string>;
  unit: string;
  solveFor: string | null;
}>();

defineEmits(["update:value", "update:solve-for"]);

function fieldUnit(unit: string, field: WorkspaceFieldDefinition): string {
  if (field.dimension === 0) return "°";
  if (field.dimension === 2) return `${unit}²`;
  if (field.dimension === 3) return `${unit}³`;
  return unit;
}
</script>

<template>
  <section class="workspace-section">
    <div class="workspace-section-heading">
      <div>
        <p class="workspace-eyebrow">{{ __("Known values") }}</p>
        <h2>{{ __(definition.label) }}</h2>
      </div>
      <span class="workspace-count">
        {{ definition.minimumKnownValues }} {{ __("minimum") }}
      </span>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
      <label
        v-for="field in definition.fields"
        :key="field.key"
        class="workspace-field"
      >
        <span class="workspace-field-label">
          <span>{{ __(field.label) }}</span>
          <code>{{ field.symbol }}</code>
        </span>
        <span class="workspace-input-shell">
          <input
            :value="drafts[field.key] ?? ''"
            type="number"
            step="any"
            inputmode="decimal"
            :aria-label="__(field.label)"
            @input="
              $emit(
                'update:value',
                field.key,
                ($event.target as HTMLInputElement).value,
              )
            "
          />
          <span>{{ fieldUnit(unit, field) }}</span>
        </span>
      </label>
    </div>

    <label class="workspace-field mt-4">
      <span class="workspace-field-label">
        <span>{{ __("Find") }}</span>
      </span>
      <select
        :value="solveFor ?? ''"
        @change="
          $emit(
            'update:solve-for',
            ($event.target as HTMLSelectElement).value || null,
          )
        "
      >
        <option value="">{{ __("Calculate everything possible") }}</option>
        <option
          v-for="field in definition.fields"
          :key="`find-${field.key}`"
          :value="field.key"
        >
          {{ __(field.label) }}
        </option>
      </select>
    </label>
  </section>
</template>

<style scoped>
.workspace-section {
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--card);
  padding: 1.25rem;
}

.workspace-section-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.workspace-section-heading h2 {
  margin-top: 0.2rem;
  color: var(--foreground);
  font-size: 1rem;
  font-weight: 750;
}

.workspace-eyebrow,
.workspace-count {
  color: var(--muted-foreground);
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
}

.workspace-count {
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 0.3rem 0.5rem;
}

.workspace-field {
  display: grid;
  gap: 0.35rem;
}

.workspace-field-label {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  color: var(--muted-foreground);
  font-size: 0.75rem;
  font-weight: 650;
}

.workspace-field-label code {
  color: var(--primary);
  font-size: 0.72rem;
}

.workspace-input-shell {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  min-height: 2.65rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  background: var(--background);
  overflow: hidden;
}

.workspace-input-shell:focus-within {
  border-color: var(--ring);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--ring) 16%, transparent);
}

.workspace-input-shell input,
select {
  width: 100%;
  min-width: 0;
  border: 0;
  background: transparent;
  color: var(--foreground);
  outline: none;
}

.workspace-input-shell input {
  height: 2.55rem;
  padding: 0 0.75rem;
}

.workspace-input-shell > span {
  padding: 0 0.75rem;
  color: var(--muted-foreground);
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.72rem;
}

select {
  height: 2.65rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 0 0.75rem;
}
</style>
