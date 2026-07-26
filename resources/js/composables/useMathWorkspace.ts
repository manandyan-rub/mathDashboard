import { computed, reactive, ref, watch } from "vue";
import { fromBaseUnit, toBaseUnit } from "@/lib/geometry/units";
import { interpretWorkspaceProblem } from "@/lib/mathWorkspace/interpretProblem";
import { generateWorkspacePractice } from "@/lib/mathWorkspace/practice";
import {
  solveWorkspace,
  workspaceTopic,
} from "@/lib/mathWorkspace/topics";
import type { LengthUnit } from "@/types/geometryCalculator";
import type {
  SavedMathWorkspaceCalculation,
  WorkspacePractice,
  WorkspaceTopicKey,
} from "@/types/mathWorkspace";

export function useMathWorkspace() {
  const selectedTopic = ref<WorkspaceTopicKey>("rectangle");
  const unit = ref<LengthUnit>("cm");
  const solveFor = ref<string | null>(null);
  const problemText = ref("");
  const parserWarnings = ref<string[]>([]);
  const practice = ref<WorkspacePractice | null>(null);
  const drafts = reactive<Record<string, string>>({});
  let suspendUnitConversion = false;

  const topicDefinition = computed(() =>
    workspaceTopic(selectedTopic.value),
  );
  const inputValues = computed(() =>
    Object.fromEntries(
      topicDefinition.value.fields.map(({ key }) => {
        const raw = drafts[key]?.trim() ?? "";
        return [key, raw === "" ? null : Number(raw)];
      }),
    ),
  );
  const state = computed(() => {
    const solved = solveWorkspace({
      topic: selectedTopic.value,
      values: inputValues.value,
      unit: unit.value,
      solveFor: solveFor.value,
    });

    return {
      ...solved,
      warnings: [...parserWarnings.value, ...solved.warnings].filter(
        (warning, index, warnings) =>
          warnings.indexOf(warning) === index,
      ),
    };
  });

  watch(unit, (nextUnit, previousUnit) => {
    if (suspendUnitConversion) return;

    for (const field of topicDefinition.value.fields) {
      const raw = drafts[field.key]?.trim() ?? "";
      const value = Number(raw);
      if (raw === "" || !Number.isFinite(value) || field.dimension === 0) {
        continue;
      }

      drafts[field.key] = formatEditable(
        fromBaseUnit(
          toBaseUnit(value, previousUnit, field.dimension),
          nextUnit,
          field.dimension,
        ),
      );
    }
  });

  function selectTopic(topic: WorkspaceTopicKey) {
    if (topic === selectedTopic.value) return;
    selectedTopic.value = topic;
    solveFor.value = null;
    parserWarnings.value = [];
    practice.value = null;
    clearDrafts();
  }

  function updateDraft(key: string, value: unknown) {
    drafts[key] = String(value ?? "");
    parserWarnings.value = [];
  }

  function interpret() {
    const interpretation = interpretWorkspaceProblem(
      problemText.value,
      unit.value,
    );
    parserWarnings.value = interpretation.warnings;

    if (!interpretation.topic) return;

    selectedTopic.value = interpretation.topic;
    suspendUnitConversion = true;
    unit.value = interpretation.unit;
    suspendUnitConversion = false;
    solveFor.value = interpretation.solveFor;
    clearDrafts();
    for (const [key, value] of Object.entries(interpretation.values)) {
      drafts[key] = formatEditable(value);
    }
    practice.value = null;
  }

  function reset() {
    problemText.value = "";
    solveFor.value = null;
    parserWarnings.value = [];
    practice.value = null;
    clearDrafts();
  }

  function createPractice() {
    practice.value = generateWorkspacePractice(
      selectedTopic.value,
      unit.value,
    );
  }

  function loadSaved(calculation: SavedMathWorkspaceCalculation) {
    selectedTopic.value = calculation.topic;
    suspendUnitConversion = true;
    unit.value = calculation.state.units.length;
    suspendUnitConversion = false;
    solveFor.value = calculation.state.solveFor;
    problemText.value = calculation.problem_text ?? "";
    parserWarnings.value = [];
    practice.value = null;
    clearDrafts();

    for (const value of calculation.state.userEnteredValues) {
      drafts[value.key] = formatEditable(value.value);
    }
  }

  function clearDrafts() {
    for (const key of Object.keys(drafts)) delete drafts[key];
    for (const field of topicDefinition.value.fields) drafts[field.key] = "";
  }

  clearDrafts();

  return {
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
  };
}

function formatEditable(value: number): string {
  if (!Number.isFinite(value)) return "";
  const absolute = Math.abs(value);
  if ((absolute > 0 && absolute < 1e-8) || absolute >= 1e15) {
    return value.toExponential(8);
  }
  return String(Number(value.toPrecision(12)));
}
