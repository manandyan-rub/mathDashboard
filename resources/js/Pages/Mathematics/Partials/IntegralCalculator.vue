<script setup lang="ts">
import { MathfieldElement } from "mathlive";
import axios from "axios";
import { nextTick, onBeforeUnmount, onMounted, onUpdated, ref } from "vue";

const appOrigin =
  typeof window !== "undefined"
    ? window.location.origin.replace(/\/$/, "")
    : "http://127.0.0.1:8000";

MathfieldElement.fontsDirectory = `${appOrigin}/fonts/mathlive`;
MathfieldElement.soundsDirectory = `${appOrigin}/sounds/mathlive`;

type StepItem = {
  latex: string;
  explanation?: string | null;
};

type MathfieldLike = HTMLElement & {
  value?: string;
  readOnly?: boolean;
  getValue?: (format?: string) => string;
  setValue?: (value: string) => void;
};

const latex = ref("\\int x^2 \\sin(x)\\,dx");
const result = ref("");
const steps = ref<StepItem[]>([]);
const stepsNote = ref("");
const loading = ref(false);
const error = ref("");
const mathfieldRef = ref<MathfieldLike | null>(null);

const normalizeSteps = (raw: unknown): StepItem[] => {
  if (!Array.isArray(raw)) return [];
  return raw
    .map((item) => {
      if (typeof item === "string") return { latex: item, explanation: null };
      if (item && typeof item === "object") {
        return {
          latex: String((item as any).latex ?? ""),
          explanation: (item as any).explanation
            ? String((item as any).explanation)
            : null,
        };
      }
      return { latex: "", explanation: null };
    })
    .filter((s) => s.latex.trim() !== "");
};

function looksLikeLatex(str: string): boolean {
  return /[\\^_{}]/.test(str) || /\\(int|frac|sqrt|sum|pi|infty)/.test(str);
}

const syncLatexFromMathfield = () => {
  const field = mathfieldRef.value;
  if (!field) return;
  if (typeof field.getValue === "function") {
    latex.value = field.getValue("latex");
  } else {
    latex.value = field.value ?? "";
  }
};

let detachListener: (() => void) | null = null;

onMounted(() => {
  const field = mathfieldRef.value;
  if (!field) return;
  if (typeof field.setValue === "function") {
    field.setValue(latex.value);
  } else {
    field.value = latex.value;
  }
  const onInput = () => syncLatexFromMathfield();
  field.addEventListener("input", onInput);
  detachListener = () => field.removeEventListener("input", onInput);
});

onBeforeUnmount(() => {
  detachListener?.();
});

function setMathFieldValue(field: MathfieldLike, value: string) {
  if (typeof field.setValue === "function") field.setValue(value);
  else field.value = value;
}

function injectMathField(container: HTMLElement, value: string) {
  const existing = container.querySelector("math-field");
  if (existing) {
    setMathFieldValue(existing as MathfieldLike, value);
    (existing as HTMLElement).style.color = "var(--foreground)";
    return;
  }
  const mf = document.createElement("math-field") as MathfieldLike;
  mf.readOnly = true;
  mf.setAttribute("read-only", "");
  mf.style.cssText =
    "background:transparent;border:none;pointer-events:none;display:block;width:100%;color:var(--foreground);";
  container.appendChild(mf);
  requestAnimationFrame(() => setMathFieldValue(mf, value));
}

function renderAll() {
  const resultEl = document.querySelector<HTMLElement>(
    "[data-result-container]",
  );
  if (resultEl && result.value) {
    injectMathField(resultEl, result.value);
  }

  document.querySelectorAll<HTMLElement>("[data-step-container]").forEach((el) => {
    const latexValue = el.dataset.stepLatex ?? "";
    if (latexValue.trim()) {
      injectMathField(el, latexValue);
    }
  });
}

onUpdated(() => renderAll());

async function calculate() {
  loading.value = true;
  error.value = "";
  result.value = "";
  steps.value = [];
  stepsNote.value = "";

  try {
    const response = await axios.post("/mathematics/integral/calculate", {
      latex: latex.value,
    });
    result.value = response.data?.result ?? "";
    steps.value = normalizeSteps(response.data?.steps);
    stepsNote.value = String(response.data?.steps_note ?? "");
  } catch (err: any) {
    error.value =
      err?.response?.data?.message ||
      err?.message ||
      "Հաշվարկի ընթացքում ինչ-որ բան սխալ է տեղի ունեցել";
  } finally {
    loading.value = false;
    await nextTick();
    renderAll();
  }
}
</script>

<template>
  <div
    class="mx-auto max-w-3xl rounded-2xl border border-border bg-card p-6 text-card-foreground shadow-sm transition-colors duration-300"
  >
    <div class="mb-4">
      <h1 class="text-2xl font-bold">Ինտեգրալ հաշվիչ</h1>
    </div>

    <label
      class="mb-2 block text-sm text-muted-foreground"
    >
      Մուտքագրեք ինտեգրալ
    </label>

    <form @submit.prevent="calculate">
      <math-field
        id="integral-input"
        ref="mathfieldRef"
        class="block min-h-[64px] w-full rounded-xl border border-border bg-muted/70 px-4 py-3 text-lg transition-colors dark:bg-background"
      >
        \int x^2 \sin(x)\,dx
      </math-field>

      <button
        type="submit"
        class="mt-4 rounded-xl bg-primary px-4 py-2 font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
        :disabled="loading"
      >
        {{ loading ? "Հաշվարկվում է..." : "Հաշվարկել" }}
      </button>
    </form>

    <div
      v-if="error"
      class="mt-4 rounded-xl border border-destructive/30 bg-destructive/10 p-3 text-destructive"
    >
      {{ error }}
    </div>

    <div v-if="steps.length > 0" class="mt-6 space-y-3">
      <div
        class="text-sm font-semibold uppercase tracking-widest text-muted-foreground"
      >
        Լուծման քայլեր
      </div>

      <div
        v-for="(step, i) in steps"
        :key="i"
        class="rounded-xl border border-border bg-muted/50 p-4 transition-colors dark:bg-background/40"
      >
        <div class="flex items-start gap-3">
          <span
            class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground"
          >
            {{ i + 1 }}
          </span>

          <div class="min-w-0 flex-1 space-y-1">
            <div
              v-if="step.explanation"
              class="text-sm text-muted-foreground"
            >
              {{ step.explanation }}
            </div>

            <div
              v-if="looksLikeLatex(step.latex)"
              :data-step-container="i"
              :data-step-latex="step.latex"
              class="min-h-[2rem] overflow-x-auto"
            />
            <pre
              v-else
              class="overflow-x-auto rounded-lg border border-border bg-card px-3 py-2 font-mono text-sm text-foreground"
              >{{ step.latex }}</pre
            >
          </div>
        </div>
      </div>
    </div>

    <div
      v-else-if="result && stepsNote"
      class="mt-4 rounded-xl border border-border bg-muted/50 p-3 text-sm text-muted-foreground"
    >
      {{ stepsNote }}
    </div>

    <div
      v-if="result"
      class="mt-4 rounded-xl border border-border bg-muted/45 p-4 transition-colors dark:bg-background/40"
    >
      <div
        class="mb-2 text-sm font-semibold uppercase tracking-widest text-muted-foreground"
      >
        Արդյունք
      </div>

      <div
        v-if="looksLikeLatex(result)"
        data-result-container
        class="min-h-[2rem] overflow-x-auto rounded-lg border border-border bg-card px-3 py-3"
      />
      <pre
        v-else
        class="overflow-x-auto rounded-lg border border-border bg-card px-3 py-2 font-mono text-sm text-foreground"
        >{{ result }}</pre
      >
    </div>
  </div>
</template>

<style scoped>
math-field {
  width: 100%;
  color: var(--foreground);
  --caret-color: var(--foreground);
  --primary: var(--ring);
  --selection-background-color: color-mix(in srgb, var(--ring) 25%, transparent);
  --keyboard-zindex: 50;
}

math-field:focus-within {
  outline: 2px solid var(--ring);
  outline-offset: 2px;
}

math-field[read-only] {
  --caret-color: transparent;
  pointer-events: none;
}
</style>
