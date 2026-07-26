<script setup lang="ts">
import { computed } from "vue";
import { AlertTriangle, CheckCircle2 } from "lucide-vue-next";
import { cmsTranslate as __ } from "@/lib/i18n";
import { formatNumber } from "@/lib/mathWorkspace/topics";
import type { MathWorkspaceState } from "@/types/mathWorkspace";

const props = defineProps<{
  state: MathWorkspaceState;
}>();

const values = computed(() => [
  ...props.state.userEnteredValues,
  ...props.state.calculatedValues,
]);
</script>

<template>
  <section class="workspace-results">
    <div class="workspace-results-heading">
      <div>
        <p>{{ __("Calculated results") }}</p>
        <h2>{{ __("Live solution") }}</h2>
      </div>
      <CheckCircle2
        v-if="state.calculatedValues.length && !state.conflicts.length"
        :size="20"
        class="text-chart-2"
      />
      <AlertTriangle
        v-else-if="state.warnings.length || state.conflicts.length"
        :size="20"
        class="text-destructive"
      />
    </div>

    <div
      v-if="state.warnings.length"
      class="workspace-notice"
      data-tone="warning"
    >
      <p v-for="warning in state.warnings" :key="warning">
        {{ __(warning) }}
      </p>
    </div>

    <div
      v-if="state.conflicts.length"
      class="workspace-notice"
      data-tone="danger"
    >
      <p v-for="conflict in state.conflicts" :key="conflict.field">
        {{ __(conflict.label) }}: {{ __(conflict.message) }}
      </p>
    </div>

    <div v-if="values.length" class="workspace-result-grid">
      <article
        v-for="value in values"
        :key="`${value.source}-${value.key}`"
        :data-source="value.source"
        class="workspace-result"
      >
        <div>
          <span>{{ __(value.label) }}</span>
          <code>{{ value.symbol }}</code>
        </div>
        <strong>{{ formatNumber(value.value) }}</strong>
        <small>
          {{ value.unit }} ·
          {{ __(value.source === "entered" ? "Entered" : "Calculated") }}
        </small>
      </article>
    </div>

    <div v-else class="workspace-empty">
      {{ __("Enter known values to calculate the result.") }}
    </div>
  </section>
</template>

<style scoped>
.workspace-results {
  min-width: 0;
}

.workspace-results-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.workspace-results-heading p {
  color: var(--muted-foreground);
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
}

.workspace-results-heading h2 {
  margin-top: 0.2rem;
  color: var(--foreground);
  font-size: 1.05rem;
  font-weight: 750;
}

.workspace-notice {
  margin-bottom: 0.75rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 0.7rem 0.8rem;
  font-size: 0.78rem;
}

.workspace-notice[data-tone="warning"] {
  border-color: color-mix(in srgb, var(--chart-3) 38%, var(--border));
  background: color-mix(in srgb, var(--chart-3) 8%, var(--card));
  color: color-mix(in srgb, var(--chart-3) 72%, var(--foreground));
}

.workspace-notice[data-tone="danger"] {
  border-color: color-mix(in srgb, var(--destructive) 38%, var(--border));
  background: color-mix(in srgb, var(--destructive) 7%, var(--card));
  color: var(--destructive);
}

.workspace-result-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.7rem;
}

.workspace-result {
  min-width: 0;
  border: 1px solid var(--border);
  border-left: 3px solid var(--primary);
  border-radius: var(--radius-sm);
  background: var(--card);
  padding: 0.8rem;
}

.workspace-result[data-source="calculated"] {
  border-left-color: var(--chart-2);
  background: color-mix(in srgb, var(--chart-2) 5%, var(--card));
}

.workspace-result > div {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  color: var(--muted-foreground);
  font-size: 0.72rem;
  font-weight: 650;
}

.workspace-result code {
  color: var(--primary);
}

.workspace-result strong {
  display: block;
  overflow-wrap: anywhere;
  margin-top: 0.45rem;
  color: var(--foreground);
  font-size: 1.1rem;
}

.workspace-result small {
  display: block;
  margin-top: 0.2rem;
  color: var(--muted-foreground);
  font-size: 0.67rem;
}

.workspace-empty {
  display: flex;
  min-height: 9rem;
  align-items: center;
  justify-content: center;
  border: 1px dashed var(--border);
  border-radius: var(--radius-sm);
  color: var(--muted-foreground);
  padding: 1rem;
  text-align: center;
  font-size: 0.8rem;
}

@media (max-width: 36rem) {
  .workspace-result-grid {
    grid-template-columns: 1fr;
  }
}
</style>
