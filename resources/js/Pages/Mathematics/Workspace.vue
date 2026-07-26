<script setup lang="ts">
import { computed, ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import {
  BookOpen,
  ExternalLink,
  History,
  Lightbulb,
  Play,
  RotateCcw,
  Save,
  Sparkles,
  WandSparkles,
} from "lucide-vue-next";
import AdminFormLayout from "@/Layouts/AdminFormLayout.vue";
import { Button } from "@/components/ui/button";
import { useMathWorkspace } from "@/composables/useMathWorkspace";
import { LENGTH_UNITS } from "@/lib/geometry/units";
import { cmsTranslate as __ } from "@/lib/i18n";
import { WORKSPACE_TOPICS } from "@/lib/mathWorkspace/topics";
import WorkspaceFieldGrid from "@/Pages/Mathematics/Workspace/WorkspaceFieldGrid.vue";
import WorkspaceResults from "@/Pages/Mathematics/Workspace/WorkspaceResults.vue";
import WorkspaceSteps from "@/Pages/Mathematics/Workspace/WorkspaceSteps.vue";
import WorkspaceVisualization from "@/Pages/Mathematics/Workspace/WorkspaceVisualization.vue";
import type { SavedMathWorkspaceCalculation } from "@/types/mathWorkspace";

const props = defineProps<{
  savedCalculations: SavedMathWorkspaceCalculation[];
}>();

const {
  selectedTopic,
  unit,
  solveFor,
  problemText,
  drafts,
  practice,
  topicDefinition,
  state,
  selectTopic,
  updateDraft,
  interpret,
  reset,
  createPractice,
  loadSaved,
} = useMathWorkspace();

const isSaving = ref(false);
const saveError = ref("");
const showPracticeAnswer = ref(false);
const saveTitle = ref("");
const canSave = computed(
  () =>
    state.value.calculatedValues.length > 0 &&
    state.value.conflicts.length === 0,
);
const resolvedSaveTitle = computed(
  () =>
    saveTitle.value.trim() ||
    `${__(topicDefinition.value.label)} ${__("calculation")}`,
);

function saveCalculation() {
  if (!canSave.value || isSaving.value) return;

  router.post(
    route("mathematics.workspace.saves.store"),
    {
      title: resolvedSaveTitle.value,
      topic: selectedTopic.value,
      problem_text: problemText.value.trim() || null,
      state: state.value,
    },
    {
      preserveScroll: true,
      preserveState: true,
      onStart: () => {
        isSaving.value = true;
        saveError.value = "";
      },
      onFinish: () => {
        isSaving.value = false;
      },
      onSuccess: () => {
        saveTitle.value = "";
      },
      onError: () => {
        saveError.value =
          "The calculation could not be saved. Check the entered values and try again.";
      },
    },
  );
}

function generatePractice() {
  showPracticeAnswer.value = false;
  createPractice();
}

function reopen(calculation: SavedMathWorkspaceCalculation) {
  loadSaved(calculation);
  window.scrollTo({ top: 0, behavior: "smooth" });
}

function formattedDate(value: string): string {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function savedTopicLabel(topic: SavedMathWorkspaceCalculation["topic"]): string {
  return __(WORKSPACE_TOPICS.find((item) => item.key === topic)?.label ?? topic);
}
</script>

<template>
  <Head :title="__('Math Workspace')" />

  <AdminFormLayout :title="__('Math Workspace')" content-width="big">
    <div class="math-workspace">
      <header class="workspace-header">
        <div>
          <div class="workspace-kicker">
            <Sparkles :size="14" />
            {{ __("Universal Math Workspace") }}
          </div>
          <h1>{{ __("Calculate, inspect, and continue") }}</h1>
          <p>
            {{ __(topicDefinition.category) }} · {{ __(topicDefinition.label) }}
          </p>
        </div>

        <div class="workspace-header-actions">
          <label>
            <span class="sr-only">{{ __("Save title") }}</span>
            <input
              v-model="saveTitle"
              type="text"
              maxlength="120"
              :placeholder="__('Save title')"
            />
          </label>
          <Button
            variant="outline"
            :disabled="!canSave || isSaving"
            @click="saveCalculation"
          >
            <Save :size="16" />
            {{ isSaving ? __("Saving...") : __("Save") }}
          </Button>
          <Button variant="outline" @click="reset">
            <RotateCcw :size="16" />
            {{ __("Reset") }}
          </Button>
        </div>
      </header>

      <p v-if="saveError" class="workspace-save-error" role="alert">
        {{ __(saveError) }}
      </p>

      <nav class="workspace-topic-tabs" :aria-label="__('Math topics')">
        <button
          v-for="item in WORKSPACE_TOPICS"
          :key="item.key"
          type="button"
          :class="{ 'is-active': selectedTopic === item.key }"
          @click="selectTopic(item.key)"
        >
          {{ __(item.label) }}
        </button>
      </nav>

      <section class="workspace-problem">
        <div class="workspace-problem-heading">
          <div>
            <p>{{ __("Problem input") }}</p>
            <h2>{{ __("Describe the problem") }}</h2>
          </div>
          <label class="workspace-unit">
            <span>{{ __("Unit") }}</span>
            <select v-model="unit">
              <option v-for="item in LENGTH_UNITS" :key="item" :value="item">
                {{ item }}
              </option>
            </select>
          </label>
        </div>

        <div class="workspace-problem-composer">
          <textarea
            v-model="problemText"
            rows="3"
            maxlength="2000"
            :placeholder="
              __(
                'A rectangle has an area of 48 cm² and a height of 6 cm. Find its width.',
              )
            "
            @keydown.ctrl.enter.prevent="interpret"
            @keydown.meta.enter.prevent="interpret"
          />
          <Button @click="interpret">
            <WandSparkles :size="17" />
            {{ __("Interpret problem") }}
          </Button>
        </div>
      </section>

      <div class="workspace-body">
        <aside class="workspace-sidebar">
          <WorkspaceFieldGrid
            :definition="topicDefinition"
            :drafts="drafts"
            :unit="unit"
            :solve-for="solveFor"
            @update:value="updateDraft"
            @update:solve-for="solveFor = $event"
          />

          <section class="workspace-related">
            <div>
              <p>{{ __("Related formulas") }}</p>
              <h2>{{ __("Formula set") }}</h2>
            </div>
            <ul>
              <li v-for="formula in state.formulas" :key="formula.key">
                <span>{{ __(formula.label) }}</span>
                <code>{{ formula.expression }}</code>
              </li>
            </ul>
            <Link
              :href="route('formula.index')"
              class="workspace-text-link"
            >
              <BookOpen :size="15" />
              {{ __("Open formula library") }}
            </Link>
          </section>
        </aside>

        <main class="workspace-main">
          <div class="workspace-live-grid">
            <section class="workspace-panel">
              <WorkspaceResults :state="state" />
            </section>
            <section class="workspace-panel">
              <WorkspaceVisualization :state="state" />
            </section>
          </div>

          <div class="workspace-detail-grid">
            <WorkspaceSteps :steps="state.calculationSteps" />

            <section class="workspace-actions-panel">
              <div class="workspace-action-heading">
                <div>
                  <p>{{ __("Continue") }}</p>
                  <h2>{{ __("Practice and explore") }}</h2>
                </div>
                <Lightbulb :size="20" class="text-chart-3" />
              </div>

              <div class="workspace-action-buttons">
                <Button
                  :disabled="!state.calculatedValues.length"
                  @click="generatePractice"
                >
                  <Play :size="16" />
                  {{ __("Practice this topic") }}
                </Button>
                <Button variant="outline" as-child>
                  <Link :href="route(topicDefinition.calculatorRoute)">
                    <ExternalLink :size="16" />
                    {{ __("Open full calculator") }}
                  </Link>
                </Button>
              </div>

              <article v-if="practice" class="workspace-practice">
                <p>{{ __(practice.promptKey, practice.replacements) }}</p>
                <button
                  type="button"
                  @click="showPracticeAnswer = !showPracticeAnswer"
                >
                  {{
                    showPracticeAnswer
                      ? __("Hide answer")
                      : __("Show answer")
                  }}
                </button>
                <strong v-if="showPracticeAnswer">
                  {{ __(practice.answerLabel) }}: {{ practice.answer }}
                </strong>
              </article>
            </section>
          </div>

          <section class="workspace-history">
            <div class="workspace-history-heading">
              <div>
                <p>{{ __("Saved work") }}</p>
                <h2>{{ __("Recent calculations") }}</h2>
              </div>
              <History :size="19" />
            </div>

            <div v-if="props.savedCalculations.length" class="workspace-history-list">
              <button
                v-for="calculation in props.savedCalculations"
                :key="calculation.id"
                type="button"
                @click="reopen(calculation)"
              >
                <span>
                  <strong>{{ calculation.title }}</strong>
                  <small>
                    {{ savedTopicLabel(calculation.topic) }} ·
                    {{ formattedDate(calculation.created_at) }}
                  </small>
                </span>
                <ExternalLink :size="15" />
              </button>
            </div>
            <div v-else class="workspace-history-empty">
              {{ __("Saved calculations will appear here.") }}
            </div>
          </section>
        </main>
      </div>
    </div>
  </AdminFormLayout>
</template>

<style scoped>
.math-workspace {
  display: grid;
  gap: 1rem;
  padding: 1.25rem;
}

.workspace-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1.5rem;
  border-bottom: 1px solid var(--border);
  padding-bottom: 1rem;
}

.workspace-kicker {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  color: var(--primary);
  font-size: 0.72rem;
  font-weight: 750;
  text-transform: uppercase;
}

.workspace-header h1 {
  margin-top: 0.4rem;
  color: var(--foreground);
  font-size: 1.65rem;
  font-weight: 800;
}

.workspace-header p {
  margin-top: 0.2rem;
  color: var(--muted-foreground);
  font-size: 0.82rem;
}

.workspace-save-error {
  border: 1px solid color-mix(in srgb, var(--destructive) 42%, var(--border));
  border-radius: var(--radius-sm);
  background: color-mix(in srgb, var(--destructive) 7%, var(--card));
  color: var(--destructive);
  padding: 0.7rem 0.85rem;
  font-size: 0.78rem;
}

.workspace-header-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
}

.workspace-header-actions input {
  width: 12rem;
  height: 2.5rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  background: var(--background);
  color: var(--foreground);
  padding: 0 0.75rem;
  outline: none;
}

.workspace-header-actions input:focus {
  border-color: var(--ring);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--ring) 14%, transparent);
}

.workspace-topic-tabs {
  display: grid;
  grid-template-columns: repeat(6, minmax(7.5rem, 1fr));
  gap: 0.4rem;
  overflow-x: auto;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--card);
  padding: 0.4rem;
}

.workspace-topic-tabs button {
  min-height: 2.55rem;
  border-radius: var(--radius-sm);
  color: var(--muted-foreground);
  padding: 0 0.75rem;
  font-size: 0.76rem;
  font-weight: 700;
  white-space: nowrap;
}

.workspace-topic-tabs button:hover {
  background: var(--muted);
  color: var(--foreground);
}

.workspace-topic-tabs button.is-active {
  background: var(--primary);
  color: var(--primary-foreground);
}

.workspace-problem,
.workspace-panel,
.workspace-related,
.workspace-actions-panel,
.workspace-history {
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--card);
  padding: 1.25rem;
}

.workspace-problem-heading,
.workspace-history-heading,
.workspace-action-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0.8rem;
}

.workspace-problem-heading p,
.workspace-related > div p,
.workspace-history-heading p,
.workspace-action-heading p {
  color: var(--muted-foreground);
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
}

.workspace-problem-heading h2,
.workspace-related > div h2,
.workspace-history-heading h2,
.workspace-action-heading h2 {
  margin-top: 0.2rem;
  color: var(--foreground);
  font-size: 1rem;
  font-weight: 750;
}

.workspace-unit {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  color: var(--muted-foreground);
  font-size: 0.72rem;
  font-weight: 700;
}

.workspace-unit select {
  height: 2.3rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  background: var(--background);
  color: var(--foreground);
  padding: 0 1.8rem 0 0.65rem;
}

.workspace-problem-composer {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: end;
  gap: 0.75rem;
}

.workspace-problem-composer textarea {
  width: 100%;
  min-height: 5.2rem;
  resize: vertical;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  background: var(--background);
  color: var(--foreground);
  padding: 0.8rem;
  outline: none;
}

.workspace-problem-composer textarea:focus {
  border-color: var(--ring);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--ring) 14%, transparent);
}

.workspace-body {
  display: grid;
  grid-template-columns: minmax(18rem, 22rem) minmax(0, 1fr);
  align-items: start;
  gap: 1rem;
}

.workspace-sidebar,
.workspace-main {
  display: grid;
  min-width: 0;
  gap: 1rem;
}

.workspace-related ul {
  display: grid;
  gap: 0.55rem;
  margin-top: 0.9rem;
}

.workspace-related li {
  display: grid;
  gap: 0.25rem;
  border-bottom: 1px solid var(--border);
  padding-bottom: 0.55rem;
}

.workspace-related li:last-child {
  border-bottom: 0;
}

.workspace-related li span {
  color: var(--muted-foreground);
  font-size: 0.7rem;
}

.workspace-related li code {
  overflow-wrap: anywhere;
  color: var(--foreground);
  font-size: 0.78rem;
}

.workspace-text-link {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  margin-top: 0.85rem;
  color: var(--primary);
  font-size: 0.76rem;
  font-weight: 700;
}

.workspace-live-grid {
  display: grid;
  grid-template-columns: minmax(18rem, 0.8fr) minmax(24rem, 1.2fr);
  gap: 1rem;
}

.workspace-detail-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.15fr) minmax(18rem, 0.85fr);
  align-items: start;
  gap: 1rem;
}

.workspace-action-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 0.55rem;
}

.workspace-practice {
  display: grid;
  gap: 0.65rem;
  margin-top: 1rem;
  border-left: 3px solid var(--chart-3);
  border-radius: var(--radius-sm);
  background: color-mix(in srgb, var(--chart-3) 7%, var(--card));
  padding: 0.85rem;
}

.workspace-practice p {
  color: var(--foreground);
  font-size: 0.82rem;
  line-height: 1.55;
}

.workspace-practice button {
  width: fit-content;
  color: var(--primary);
  font-size: 0.72rem;
  font-weight: 700;
}

.workspace-practice strong {
  color: var(--foreground);
  font-size: 0.82rem;
}

.workspace-history-list {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.65rem;
}

.workspace-history-list button {
  display: flex;
  min-width: 0;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 0.75rem;
  text-align: left;
}

.workspace-history-list button:hover {
  border-color: var(--ring);
  background: var(--accent);
}

.workspace-history-list button span {
  display: grid;
  min-width: 0;
  gap: 0.2rem;
}

.workspace-history-list strong,
.workspace-history-list small {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.workspace-history-list strong {
  color: var(--foreground);
  font-size: 0.78rem;
}

.workspace-history-list small {
  color: var(--muted-foreground);
  font-size: 0.67rem;
}

.workspace-history-empty {
  border: 1px dashed var(--border);
  border-radius: var(--radius-sm);
  color: var(--muted-foreground);
  padding: 1.1rem;
  text-align: center;
  font-size: 0.78rem;
}

@media (max-width: 75rem) {
  .workspace-live-grid,
  .workspace-detail-grid {
    grid-template-columns: 1fr;
  }

  .workspace-history-list {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 62rem) {
  .workspace-header {
    align-items: flex-start;
    flex-direction: column;
  }

  .workspace-header-actions {
    width: 100%;
    justify-content: flex-start;
  }

  .workspace-body {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 42rem) {
  .math-workspace {
    padding: 0.75rem;
  }

  .workspace-header-actions input {
    width: 100%;
  }

  .workspace-header-actions label {
    width: 100%;
  }

  .workspace-problem-composer {
    grid-template-columns: 1fr;
  }

  .workspace-history-list {
    grid-template-columns: 1fr;
  }
}
</style>
