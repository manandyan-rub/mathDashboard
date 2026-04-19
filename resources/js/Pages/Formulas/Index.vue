<script setup lang="ts">
import "mathlive";
import { MathfieldElement } from "mathlive";
import { ref, computed, onMounted, onUpdated, nextTick } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import { ArrowLeft } from "lucide-vue-next";
import { route } from "ziggy-js";
import Dropdown from "@/components/Dropdown.vue";
import DropdownLink from "@/components/DropdownLink.vue";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { ScrollArea } from "@/components/ui/scroll-area";
import type { FormulaCategory, Formula } from "@/types/formula";

MathfieldElement.fontsDirectory = "/fonts/mathlive";
MathfieldElement.soundsDirectory = "/sounds/mathlive";

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
          f.name.toLowerCase().includes(q) ||
          f.latex.toLowerCase().includes(q),
      ),
    }))
    .filter((sec) => sec.formulas.length > 0);
});

const totalFormulaCount = computed(() => formulas.value.length);

function injectMath(el: HTMLElement, latex: string) {
  el.innerHTML = "";
  const mf = document.createElement("math-field") as any;
  mf.setAttribute("read-only", "");
  mf.style.cssText =
    "background:transparent;border:none;pointer-events:none;display:block;width:100%;color:inherit;";
  el.appendChild(mf);
  requestAnimationFrame(() => {
    if (typeof mf.setValue === "function") mf.setValue(latex);
    else mf.value = latex;
  });
}

function renderAll() {
  document.querySelectorAll<HTMLElement>("[data-latex]").forEach((el) => {
    if (el.dataset.latex && !el.querySelector("math-field")) {
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

function selectCategory(id: string) {
  activeId.value = id;
  search.value = "";
  sidebarOpen.value = false;
  nextTick(renderAll);
}
</script>

<template>
  <Head title="Մաթեմատիկական բանաձևերի գրադարան" />

  <div class="min-h-screen flex flex-col bg-background">
    <!-- Վերնագիր -->
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
          <h1 class="font-semibold text-base whitespace-nowrap">
            Մաթեմատիկական բանաձևերի գրադարան
          </h1>
          <Badge variant="secondary" class="hidden sm:flex text-xs"
            >{{ totalFormulaCount }} բանաձև</Badge
          >
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
            <DropdownLink :href="route('logout')" method="post" as="button"
              >Դուրս գալ</DropdownLink
            >
          </template>
        </Dropdown>
      </div>
    </header>

    <div class="flex flex-1 overflow-hidden">
      <!-- Ձախ բաժին -->
      <aside
        class="shrink-0 border-r bg-background transition-transform duration-200 lg:translate-x-0 lg:static lg:w-56"
        :class="
          sidebarOpen
            ? 'fixed inset-y-0 left-0 z-40 w-64 translate-x-0 pt-14'
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
              <span class="text-[10px] opacity-60">{{
                cat.sections.reduce((s, sec) => s + sec.formulas.length, 0)
              }}</span>
            </button>
          </nav>
        </ScrollArea>
      </aside>

      <!-- Հիմնական բովանդակություն -->
      <main class="flex-1 overflow-y-auto">
        <div class="max-w-4xl mx-auto px-4 py-6 space-y-8">
          <div class="flex items-center gap-3">
            <span class="text-4xl">{{ activeCategory.emoji }}</span>
            <div>
              <h2 class="text-2xl font-bold">
                {{ activeCategory.label }}
              </h2>
              <p class="text-sm text-muted-foreground">
                {{ activeCategory.sections.length }} բաժին,
                {{
                  activeCategory.sections.reduce(
                    (s, sec) => s + sec.formulas.length,
                    0,
                  )
                }}
                բանաձև
              </p>
            </div>
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
            class="space-y-4"
          >
            <div class="flex items-center gap-3">
              <h3 class="text-base font-semibold">{{ section.title }}</h3>
              <Separator class="flex-1" />
              <span class="text-xs text-muted-foreground">{{
                section.formulas.length
              }}</span>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
              <div
                v-for="formula in section.formulas"
                :key="formula.id"
                class="group rounded-xl border p-4 transition-all hover:shadow-md"
                :class="[
                  COLOR_MAP[activeCategory.color]?.border ?? 'border-border',
                  'hover:' +
                    (COLOR_MAP[activeCategory.color]?.bg ?? 'bg-muted/30'),
                ]"
              >
                <div class="flex items-start gap-2 mb-3">
                  <span
                    class="text-xs font-medium px-2 py-0.5 rounded-md"
                    :class="
                      COLOR_MAP[activeCategory.color]?.badge ??
                      'bg-muted text-foreground'
                    "
                  >
                    {{ formula.name }}
                  </span>
                </div>

                <div
                  :data-latex="formula.latex"
                  class="min-h-[36px] flex items-center overflow-x-auto"
                  :class="
                    COLOR_MAP[activeCategory.color]?.text ?? 'text-foreground'
                  "
                />

                <p
                  v-if="formula.description"
                  class="mt-2 text-xs text-muted-foreground leading-snug"
                >
                  {{ formula.description }}
                </p>

                <div
                  v-if="formula.example"
                  class="mt-3 pt-3 border-t border-dashed"
                >
                  <span
                    class="text-[10px] uppercase tracking-wider text-muted-foreground block mb-1"
                    >Օրինակ</span
                  >
                  <div
                    :data-latex="formula.example"
                    class="min-h-[28px] flex items-center overflow-x-auto text-sm text-muted-foreground"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
</template>

<style>
math-field[read-only] {
  --caret-color: transparent;
  pointer-events: none;
  background: transparent !important;
  border: none !important;
  outline: none !important;
  font-size: 1rem;
}
math-field[read-only]::part(virtual-keyboard-toggle),
math-field[read-only]::part(menu-toggle) {
  display: none;
}
</style>
