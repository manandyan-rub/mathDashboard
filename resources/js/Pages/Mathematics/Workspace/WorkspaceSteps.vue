<script setup lang="ts">
import { ChevronDown } from "lucide-vue-next";
import { cmsTranslate as __ } from "@/lib/i18n";
import type { WorkspaceCalculationStep } from "@/types/mathWorkspace";

defineProps<{
  steps: WorkspaceCalculationStep[];
}>();
</script>

<template>
  <section class="workspace-steps">
    <div class="workspace-steps-heading">
      <p>{{ __("Steps") }}</p>
      <h2>{{ __("How it is calculated") }}</h2>
    </div>

    <div v-if="steps.length" class="space-y-2">
      <details
        v-for="(step, index) in steps"
        :key="step.id"
        class="workspace-step"
        :open="index === 0"
      >
        <summary>
          <span>
            <small>{{ __("Step :number", { number: String(index + 1) }) }}</small>
            <strong>{{ __(step.title) }}</strong>
          </span>
          <ChevronDown :size="16" />
        </summary>
        <div class="workspace-step-body">
          <code>{{ step.formula }}</code>
          <p>{{ step.substitution }}</p>
          <strong>{{ step.result }}</strong>
          <small v-if="step.rule">{{ __(step.rule) }}</small>
        </div>
      </details>
    </div>

    <div v-else class="workspace-step-empty">
      {{ __("Calculation steps appear after the shape is solved.") }}
    </div>
  </section>
</template>

<style scoped>
.workspace-steps {
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--card);
  padding: 1.25rem;
}

.workspace-steps-heading {
  margin-bottom: 1rem;
}

.workspace-steps-heading p {
  color: var(--muted-foreground);
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
}

.workspace-steps-heading h2 {
  margin-top: 0.2rem;
  color: var(--foreground);
  font-size: 1rem;
  font-weight: 750;
}

.workspace-step {
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  overflow: hidden;
}

.workspace-step summary {
  display: flex;
  min-height: 3.2rem;
  cursor: pointer;
  list-style: none;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.7rem 0.85rem;
}

.workspace-step summary::-webkit-details-marker {
  display: none;
}

.workspace-step summary span {
  display: grid;
  gap: 0.15rem;
}

.workspace-step summary small {
  color: var(--muted-foreground);
  font-size: 0.62rem;
  font-weight: 700;
  text-transform: uppercase;
}

.workspace-step summary strong {
  color: var(--foreground);
  font-size: 0.82rem;
}

.workspace-step[open] summary svg {
  transform: rotate(180deg);
}

.workspace-step-body {
  display: grid;
  gap: 0.55rem;
  border-top: 1px solid var(--border);
  background: color-mix(in srgb, var(--muted) 38%, var(--card));
  padding: 0.85rem;
}

.workspace-step-body code {
  color: var(--primary);
  font-size: 0.78rem;
}

.workspace-step-body p,
.workspace-step-body small {
  color: var(--muted-foreground);
  font-size: 0.74rem;
}

.workspace-step-body strong {
  color: var(--foreground);
  font-size: 0.9rem;
}

.workspace-step-empty {
  border: 1px dashed var(--border);
  border-radius: var(--radius-sm);
  color: var(--muted-foreground);
  padding: 1.2rem;
  text-align: center;
  font-size: 0.78rem;
}
</style>
