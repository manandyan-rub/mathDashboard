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
import OrganizationContext from "@/components/OrganizationContext.vue";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { ScrollArea } from "@/components/ui/scroll-area";
import { useTheme } from "@/composables/useTheme";
import {
  cmsTranslate as __,
  getLanguage,
  setLanguage,
  type LocaleCode,
} from "@/lib/i18n";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import type {
  FormulaCategory,
  FormulaApiRecord,
  FormulaGroupApiRecord,
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

const CATEGORY_META = [
  {
    id: "arithmetic",
    label: "Arithmetic",
    color: "blue",
    sections: [
      { id: "basic-ops", title: "Basic operations" },
      { id: "fractions", title: "Fractions" },
      { id: "percentages", title: "Percentages" },
      { id: "powers-roots", title: "Powers and roots" },
      { id: "divisibility", title: "Divisibility" },
    ],
  },
  {
    id: "algebra",
    label: "Algebra",
    color: "violet",
    sections: [
      { id: "identities", title: "Algebraic identities" },
      { id: "equations", title: "Equations" },
      { id: "inequalities", title: "Inequalities" },
      { id: "sequences", title: "Sequences" },
      { id: "logarithms", title: "Logarithms" },
      { id: "binomial", title: "Binomial theorem" },
    ],
  },
  {
    id: "geometry2d",
    label: "Plane Geometry",
    color: "emerald",
    sections: [
      { id: "triangle", title: "Triangles" },
      { id: "circle", title: "Circles and arcs" },
      { id: "quadrilaterals", title: "Quadrilaterals" },
      { id: "polygon", title: "Polygons" },
      { id: "coordinate", title: "Coordinate geometry" },
    ],
  },
  {
    id: "geometry3d",
    label: "Solid Geometry",
    color: "cyan",
    sections: [
      { id: "prisms", title: "Prisms and cuboids" },
      { id: "cylinders-cones", title: "Cylinders and cones" },
      { id: "sphere", title: "Spheres" },
      { id: "pyramids", title: "Pyramids" },
    ],
  },
  {
    id: "trigonometry",
    label: "Trigonometry",
    color: "amber",
    sections: [
      { id: "trig-defs", title: "Trigonometric definitions" },
      { id: "trig-values", title: "Special angle values" },
      { id: "trig-identities", title: "Trigonometric identities" },
      { id: "inverse-trig", title: "Inverse trigonometric functions" },
    ],
  },
  {
    id: "calculus",
    label: "Calculus",
    color: "rose",
    sections: [
      { id: "limits", title: "Limits" },
      { id: "derivatives", title: "Derivatives" },
      { id: "integrals", title: "Integrals" },
      { id: "series", title: "Series" },
      { id: "differential-eqs", title: "Differential equations" },
    ],
  },
  {
    id: "linearAlgebra",
    label: "Linear Algebra",
    color: "indigo",
    sections: [
      { id: "vectors", title: "Vectors" },
      { id: "matrices", title: "Matrices" },
      { id: "transformations", title: "Geometric transformations" },
    ],
  },
  {
    id: "statistics",
    label: "Statistics and Probability",
    color: "teal",
    sections: [
      { id: "combinatorics", title: "Combinatorics" },
      { id: "probability", title: "Probability" },
      { id: "distributions", title: "Distributions" },
      { id: "stats-measures", title: "Statistical measures" },
    ],
  },
];

const COLOR_MAP: Record<
  string,
  { bg: string; text: string; border: string; badge: string }
> = {
  blue: {
    bg: "bg-chart-5/10 dark:bg-chart-5/20",
    text: "text-chart-5 dark:text-chart-5",
    border: "border-chart-5 dark:border-chart-5",
    badge: "bg-chart-5/10 text-chart-5 dark:bg-chart-5/20 dark:text-chart-5",
  },
  violet: {
    bg: "bg-chart-4/10 dark:bg-chart-4/20",
    text: "text-chart-4 dark:text-chart-4",
    border: "border-chart-4 dark:border-chart-4",
    badge:
      "bg-chart-4/10 text-chart-4 dark:bg-chart-4/20 dark:text-chart-4",
  },
  emerald: {
    bg: "bg-chart-2/10 dark:bg-chart-2/20",
    text: "text-chart-2 dark:text-chart-2",
    border: "border-chart-2 dark:border-chart-2",
    badge:
      "bg-chart-2/10 text-chart-2 dark:bg-chart-2/20 dark:text-chart-2",
  },
  cyan: {
    bg: "bg-chart-5/10 dark:bg-chart-5/20",
    text: "text-chart-5 dark:text-chart-5",
    border: "border-chart-5 dark:border-chart-5",
    badge: "bg-chart-5/10 text-chart-5 dark:bg-chart-5/20 dark:text-chart-5",
  },
  amber: {
    bg: "bg-chart-3/10 dark:bg-chart-3/20",
    text: "text-chart-3 dark:text-chart-3",
    border: "border-chart-3 dark:border-chart-3",
    badge: "bg-chart-3/10 text-chart-3 dark:bg-chart-3/20 dark:text-chart-3",
  },
  rose: {
    bg: "bg-destructive/10 dark:bg-destructive/20",
    text: "text-destructive dark:text-destructive",
    border: "border-destructive dark:border-destructive",
    badge: "bg-destructive/10 text-destructive dark:bg-destructive/20 dark:text-destructive",
  },
  indigo: {
    bg: "bg-primary/10 dark:bg-primary/20",
    text: "text-primary dark:text-primary",
    border: "border-primary dark:border-primary",
    badge:
      "bg-accent text-accent-foreground dark:bg-primary/20 dark:text-primary",
  },
  teal: {
    bg: "bg-chart-2/10 dark:bg-chart-2/20",
    text: "text-chart-2 dark:text-chart-2",
    border: "border-chart-2 dark:border-chart-2",
    badge: "bg-chart-2/10 text-chart-2 dark:bg-chart-2/20 dark:text-chart-2",
  },
};

function categoryColor(color: string) {
  return COLOR_MAP[color] ?? COLOR_MAP.indigo;
}

const formulas = ref<FormulaApiRecord[]>([]);
const customGroups = ref<FormulaGroupApiRecord[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

const search = ref("");
const activeId = ref(CATEGORY_META[0]?.id ?? "arithmetic");
const sidebarOpen = ref(false);
const page = usePage();
const user = computed(() => (page.props as any).auth?.user);
const { theme, setTheme } = useTheme();
const language = ref<LocaleCode>(getLanguage());
const languages = [
  { value: "en", label: "English" },
  { value: "hy", label: "Armenian" },
  { value: "fr", label: "French" },
  { value: "ru", label: "Russian" },
] satisfies Array<{ value: LocaleCode; label: string }>;
const initials = computed(() => {
  const parts = (user.value?.name ?? "U").trim().split(/\s+/);
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

const categoryMeta = computed(() => [...CATEGORY_META, ...customGroups.value]);

const allCategories = computed<FormulaCategory[]>(() =>
  categoryMeta.value.map((cat) => ({
    id: cat.id,
    label: __(cat.label),
    color: cat.color,
    sections: cat.sections.map((sec) => ({
      id: sec.id,
      title: __(sec.title),
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
  stepsPlaying.value = false;

  try {
    const locale = (document?.documentElement?.lang ?? "en").slice(0, 2);
    const res = await fetch(
      `/api/formulas/${formula.id}/steps?locale=${encodeURIComponent(locale)}`,
    );
    const payload = (await res.json()) as FormulaStepsResponse & { message?: string };
    if (!res.ok) {
      throw new Error(
        payload?.message ? __(payload.message) : __("Failed to load steps."),
      );
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
      err instanceof Error ? err.message : __("Failed to load steps.");
  } finally {
    stepsLoading.value = false;
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

async function fetchFormulaGroups() {
  const res = await fetch("/api/formula-groups");
  if (!res.ok) {
    throw new Error("Could not load formula groups.");
  }

  customGroups.value = await res.json();
}

async function fetchFormulas() {
  loading.value = true;
  error.value = null;
  try {
    const [res] = await Promise.all([fetch("/api/formulas"), fetchFormulaGroups()]);
    if (!res.ok) {
      throw new Error(__("Failed to load formulas."));
    }
    formulas.value = await res.json();
  } catch (err) {
    error.value =
      err instanceof Error ? err.message : __("Failed to load data.");
  } finally {
    loading.value = false;
    nextTick(renderAll);
  }
}

async function changeLanguage(event: Event) {
  const value = (event.target as HTMLSelectElement).value as LocaleCode;
  if (value === language.value) return;

  pauseSelectedFormulaSteps();
  stepsDialogOpen.value = false;
  language.value = value;
  setLanguage(value);
  await fetchFormulas();
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
  <Head :title="__('Formula Library')" />

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
          <span class="hidden sm:inline">{{ __("Back") }}</span>
        </Link>

        <div class="flex items-center gap-2 min-w-0">
          <h1 class="font-semibold text-base whitespace-nowrap">
            {{ __("Formula Library") }}
          </h1>
          <Badge variant="secondary" class="hidden sm:flex text-xs">
            {{ __(":count formulas", { count: String(totalFormulaCount) }) }}
          </Badge>
        </div>

        <div class="flex-1 max-w-sm ml-auto">
          <Input
            v-model="search"
            :placeholder="__('Search formulas...')"
            class="h-8 text-sm"
          />
        </div>

        <select
          :value="language"
          class="h-9 rounded-lg border border-border bg-input px-3 py-2 text-sm font-medium text-foreground shadow-sm transition hover:border-ring focus:border-ring focus:outline-none"
          :aria-label="__('Language')"
          @change="changeLanguage"
        >
          <option
            v-for="lang in languages"
            :key="lang.value"
            :value="lang.value"
          >
            {{ __(lang.label) }}
          </option>
        </select>

        <Dropdown
          align="right"
          width="48"
          content-classes="py-1 bg-card dark:bg-card"
        >
          <template #trigger>
            <button
              type="button"
              class="flex h-9 w-9 items-center justify-center rounded-full border bg-gradient-to-br from-primary to-chart-4 text-xs font-semibold text-primary-foreground shadow-sm"
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
              <p class="text-sm font-semibold">{{ user?.name ?? __("User") }}</p>
              <p class="text-xs text-muted-foreground">
                {{ user?.email ?? "" }}
              </p>
            </div>
            <OrganizationContext class="border-y border-border px-4 py-3" />
            <DropdownLink :href="route('profile.edit')">{{ __("Profile") }}</DropdownLink>

            <div class="px-4 py-2">
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground dark:text-muted-foreground">
                {{ __("Theme") }}
              </p>
              <div class="grid grid-cols-2 gap-2">
                <button
                  type="button"
                  class="inline-flex items-center justify-center gap-1 rounded-md border px-2 py-1.5 text-xs font-medium transition"
                  :class="
                    theme === 'light'
                      ? 'border-primary/30 bg-accent text-accent-foreground dark:border-primary dark:bg-primary/20 dark:text-primary'
                      : 'border-border text-muted-foreground hover:bg-muted dark:border-border dark:text-muted-foreground dark:hover:bg-card'
                  "
                  @click.stop="setTheme('light')"
                >
                  <Sun :size="14" />
                  {{ __("Light") }}
                </button>
                <button
                  type="button"
                  class="inline-flex items-center justify-center gap-1 rounded-md border px-2 py-1.5 text-xs font-medium transition"
                  :class="
                    theme === 'dark'
                      ? 'border-primary/30 bg-accent text-accent-foreground dark:border-primary dark:bg-primary/20 dark:text-primary'
                      : 'border-border text-muted-foreground hover:bg-muted dark:border-border dark:text-muted-foreground dark:hover:bg-card'
                  "
                  @click.stop="setTheme('dark')"
                >
                  <Moon :size="14" />
                  {{ __("Dark") }}
                </button>
              </div>
            </div>

            <DropdownLink :href="route('logout')" method="post" as="button">
              {{ __("Log Out") }}
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
          class="lg:hidden fixed inset-0 bg-foreground/30 z-[-1]"
          @click="sidebarOpen = false"
        />
        <ScrollArea class="h-full py-3">
          <nav class="px-2 space-y-0.5">
            <button
              v-for="cat in allCategories"
              :key="cat.id"
              class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg border text-sm transition-colors text-left"
              :class="
                activeId === cat.id
                  ? `${categoryColor(cat.color).bg} ${categoryColor(cat.color).text} ${categoryColor(cat.color).border} font-semibold shadow-sm`
                  : 'border-transparent text-muted-foreground hover:border-border hover:text-foreground hover:bg-muted'
              "
              @click="selectCategory(cat.id)"
            >
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
            class="mb-8 rounded-2xl border border-border bg-gradient-to-br from-white via-muted to-primary p-6 shadow-sm dark:border-border dark:from-card dark:via-background dark:to-primary/40"
          >
            <div class="inline-flex items-center gap-2 rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-wider text-primary dark:text-primary">
              <Sigma :size="14" />
              {{ __("Knowledge Base") }}
            </div>
            <h2 class="mt-4 text-2xl font-bold text-foreground dark:text-primary-foreground md:text-3xl">
              {{ activeCategory.label }}
            </h2>
            <p class="mt-2 max-w-3xl text-sm text-muted-foreground dark:text-muted-foreground">
              {{ __("Browse formulas and open step-by-step animated explanations.") }}
            </p>
          </div>

          <div
            v-if="loading"
            class="rounded-lg border border-dashed px-4 py-3 text-sm text-muted-foreground"
          >
            {{ __("Loading formulas...") }}
          </div>
          <div
            v-else-if="error"
            class="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive dark:border-destructive/60 dark:bg-destructive/30 dark:text-destructive"
          >
            {{ error }}
          </div>

          <div
            v-if="search && filteredSections.length === 0"
            class="text-center py-16 text-muted-foreground"
          >
            <p>{{ __("No formulas found for :query.", { query: search }) }}</p>
          </div>

          <div
            v-for="section in filteredSections"
            :key="section.id"
            class="mb-10 space-y-4"
          >
            <div class="flex items-center gap-3">
              <h3 class="text-base font-semibold text-foreground dark:text-foreground">
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
                class="group relative cursor-pointer overflow-hidden rounded-2xl border border-border bg-card p-5 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg dark:border-border dark:bg-card/80"
                @click="openFormulaStepsDialog(formula)"
                @keydown.enter.prevent="openFormulaStepsDialog(formula)"
                @keydown.space.prevent="openFormulaStepsDialog(formula)"
              >
                <div
                  class="pointer-events-none absolute -right-16 -top-16 h-36 w-36 rounded-full bg-gradient-to-br from-primary/30 to-chart-5/20 blur-3xl opacity-0 transition-opacity duration-500 group-hover:opacity-100"
                />

                <div class="relative z-10 mb-4 flex items-start justify-between gap-4">
                  <span class="rounded-md border border-border bg-muted px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground dark:border-border dark:bg-card dark:text-muted-foreground">
                    {{ section.title }}
                  </span>
                  <div class="flex h-8 w-8 items-center justify-center rounded-full border border-border bg-card text-muted-foreground transition-colors group-hover:text-primary dark:border-border dark:bg-card dark:text-muted-foreground dark:group-hover:text-primary">
                    <Play :size="14" class="translate-x-[1px]" />
                  </div>
                </div>

                <h4 class="relative z-10 text-lg font-bold text-foreground dark:text-primary-foreground">
                  {{ formula.name }}
                </h4>

                <p
                  v-if="formula.description"
                  class="relative z-10 mt-2 text-sm leading-relaxed text-muted-foreground dark:text-muted-foreground"
                >
                  {{ formula.description }}
                </p>

                <div class="relative z-10 mt-5 rounded-xl border border-border bg-muted p-3 text-center shadow-inner dark:border-border dark:bg-background">
                  <div
                    :data-latex="formula.latex"
                    class="min-h-[34px] flex items-center justify-center overflow-x-auto text-chart-2 dark:text-chart-2"
                  />
                </div>

                <div class="relative z-10 mt-4 border-t border-dashed border-border pt-3 dark:border-border">
                  <span class="text-[11px] font-medium text-primary dark:text-primary">
                    {{ __("Open animated solution") }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <Dialog :open="stepsDialogOpen" @update:open="onStepsDialogToggle">
      <DialogContent class="formula-steps-dialog sm:max-w-5xl max-h-[90vh] p-0 overflow-hidden border-border bg-card text-foreground dark:border-border dark:bg-background dark:text-primary-foreground">
        <div class="max-h-[90vh] flex flex-col bg-card text-foreground dark:bg-background dark:text-primary-foreground">
          <DialogHeader class="sr-only">
            <DialogTitle>{{ selectedFormula?.name ?? "Formula" }}</DialogTitle>
            <DialogDescription>
              {{ selectedFormula?.description ?? __("Step-by-step explanation") }}
            </DialogDescription>
          </DialogHeader>

          <div class="flex items-start justify-between border-b border-border p-5 dark:border-border">
            <div>
              <span class="inline-flex items-center rounded-md border border-primary/30 bg-primary/10 px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-primary dark:text-primary">
                {{ __("Step-by-step explanation") }}
              </span>
              <h3 class="mt-2 text-xl font-bold md:text-2xl">
                {{ selectedFormula?.name ?? __("Formula") }}
              </h3>
              <p class="mt-1 text-sm text-muted-foreground dark:text-muted-foreground">
                {{ selectedFormula?.description ?? __("Watch the step-by-step animated solution.") }}
              </p>
            </div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-border bg-card text-muted-foreground transition hover:bg-muted hover:text-foreground dark:border-border dark:bg-card dark:text-muted-foreground dark:hover:bg-card dark:hover:text-primary-foreground"
                @click="onStepsDialogToggle(false)"
              >
                <X :size="16" />
              </button>
            </div>
          </div>

          <div class="custom-scrollbar overflow-y-auto p-5 md:p-6 space-y-5">
            <div
              v-if="selectedFormula"
              class="rounded-xl border border-border bg-muted px-4 py-3 dark:border-border dark:bg-card"
            >
              <div
                :data-latex="selectedFormula.latex"
                class="min-h-[34px] flex items-center justify-center overflow-x-auto text-chart-2 dark:text-chart-2"
              />
            </div>

            <div
              v-if="stepsLoading"
              class="rounded-md border border-dashed p-4 text-sm text-muted-foreground"
            >
              {{ __("Loading steps...") }}
            </div>

            <div
              v-else-if="stepsError"
              class="rounded-md border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive dark:border-destructive/60 dark:bg-destructive/30 dark:text-destructive"
            >
              {{ stepsError }}
            </div>

            <div
              v-else-if="selectedSteps.length === 0"
              class="rounded-md border border-chart-3/30 bg-chart-3/10 p-4 text-sm text-chart-3 dark:border-chart-3/60 dark:bg-chart-3/30 dark:text-chart-3"
            >
              {{ __("Steps have not been generated yet.") }}
            </div>

            <div
              v-else
              class="grid gap-5 lg:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]"
            >
              <div class="overflow-hidden rounded-xl border border-border bg-muted dark:border-border dark:bg-card">
                <div class="flex items-center justify-between border-b border-border bg-card px-4 py-3 dark:border-border dark:bg-card/70">
                  <p class="text-sm font-semibold">{{ __("Live Example") }}</p>
                  <div class="flex items-center gap-2">
                    <button
                      type="button"
                      class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-card text-muted-foreground transition hover:text-primary dark:border-border dark:bg-card dark:text-muted-foreground dark:hover:text-primary"
                      :title="stepsPlaying ? __('Pause') : __('Play')"
                      @click="stepsPlaying ? pauseSelectedFormulaSteps() : playSelectedFormulaSteps()"
                    >
                      <Pause v-if="stepsPlaying" :size="14" />
                      <Play v-else :size="14" class="translate-x-[1px]" />
                    </button>
                    <button
                      type="button"
                      class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-card text-muted-foreground transition hover:text-primary dark:border-border dark:bg-card dark:text-muted-foreground dark:hover:text-primary"
                      :title="__('Replay')"
                      @click="replaySelectedFormulaSteps"
                    >
                      <RotateCcw :size="14" />
                    </button>
                  </div>
                </div>

                <div class="relative h-[260px] overflow-hidden px-5 py-8 md:h-[300px] md:px-8">
                  <div class="absolute inset-0 bg-gradient-to-br from-primary/10 via-transparent to-chart-5/10" />
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
                          class="min-h-[70px] w-full flex items-center justify-center overflow-x-auto text-center text-2xl text-foreground dark:text-primary-foreground"
                        />
                        <div class="inline-flex min-h-[38px] max-w-full items-center justify-center rounded-full border border-primary/30 bg-primary/10 px-4 py-1.5 text-center text-sm font-medium text-primary dark:text-primary">
                          {{ activeStep?.note ?? __("Step :number", { number: String(activeStepIndex + 1) }) }}
                        </div>
                      </div>
                    </Transition>
                  </div>
                </div>

                <div class="h-1.5 w-full bg-muted dark:bg-card">
                  <div
                    class="h-full bg-chart-2 transition-all duration-500"
                    :style="{ width: `${stepProgressPercent}%` }"
                  />
                </div>
              </div>

              <div class="rounded-xl border border-border bg-card p-3 dark:border-border dark:bg-card/55">
                <p class="mb-2 px-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground dark:text-muted-foreground">
                  {{ __("Steps") }}
                </p>
                <div class="space-y-2 max-h-[460px] overflow-y-auto custom-scrollbar pr-1">
                  <div
                    v-for="(step, index) in selectedSteps"
                    :key="`formula-step-list-${index}`"
                    class="rounded-lg border p-3 text-foreground transition-colors"
                    :class="
                      index === activeStepIndex
                        ? 'border-primary/40 bg-primary/10 ring-1 ring-primary/20 dark:bg-primary/15'
                        : index < activeStepIndex
                          ? 'border-chart-2/40 bg-chart-2/10 dark:bg-chart-2/15'
                          : 'border-border bg-muted dark:border-border dark:bg-card/40'
                    "
                  >
                    <p
                      class="text-xs font-semibold"
                      :class="
                        index === activeStepIndex
                          ? 'text-primary'
                          : index < activeStepIndex
                            ? 'text-chart-2'
                            : 'text-muted-foreground'
                      "
                    >
                      {{ __("Step :number", { number: String(index + 1) }) }}
                    </p>
                    <div
                      :data-latex="step.latex"
                      class="mt-1 min-h-[30px] flex items-center overflow-x-auto text-sm text-foreground dark:text-foreground"
                    />
                    <p
                      v-if="step.note"
                      class="mt-1 text-xs leading-relaxed text-muted-foreground dark:text-muted-foreground"
                    >
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
  background: color-mix(in srgb, var(--muted-foreground) 12%, transparent);
  border-radius: 9999px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: color-mix(in srgb, var(--ring) 45%, transparent);
  border-radius: 9999px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: color-mix(in srgb, var(--ring) 70%, transparent);
}

.formula-steps-dialog > button.absolute.right-4.top-4 {
  display: none !important;
}
</style>
