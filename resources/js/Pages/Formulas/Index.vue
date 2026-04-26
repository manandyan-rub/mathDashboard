<script setup lang="ts">
import "mathlive";
import { MathfieldElement } from "mathlive";
import {
  ref,
  computed,
  onMounted,
  onUpdated,
  onBeforeUnmount,
  nextTick,
  watch,
} from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import {
  ArrowLeft,
  Sigma,
  Play,
  Pause,
  RotateCcw,
  X,
  Moon,
  Sun,
} from "lucide-vue-next";
import { route } from "ziggy-js";
import Dropdown from "@/components/Dropdown.vue";
import DropdownLink from "@/components/DropdownLink.vue";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { ScrollArea } from "@/components/ui/scroll-area";
import { useTheme } from "@/composables/useTheme";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import type {
  FormulaCategory,
  Formula,
  FormulaStep,
  FormulaStepsResponse,
} from "@/types/formula";

const appOrigin =
  typeof window !== "undefined"
    ? window.location.origin.replace(/\/$/, "")
    : "http://127.0.0.1:8000";

MathfieldElement.fontsDirectory = `${appOrigin}/fonts/mathlive`;
MathfieldElement.soundsDirectory = `${appOrigin}/sounds/mathlive`;

type ApiFormula = {
  id: number;
  name: string;
  category: string;
  formula: string;
  description?: string | null;
  example?: string | null;
};

const CATEGORY_META = [
  {
    id: "arithmetic",
    label: "Թվաբանություն",
    emoji: "🔢",
    color: "blue",
    sections: [
      { id: "basic-ops", title: "Հիմնական գործողություններ" },
      { id: "fractions", title: "Կոտորակներ" },
      { id: "percentages", title: "Տոկոսներ" },
      { id: "powers-roots", title: "Աստիճաններ և արմատներ" },
      { id: "divisibility", title: "Բաժանելիություն" },
    ],
  },
  {
    id: "algebra",
    label: "Հանրահաշիվ",
    emoji: "🧩",
    color: "violet",
    sections: [
      { id: "identities", title: "Հանրահաշվական նույնություններ" },
      { id: "equations", title: "Հավասարումներ" },
      { id: "inequalities", title: "Անհավասարություններ" },
      { id: "sequences", title: "Հաջորդականություններ" },
      { id: "logarithms", title: "Լոգարիթմներ" },
      { id: "binomial", title: "Նյուտոնի բինոմ" },
    ],
  },
  {
    id: "geometry2d",
    label: "Հարթ երկրաչափություն",
    emoji: "📐",
    color: "emerald",
    sections: [
      { id: "triangle", title: "Եռանկյուն" },
      { id: "circle", title: "Շրջան և աղեղներ" },
      { id: "quadrilaterals", title: "Քառանկյուններ" },
      { id: "polygon", title: "Բազմանկյուններ" },
      { id: "coordinate", title: "Կոորդինատային երկրաչափություն" },
    ],
  },
  {
    id: "geometry3d",
    label: "Տարածական երկրաչափություն",
    emoji: "🧊",
    color: "cyan",
    sections: [
      { id: "prisms", title: "Պրիզմա, ուղղանկյուն զուգահեռանիստ, խորանարդ" },
      { id: "cylinders-cones", title: "Գլաններ և կոներ" },
      { id: "sphere", title: "Գունդ" },
      { id: "pyramids", title: "Բուրգեր" },
    ],
  },
  {
    id: "trigonometry",
    label: "Եռանկյունաչափություն",
    emoji: "📏",
    color: "amber",
    sections: [
      { id: "trig-defs", title: "Տրիգոնոմետրիկ սահմանումներ" },
      { id: "trig-values", title: "Հիմնական անկյունների արժեքներ" },
      { id: "trig-identities", title: "Տրիգոնոմետրիկ նույնություններ" },
      { id: "inverse-trig", title: "Հակադարձ տրիգոնոմետրիկ ֆունկցիաներ" },
    ],
  },
  {
    id: "calculus",
    label: "Մաթեմատիկական անալիզ",
    emoji: "∫",
    color: "rose",
    sections: [
      { id: "limits", title: "Սահմաններ" },
      { id: "derivatives", title: "Ածանցյալներ" },
      { id: "integrals", title: "Ինտեգրալներ" },
      { id: "series", title: "Շարքեր" },
      { id: "differential-eqs", title: "Դիֆերենցիալ հավասարումներ" },
    ],
  },
  {
    id: "linearAlgebra",
    label: "Գծային հանրահաշիվ",
    emoji: "🧮",
    color: "indigo",
    sections: [
      { id: "vectors", title: "Վեկտորներ" },
      { id: "matrices", title: "Մատրիցներ" },
      { id: "transformations", title: "Երկրաչափական ձևափոխումներ" },
    ],
  },
  {
    id: "statistics",
    label: "Վիճակագրություն և հավանականություն",
    emoji: "📊",
    color: "teal",
    sections: [
      { id: "combinatorics", title: "Կոմբինատորիկա" },
      { id: "probability", title: "Հավանականություն" },
      { id: "distributions", title: "Բաշխումներ" },
      { id: "stats-measures", title: "Վիճակագրական չափումներ" },
    ],
  },
];

const COLOR_MAP: Record<
  string,
  { bg: string; text: string; border: string; badge: string }
> = {
  blue: {
    bg: "bg-blue-50 dark:bg-blue-950/30",
    text: "text-blue-700 dark:text-blue-300",
    border: "border-blue-200 dark:border-blue-800",
    badge: "bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300",
  },
  violet: {
    bg: "bg-violet-50 dark:bg-violet-950/30",
    text: "text-violet-700 dark:text-violet-300",
    border: "border-violet-200 dark:border-violet-800",
    badge:
      "bg-violet-100 text-violet-700 dark:bg-violet-900 dark:text-violet-300",
  },
  emerald: {
    bg: "bg-emerald-50 dark:bg-emerald-950/30",
    text: "text-emerald-700 dark:text-emerald-300",
    border: "border-emerald-200 dark:border-emerald-800",
    badge:
      "bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300",
  },
  cyan: {
    bg: "bg-cyan-50 dark:bg-cyan-950/30",
    text: "text-cyan-700 dark:text-cyan-300",
    border: "border-cyan-200 dark:border-cyan-800",
    badge: "bg-cyan-100 text-cyan-700 dark:bg-cyan-900 dark:text-cyan-300",
  },
  amber: {
    bg: "bg-amber-50 dark:bg-amber-950/30",
    text: "text-amber-700 dark:text-amber-300",
    border: "border-amber-200 dark:border-amber-800",
    badge: "bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300",
  },
  rose: {
    bg: "bg-rose-50 dark:bg-rose-950/30",
    text: "text-rose-700 dark:text-rose-300",
    border: "border-rose-200 dark:border-rose-800",
    badge: "bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300",
  },
  indigo: {
    bg: "bg-indigo-50 dark:bg-indigo-950/30",
    text: "text-indigo-700 dark:text-indigo-300",
    border: "border-indigo-200 dark:border-indigo-800",
    badge:
      "bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300",
  },
  teal: {
    bg: "bg-teal-50 dark:bg-teal-950/30",
    text: "text-teal-700 dark:text-teal-300",
    border: "border-teal-200 dark:border-teal-800",
    badge: "bg-teal-100 text-teal-700 dark:bg-teal-900 dark:text-teal-300",
  },
};

const formulas = ref<ApiFormula[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

const search = ref("");
const activeId = ref(CATEGORY_META[0]?.id ?? "arithmetic");
const sidebarOpen = ref(false);
const page = usePage();
const user = computed(() => (page.props as any).auth?.user);
const { theme, setTheme } = useTheme();
const initials = computed(() => {
  const parts = (user.value?.name ?? "Օ").trim().split(/\s+/);
  return parts.length === 1
    ? parts[0][0].toUpperCase()
    : `${parts[0][0]}${parts[1][0]}`.toUpperCase();
});

const formulasBySection = computed<Record<string, Formula[]>>(() => {
  const map: Record<string, Formula[]> = {};
  for (const row of formulas.value) {
    const [categoryId, sectionId] = (row.category ?? "").split(":");
    if (!categoryId || !sectionId) continue;
    const key = `${categoryId}:${sectionId}`;
    if (!map[key]) map[key] = [];
    map[key].push({
      id: String(row.id),
      name: row.name,
      latex: row.formula,
      description: row.description ?? undefined,
      example: row.example ?? undefined,
    });
  }
  return map;
});

const allCategories = computed<FormulaCategory[]>(() =>
  CATEGORY_META.map((cat) => ({
    id: cat.id,
    label: cat.label,
    emoji: cat.emoji,
    color: cat.color,
    sections: cat.sections.map((sec) => ({
      id: sec.id,
      title: sec.title,
      formulas: formulasBySection.value[`${cat.id}:${sec.id}`] ?? [],
    })),
  })),
);

const activeCategory = computed(
  () =>
    allCategories.value.find((c) => c.id === activeId.value) ??
    allCategories.value[0],
);

const filteredSections = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return activeCategory.value.sections;
  return activeCategory.value.sections
    .map((sec) => ({
      ...sec,
      formulas: sec.formulas.filter(
        (f) =>
          f.name.toLowerCase().includes(q) || f.latex.toLowerCase().includes(q),
      ),
    }))
    .filter((sec) => sec.formulas.length > 0);
});

const totalFormulaCount = computed(() => formulas.value.length);

const stepsDialogOpen = ref(false);
const stepsLoading = ref(false);
const stepsError = ref<string | null>(null);
const stepsPlaying = ref(false);
const stepsRegenerating = ref(false);

const selectedFormula = ref<Formula | null>(null);
const selectedSteps = ref<FormulaStep[]>([]);
const activeStepIndex = ref(0);

const activeStep = computed(
  () => selectedSteps.value[activeStepIndex.value] ?? null,
);
const stepProgressPercent = computed(() => {
  if (!selectedSteps.value.length) {
    return 0;
  }

  return ((activeStepIndex.value + 1) / selectedSteps.value.length) * 100;
});

let stepsAnimationInterval: ReturnType<typeof setInterval> | null = null;

function clearStepAnimationInterval() {
  if (stepsAnimationInterval) {
    clearInterval(stepsAnimationInterval);
    stepsAnimationInterval = null;
  }
}

function pauseSelectedFormulaSteps() {
  clearStepAnimationInterval();
  stepsPlaying.value = false;
}

function playSelectedFormulaSteps(fromStart = false) {
  if (!selectedSteps.value.length) {
    return;
  }

  clearStepAnimationInterval();
  if (fromStart) {
    activeStepIndex.value = 0;
  } else if (activeStepIndex.value >= selectedSteps.value.length - 1) {
    activeStepIndex.value = 0;
  }
  stepsPlaying.value = true;

  stepsAnimationInterval = setInterval(() => {
    if (activeStepIndex.value < selectedSteps.value.length - 1) {
      activeStepIndex.value += 1;
      return;
    }

    pauseSelectedFormulaSteps();
  }, 2500);
}

function replaySelectedFormulaSteps() {
  playSelectedFormulaSteps(true);
}

function onStepsDialogToggle(open: boolean) {
  stepsDialogOpen.value = open;

  if (!open) {
    clearStepAnimationInterval();
    stepsPlaying.value = false;
    stepsRegenerating.value = false;
    activeStepIndex.value = 0;
  }
}

function normalizeStepsPayload(payload: FormulaStepsResponse | null | undefined): FormulaStep[] {
  const steps = Array.isArray(payload?.steps) ? payload.steps : [];

  return steps
    .map((step: any) => {
      const rawLatex =
        typeof step?.latex === "string"
          ? step.latex
          : typeof step?.math === "string"
            ? step.math
            : typeof step?.equation === "string"
              ? step.equation
              : "";
      const rawNote =
        typeof step?.note === "string"
          ? step.note
          : typeof step?.desc === "string"
            ? step.desc
            : typeof step?.explanation === "string"
              ? step.explanation
              : typeof step?.description === "string"
                ? step.description
                : typeof step?.text === "string"
                  ? step.text
                  : undefined;

      return {
        latex: normalizeInlineLatex(rawLatex),
        note: rawNote,
      } satisfies FormulaStep;
    })
    .filter((step) => step.latex.trim() !== "");
}

async function openFormulaStepsDialog(formula: Formula) {
  stepsDialogOpen.value = true;
  selectedFormula.value = formula;
  selectedSteps.value = [];
  activeStepIndex.value = 0;
  stepsError.value = null;
  stepsLoading.value = true;
  stepsRegenerating.value = false;
  stepsPlaying.value = false;

  try {
    const locale = (document?.documentElement?.lang ?? "en").slice(0, 2);
    const res = await fetch(
      `/api/formulas/${formula.id}/steps?locale=${encodeURIComponent(locale)}`,
    );
    const payload = (await res.json()) as FormulaStepsResponse & { message?: string };
    if (!res.ok) {
      throw new Error(payload?.message ?? "Չհաջողվեց բեռնել քայլերը");
    }

    selectedSteps.value = normalizeStepsPayload(payload);

    activeStepIndex.value = 0;
    await nextTick();
    renderAll();

    if (selectedSteps.value.length > 0) {
      replaySelectedFormulaSteps();
    }
  } catch (err) {
    selectedSteps.value = [];
    stepsError.value =
      err instanceof Error ? err.message : "Քայլերի բեռնումը ձախողվեց";
  } finally {
    stepsLoading.value = false;
  }
}

async function regenerateSelectedFormulaSteps() {
  if (!selectedFormula.value || stepsRegenerating.value) {
    return;
  }

  stepsError.value = null;
  stepsRegenerating.value = true;
  stepsPlaying.value = false;
  clearStepAnimationInterval();

  try {
    const res = await fetch(`/api/formulas/${selectedFormula.value.id}/steps/regenerate`, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        locale: (document?.documentElement?.lang ?? "en").slice(0, 2),
      }),
    });

    const payload = (await res.json()) as FormulaStepsResponse & { message?: string };
    if (!res.ok) {
      throw new Error(payload?.message ?? "Չհաջողվեց վերագեներացնել քայլերը");
    }

    selectedSteps.value = normalizeStepsPayload(payload);
    if (!selectedSteps.value.length) {
      throw new Error("AI-ը վավեր քայլեր չվերադարձրեց");
    }

    activeStepIndex.value = 0;
    await nextTick();
    renderAll();
    replaySelectedFormulaSteps();
  } catch (err) {
    stepsError.value =
      err instanceof Error ? err.message : "Քայլերի վերագեներացումը ձախողվեց";
  } finally {
    stepsRegenerating.value = false;
  }
}

function normalizeInlineLatex(value: string): string {
  let normalized = value.trim();
  if (!normalized) {
    return "";
  }

  if (
    normalized.startsWith("$$") &&
    normalized.endsWith("$$") &&
    normalized.length > 4
  ) {
    normalized = normalized.slice(2, -2).trim();
  } else if (
    normalized.startsWith("$") &&
    normalized.endsWith("$") &&
    normalized.length > 2
  ) {
    normalized = normalized.slice(1, -1).trim();
  } else if (
    normalized.startsWith("\\(") &&
    normalized.endsWith("\\)") &&
    normalized.length > 4
  ) {
    normalized = normalized.slice(2, -2).trim();
  } else if (
    normalized.startsWith("\\[") &&
    normalized.endsWith("\\]") &&
    normalized.length > 4
  ) {
    normalized = normalized.slice(2, -2).trim();
  }

  return normalized;
}

function renderTextFallback(el: HTMLElement, value: string) {
  el.innerHTML = "";
  const fallback = document.createElement("span");
  fallback.textContent = value;
  fallback.className = "font-mono text-xs leading-relaxed";
  el.appendChild(fallback);
}

function setMathFieldLatex(field: any, latex: string): boolean {
  const normalized = normalizeInlineLatex(latex);
  if (!normalized) {
    return false;
  }

  try {
    if (typeof field.setValue === "function") {
      field.setValue(normalized);
    } else {
      field.value = normalized;
    }
    return true;
  } catch {
    return false;
  }
}

function injectMath(el: HTMLElement, latex: string) {
  const normalizedLatex = normalizeInlineLatex(latex);
  if (!normalizedLatex) {
    el.innerHTML = "";
    delete el.dataset.renderedLatex;
    return;
  }

  if (el.dataset.renderedLatex === normalizedLatex && el.firstElementChild) {
    return;
  }

  let mf = el.querySelector("math-field") as any | null;
  if (!mf) {
    el.innerHTML = "";
    mf = document.createElement("math-field") as any;
    mf.setAttribute("read-only", "");
    mf.style.cssText =
      "background:transparent;border:none;pointer-events:none;display:block;width:100%;color:currentColor;";
    el.appendChild(mf);
  }

  requestAnimationFrame(() => {
    const ok = setMathFieldLatex(mf, normalizedLatex);
    if (!ok) {
      renderTextFallback(el, normalizedLatex);
      el.dataset.renderedLatex = normalizedLatex;
      return;
    }

    el.dataset.renderedLatex = normalizedLatex;
  });
}

function renderAll() {
  document.querySelectorAll<HTMLElement>("[data-latex]").forEach((el) => {
    if (el.dataset.latex) {
      injectMath(el, el.dataset.latex);
    }
  });
}

async function fetchFormulas() {
  loading.value = true;
  error.value = null;
  try {
    const res = await fetch("/api/formulas");
    if (!res.ok) {
      throw new Error("Չհաջողվեց բեռնել բանաձևերը");
    }
    formulas.value = await res.json();
  } catch (err) {
    error.value =
      err instanceof Error ? err.message : "Տվյալների բեռնումը ձախողվեց";
  } finally {
    loading.value = false;
    nextTick(renderAll);
  }
}

onMounted(() => fetchFormulas());
onUpdated(() => nextTick(renderAll));
onBeforeUnmount(() => clearStepAnimationInterval());

watch(
  () => [stepsDialogOpen.value, stepsLoading.value, activeStepIndex.value, selectedSteps.value.length],
  async () => {
    if (!stepsDialogOpen.value) {
      return;
    }

    await nextTick();
    renderAll();
    setTimeout(() => renderAll(), 40);
  },
);

function onStepTransitionAfterEnter() {
  nextTick(() => {
    renderAll();
    setTimeout(() => renderAll(), 40);
  });
}

function selectCategory(id: string) {
  activeId.value = id;
  search.value = "";
  sidebarOpen.value = false;
  nextTick(renderAll);
}
</script>

<template>
  <Head title="Մաթեմատիկական բանաձևերի գրադարան" />

  <div class="h-screen flex flex-col overflow-hidden bg-background">
    <header class="sticky top-0 z-50 border-b bg-background/95 backdrop-blur">
      <div class="flex items-center gap-3 px-4 py-3">
        <button
          class="lg:hidden p-2 rounded-md hover:bg-muted"
          @click="sidebarOpen = !sidebarOpen"
        >
          <svg
            class="w-5 h-5"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
          >
            <path d="M3 12h18M3 6h18M3 18h18" stroke-linecap="round" />
          </svg>
        </button>

        <Link
          :href="route('mathematics.index')"
          class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-xs font-medium hover:bg-muted transition"
        >
          <ArrowLeft :size="14" />
          <span class="hidden sm:inline">Վերադառնալ</span>
        </Link>

        <div class="flex items-center gap-2 min-w-0">
          <span class="text-xl">🧮</span>
          <h1 class="font-semibold text-base whitespace-nowrap">Formula Library</h1>
          <Badge variant="secondary" class="hidden sm:flex text-xs">
            {{ totalFormulaCount }} բանաձև
          </Badge>
        </div>

        <div class="flex-1 max-w-sm ml-auto">
          <Input
            v-model="search"
            placeholder="Որոնել բանաձև..."
            class="h-8 text-sm"
          />
        </div>

        <Dropdown
          align="right"
          width="48"
          content-classes="py-1 bg-white dark:bg-slate-800"
        >
          <template #trigger>
            <button
              type="button"
              class="flex h-9 w-9 items-center justify-center rounded-full border bg-gradient-to-br from-indigo-500 to-violet-600 text-xs font-semibold text-white shadow-sm"
            >
              <img
                v-if="user?.profile_photo_url"
                :src="user.profile_photo_url"
                class="h-full w-full object-cover rounded-full"
              />
              <span v-else>{{ initials }}</span>
            </button>
          </template>
          <template #content>
            <div class="px-4 py-2">
              <p class="text-sm font-semibold">{{ user?.name ?? "Օգտատեր" }}</p>
              <p class="text-xs text-muted-foreground">
                {{ user?.email ?? "" }}
              </p>
            </div>
            <DropdownLink :href="route('profile.edit')">Պրոֆիլ</DropdownLink>

            <div class="px-4 py-2">
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-300">
                Թեմա
              </p>
              <div class="grid grid-cols-2 gap-2">
                <button
                  type="button"
                  class="inline-flex items-center justify-center gap-1 rounded-md border px-2 py-1.5 text-xs font-medium transition"
                  :class="
                    theme === 'light'
                      ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-400 dark:bg-indigo-500/20 dark:text-indigo-200'
                      : 'border-slate-300 text-slate-600 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700'
                  "
                  @click.stop="setTheme('light')"
                >
                  <Sun :size="14" />
                  Լույս
                </button>
                <button
                  type="button"
                  class="inline-flex items-center justify-center gap-1 rounded-md border px-2 py-1.5 text-xs font-medium transition"
                  :class="
                    theme === 'dark'
                      ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-400 dark:bg-indigo-500/20 dark:text-indigo-200'
                      : 'border-slate-300 text-slate-600 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700'
                  "
                  @click.stop="setTheme('dark')"
                >
                  <Moon :size="14" />
                  Մութ
                </button>
              </div>
            </div>

            <DropdownLink :href="route('logout')" method="post" as="button">
              Դուրս գալ
            </DropdownLink>
          </template>
        </Dropdown>
      </div>
    </header>

    <div class="flex min-h-0 flex-1 overflow-hidden">
      <aside
        class="shrink-0 border-r bg-background transition-transform duration-200 lg:translate-x-0 lg:sticky lg:top-0 lg:h-full lg:self-start lg:w-56"
        :class="
          sidebarOpen
            ? 'fixed inset-y-0 left-0 z-40 w-64 translate-x-0 pt-14 lg:pt-0'
            : 'fixed -translate-x-full lg:translate-x-0'
        "
      >
        <div
          v-if="sidebarOpen"
          class="lg:hidden fixed inset-0 bg-black/30 z-[-1]"
          @click="sidebarOpen = false"
        />
        <ScrollArea class="h-full py-3">
          <nav class="px-2 space-y-0.5">
            <button
              v-for="cat in allCategories"
              :key="cat.id"
              class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-colors text-left"
              :class="
                activeId === cat.id
                  ? `${COLOR_MAP[cat.color]?.bg} ${COLOR_MAP[cat.color]?.text} font-medium`
                  : 'text-muted-foreground hover:text-foreground hover:bg-muted'
              "
              @click="selectCategory(cat.id)"
            >
              <span class="text-base leading-none">{{ cat.emoji }}</span>
              <span class="flex-1 truncate">{{ cat.label }}</span>
              <span class="text-[10px] opacity-60">
                {{ cat.sections.reduce((s, sec) => s + sec.formulas.length, 0) }}
              </span>
            </button>
          </nav>
        </ScrollArea>
      </aside>

      <main class="min-h-0 flex-1 overflow-y-auto">
        <div class="max-w-6xl mx-auto px-4 py-6 md:px-6 md:py-8">
          <div
            class="mb-8 rounded-2xl border border-slate-200 bg-gradient-to-br from-white via-slate-50 to-indigo-50 p-6 shadow-sm dark:border-slate-800 dark:from-slate-900 dark:via-slate-950 dark:to-indigo-950/40"
          >
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-500/30 bg-indigo-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-wider text-indigo-500 dark:text-indigo-300">
              <Sigma :size="14" />
              Knowledge Base
            </div>
            <h2 class="mt-4 text-2xl font-bold text-slate-900 dark:text-white md:text-3xl">
              {{ activeCategory.label }}
            </h2>
            <p class="mt-2 max-w-3xl text-sm text-slate-600 dark:text-slate-300">
              Սեղմիր ցանկացած քարտի վրա և տես քայլ առ քայլ բացատրությունը անիմացիայով։
            </p>
          </div>

          <div
            v-if="loading"
            class="rounded-lg border border-dashed px-4 py-3 text-sm text-muted-foreground"
          >
            Տվյալները բեռնվում են...
          </div>
          <div
            v-else-if="error"
            class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200"
          >
            {{ error }}
          </div>

          <div
            v-if="search && filteredSections.length === 0"
            class="text-center py-16 text-muted-foreground"
          >
            <span class="text-4xl block mb-3">🔍</span>
            <p>«{{ search }}» հարցմամբ բանաձև չգտնվեց</p>
          </div>

          <div
            v-for="section in filteredSections"
            :key="section.id"
            class="mb-10 space-y-4"
          >
            <div class="flex items-center gap-3">
              <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">
                {{ section.title }}
              </h3>
              <Separator class="flex-1" />
              <span class="text-xs text-muted-foreground">{{ section.formulas.length }}</span>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-2">
              <div
                v-for="formula in section.formulas"
                :key="formula.id"
                role="button"
                tabindex="0"
                class="group relative cursor-pointer overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg dark:border-slate-800 dark:bg-slate-900/80"
                @click="openFormulaStepsDialog(formula)"
                @keydown.enter.prevent="openFormulaStepsDialog(formula)"
                @keydown.space.prevent="openFormulaStepsDialog(formula)"
              >
                <div
                  class="pointer-events-none absolute -right-16 -top-16 h-36 w-36 rounded-full bg-gradient-to-br from-indigo-500/30 to-cyan-500/20 blur-3xl opacity-0 transition-opacity duration-500 group-hover:opacity-100"
                />

                <div class="relative z-10 mb-4 flex items-start justify-between gap-4">
                  <span class="rounded-md border border-slate-200 bg-slate-100 px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                    {{ section.title }}
                  </span>
                  <div class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 transition-colors group-hover:text-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:group-hover:text-indigo-300">
                    <Play :size="14" class="translate-x-[1px]" />
                  </div>
                </div>

                <h4 class="relative z-10 text-lg font-bold text-slate-900 dark:text-white">
                  {{ formula.name }}
                </h4>

                <p
                  v-if="formula.description"
                  class="relative z-10 mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300"
                >
                  {{ formula.description }}
                </p>

                <div class="relative z-10 mt-5 rounded-xl border border-slate-200 bg-slate-100 p-3 text-center shadow-inner dark:border-slate-700 dark:bg-slate-950">
                  <div
                    :data-latex="formula.latex"
                    class="min-h-[34px] flex items-center justify-center overflow-x-auto text-emerald-700 dark:text-emerald-300"
                  />
                </div>

                <div class="relative z-10 mt-4 border-t border-dashed border-slate-200 pt-3 dark:border-slate-700">
                  <span class="text-[11px] font-medium text-indigo-600 dark:text-indigo-300">
                    Բացել անիմացված լուծում
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <Dialog :open="stepsDialogOpen" @update:open="onStepsDialogToggle">
      <DialogContent class="formula-steps-dialog sm:max-w-5xl max-h-[90vh] p-0 overflow-hidden border-slate-200 bg-white text-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
        <div class="max-h-[90vh] flex flex-col bg-white text-slate-900 dark:bg-slate-950 dark:text-white">
          <DialogHeader class="sr-only">
            <DialogTitle>{{ selectedFormula?.name ?? "Formula" }}</DialogTitle>
            <DialogDescription>
              {{ selectedFormula?.description ?? "Քայլերով բացատրություն" }}
            </DialogDescription>
          </DialogHeader>

          <div class="flex items-start justify-between border-b border-slate-200 p-5 dark:border-slate-800">
            <div>
              <span class="inline-flex items-center rounded-md border border-indigo-500/30 bg-indigo-500/10 px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-indigo-500 dark:text-indigo-300">
                Քայլերով բացատրություն
              </span>
              <h3 class="mt-2 text-xl font-bold md:text-2xl">
                {{ selectedFormula?.name ?? "Բանաձև" }}
              </h3>
              <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                {{ selectedFormula?.description ?? "Տես քայլ առ քայլ լուծման անիմացիան" }}
              </p>
            </div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
                :disabled="stepsLoading || stepsRegenerating || !selectedFormula"
                @click="regenerateSelectedFormulaSteps"
              >
                <RotateCcw :size="14" />
                {{ stepsRegenerating ? "Generating..." : "Regenerate" }}
              </button>
              <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
                @click="onStepsDialogToggle(false)"
              >
                <X :size="16" />
              </button>
            </div>
          </div>

          <div class="custom-scrollbar overflow-y-auto p-5 md:p-6 space-y-5">
            <div
              v-if="selectedFormula"
              class="rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 dark:border-slate-700 dark:bg-[#0A122B]"
            >
              <div
                :data-latex="selectedFormula.latex"
                class="min-h-[34px] flex items-center justify-center overflow-x-auto text-emerald-700 dark:text-emerald-300"
              />
            </div>

            <div
              v-if="stepsLoading"
              class="rounded-md border border-dashed p-4 text-sm text-muted-foreground"
            >
              Քայլերը բեռնվում են...
            </div>

            <div
              v-else-if="stepsError"
              class="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200"
            >
              {{ stepsError }}
            </div>

            <div
              v-else-if="selectedSteps.length === 0"
              class="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200"
            >
              Քայլերը դեռ չեն գեներացվել։ Սեղմիր Regenerate կոճակը։
            </div>

            <div
              v-else
              class="grid gap-5 lg:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]"
            >
              <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-[#0B0F19]">
                <div class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-900/70">
                  <p class="text-sm font-semibold">Live Example</p>
                  <div class="flex items-center gap-2">
                    <button
                      type="button"
                      class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 transition hover:text-indigo-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:text-indigo-300"
                      :title="stepsPlaying ? 'Pause' : 'Play'"
                      @click="stepsPlaying ? pauseSelectedFormulaSteps() : playSelectedFormulaSteps()"
                    >
                      <Pause v-if="stepsPlaying" :size="14" />
                      <Play v-else :size="14" class="translate-x-[1px]" />
                    </button>
                    <button
                      type="button"
                      class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 transition hover:text-indigo-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:text-indigo-300"
                      title="Replay"
                      @click="replaySelectedFormulaSteps"
                    >
                      <RotateCcw :size="14" />
                    </button>
                  </div>
                </div>

                <div class="relative h-[260px] overflow-hidden px-5 py-8 md:h-[300px] md:px-8">
                  <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/10 via-transparent to-cyan-500/10" />
                  <div class="relative z-10 h-full">
                  <Transition
                    name="stepfade"
                    mode="out-in"
                    @after-enter="onStepTransitionAfterEnter"
                  >
                      <div
                        :key="`formula-step-main-${activeStepIndex}`"
                        class="absolute inset-0 flex flex-col items-center justify-center gap-6"
                      >
                        <div
                          :data-latex="activeStep?.latex ?? ''"
                          class="min-h-[70px] w-full flex items-center justify-center overflow-x-auto text-center text-2xl text-slate-900 dark:text-white"
                        />
                        <div class="inline-flex min-h-[38px] max-w-full items-center justify-center rounded-full border border-indigo-500/30 bg-indigo-500/10 px-4 py-1.5 text-center text-sm font-medium text-indigo-700 dark:text-indigo-200">
                          {{ activeStep?.note ?? `Քայլ ${activeStepIndex + 1}` }}
                        </div>
                      </div>
                    </Transition>
                  </div>
                </div>

                <div class="h-1.5 w-full bg-slate-200 dark:bg-slate-800">
                  <div
                    class="h-full bg-emerald-500 transition-all duration-500"
                    :style="{ width: `${stepProgressPercent}%` }"
                  />
                </div>
              </div>

              <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900/55">
                <p class="mb-2 px-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                  Steps
                </p>
                <div class="space-y-2 max-h-[460px] overflow-y-auto custom-scrollbar pr-1">
                  <div
                    v-for="(step, index) in selectedSteps"
                    :key="`formula-step-list-${index}`"
                    class="rounded-lg border p-3 transition-colors"
                    :class="
                      index === activeStepIndex
                        ? 'border-indigo-500 bg-indigo-100 dark:bg-indigo-900/35'
                        : index < activeStepIndex
                          ? 'border-emerald-500 bg-emerald-100 dark:bg-emerald-900/25'
                          : 'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/40'
                    "
                  >
                    <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                      Քայլ {{ index + 1 }}
                    </p>
                    <div
                      :data-latex="step.latex"
                      class="mt-1 min-h-[30px] flex items-center overflow-x-auto text-sm text-slate-900 dark:text-slate-100"
                    />
                    <p v-if="step.note" class="mt-1 text-xs text-slate-700 dark:text-slate-300">
                      {{ step.note }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  </div>
</template>

<style>
math-field[read-only] {
  --caret-color: transparent;
  --text-font-color: currentColor;
  pointer-events: none;
  background: transparent !important;
  border: none !important;
  outline: none !important;
  font-size: 1rem;
  color: currentColor !important;
}
math-field[read-only]::part(virtual-keyboard-toggle),
math-field[read-only]::part(menu-toggle) {
  display: none;
}

.stepfade-enter-active,
.stepfade-leave-active {
  transition:
    opacity 0.45s ease,
    transform 0.45s ease;
  will-change: opacity, transform;
}

.stepfade-enter-from,
.stepfade-leave-to {
  opacity: 0;
  transform: scale(0.985);
}

.custom-scrollbar::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: rgba(148, 163, 184, 0.12);
  border-radius: 9999px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(99, 102, 241, 0.45);
  border-radius: 9999px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(99, 102, 241, 0.7);
}

.formula-steps-dialog > button.absolute.right-4.top-4 {
  display: none !important;
}
</style>
