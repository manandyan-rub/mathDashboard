<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import axios from "axios";
import { computed, nextTick, onBeforeUnmount, onMounted, onUpdated, ref } from "vue";
import {
  Check,
  ChevronLeft,
  ChevronRight,
  Flag,
  LoaderCircle,
  Zap,
} from "lucide-vue-next";
import "mathlive";
import { MathfieldElement } from "mathlive";

MathfieldElement.fontsDirectory = "/fonts/mathlive";
MathfieldElement.soundsDirectory = "/sounds/mathlive";

type OptionItem = { id: number; text: string };
type QuestionItem = {
  id: number;
  question: string;
  options: OptionItem[];
  selected_option_id?: number | null;
};

type Segment = {
  type: "text" | "math";
  value: string;
};

const props = defineProps<{
  attemptId: number;
}>();

const levelTitle = ref("");
const xpReward = ref(0);
const questions = ref<QuestionItem[]>([]);
const currentIndex = ref(0);
const loading = ref(true);
const error = ref<string | null>(null);
const saving = ref(false);
const finishing = ref(false);

const currentQuestion = computed(() => questions.value[currentIndex.value]);
const progressText = computed(
  () => `${currentIndex.value + 1} / ${questions.value.length}`,
);
const answeredCount = computed(
  () => questions.value.filter((question) => question.selected_option_id).length,
);
const allAnswered = computed(
  () => questions.value.length > 0 && answeredCount.value === questions.value.length,
);
const progressPercent = computed(() =>
  questions.value.length === 0
    ? 0
    : Math.round((answeredCount.value / questions.value.length) * 100),
);
const direction = ref<"next" | "prev">("next");
const lastPicked = ref<number | null>(null);

const optionLetter = (index: number) => String.fromCharCode(65 + index);

async function loadAttempt() {
  loading.value = true;
  error.value = null;
  try {
    const response = await axios.get(
      `/api/math-levels/attempts/${props.attemptId}`,
    );
    levelTitle.value = response.data?.level?.title ?? "Մակարդակ";
    xpReward.value = response.data?.level?.xp_reward ?? 0;
    questions.value = response.data?.questions ?? [];
    await nextTick();
    renderMath();
  } catch (err: any) {
    error.value =
      err?.response?.data?.message ||
      err?.message ||
      "Չհաջողվեց բեռնել մակարդակը։";
  } finally {
    loading.value = false;
  }
}

async function chooseOption(optionId: number) {
  const question = currentQuestion.value;
  if (!question) return;

  saving.value = true;
  try {
    await axios.post("/api/math-levels/answer", {
      attempt_id: props.attemptId,
      question_id: question.id,
      option_id: optionId,
    });
    question.selected_option_id = optionId;
  } catch (err: any) {
    error.value =
      err?.response?.data?.message ||
      err?.message ||
      "Չհաջողվեց պահպանել պատասխանը։";
  } finally {
    saving.value = false;
  }
}

function goNext() {
  if (currentIndex.value < questions.value.length - 1) {
    direction.value = "next";
    currentIndex.value += 1;
  }
}

function goPrev() {
  if (currentIndex.value > 0) {
    direction.value = "prev";
    currentIndex.value -= 1;
  }
}

function goTo(index: number) {
  if (index === currentIndex.value) return;
  direction.value = index > currentIndex.value ? "next" : "prev";
  currentIndex.value = index;
}

async function pick(optionId: number) {
  lastPicked.value = null;
  await nextTick();
  lastPicked.value = optionId;
  await chooseOption(optionId);
}

function onKeydown(event: KeyboardEvent) {
  const target = event.target as HTMLElement | null;
  if (
    !currentQuestion.value ||
    saving.value ||
    event.metaKey ||
    event.ctrlKey ||
    event.altKey ||
    target?.closest("input, textarea, select, [contenteditable], math-field")
  ) {
    return;
  }

  if (event.key === "ArrowRight") {
    goNext();
    return;
  }
  if (event.key === "ArrowLeft") {
    goPrev();
    return;
  }

  const options = currentQuestion.value.options;
  const key = event.key.toLowerCase();
  let index = -1;
  if (/^[1-9]$/.test(key)) index = Number(key) - 1;
  else if (/^[a-z]$/.test(key)) index = key.charCodeAt(0) - 97;

  if (index >= 0 && index < options.length) {
    event.preventDefault();
    pick(options[index].id);
  }
}

function finish() {
  finishing.value = true;
  error.value = null;

  router.post(
    "/api/math-levels/finish",
    {
      attempt_id: props.attemptId,
    },
    {
      preserveScroll: true,
      onError: (errors) => {
        error.value =
          String(Object.values(errors)[0] ?? "") ||
          "Failed to finish level.";
      },
      onFinish: () => {
        finishing.value = false;
      },
    },
  );
}

onMounted(loadAttempt);
onMounted(() => window.addEventListener("keydown", onKeydown));
onBeforeUnmount(() => window.removeEventListener("keydown", onKeydown));
onUpdated(() => nextTick(renderMath));

function splitMath(text: string): Segment[] {
  if (!text) return [];
  const segments: Segment[] = [];
  const regex =
    /\$\$([\s\S]+?)\$\$|\$([^$]+)\$|\\\((.+?)\\\)|\\begin\{cases\}[\s\S]+?\\end\{cases\}/g;
  let lastIndex = 0;
  let match: RegExpExecArray | null;

  while ((match = regex.exec(text)) !== null) {
    if (match.index > lastIndex) {
      segments.push({
        type: "text",
        value: text.slice(lastIndex, match.index),
      });
    }

    const latex =
      match[1] ?? match[2] ?? match[3] ?? (match[0] ?? "").trim();
    if (latex) {
      segments.push({ type: "math", value: latex });
    }

    lastIndex = match.index + match[0].length;
  }

  if (lastIndex < text.length) {
    segments.push({ type: "text", value: text.slice(lastIndex) });
  }

  return segments;
}

function injectMath(el: HTMLElement, latex: string) {
  el.innerHTML = "";
  const mf = document.createElement("math-field") as any;
  mf.setAttribute("read-only", "");
  mf.style.cssText =
    "background:transparent;border:none;pointer-events:none;display:inline-block;color:inherit;";
  el.appendChild(mf);
  requestAnimationFrame(() => {
    if (typeof mf.setValue === "function") mf.setValue(latex);
    else mf.value = latex;
  });
}

function renderMath() {
  document.querySelectorAll<HTMLElement>(".inline-math").forEach((el) => {
    if (el.dataset.latex && !el.querySelector("math-field")) {
      injectMath(el, el.dataset.latex);
    }
  });
}
</script>

<template>
  <Head title="Մաթեմատիկայի մակարդակ" />

  <div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
    <div
      v-reveal="'down'"
      class="aurora rounded-2xl border border-border/70 bg-card/70 p-6 shadow-sm backdrop-blur-sm"
    >
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 class="text-shine text-2xl font-extrabold tracking-tight">
            {{ levelTitle }}
          </h1>
          <p class="mt-1 inline-flex items-center gap-1 text-sm font-semibold text-primary">
            <Zap :size="14" /> XP {{ xpReward }}
          </p>
        </div>
        <div class="text-right">
          <p class="text-2xl font-bold tabular-nums text-foreground">
            {{ progressText }}
          </p>
          <p class="text-xs text-muted-foreground">
            {{ answeredCount }} / {{ questions.length }} ✓
          </p>
        </div>
      </div>

      <div v-if="questions.length" class="mt-5 space-y-3">
        <div class="h-2 overflow-hidden rounded-full bg-muted">
          <div
            class="h-full rounded-full bg-gradient-to-r from-primary via-chart-4 to-chart-2 transition-[width] duration-700 ease-[cubic-bezier(0.16,1,0.3,1)]"
            :style="{ width: `${progressPercent}%` }"
          />
        </div>
        <div class="flex flex-wrap gap-1.5">
          <button
            v-for="(question, index) in questions"
            :key="question.id"
            type="button"
            class="h-7 w-7 rounded-full text-[11px] font-bold transition-all duration-300"
            :class="[
              index === currentIndex
                ? 'scale-110 bg-primary text-primary-foreground shadow-md shadow-primary/40'
                : question.selected_option_id
                  ? 'bg-chart-2/20 text-chart-2 hover:scale-110'
                  : 'bg-muted text-muted-foreground hover:scale-110 hover:text-foreground',
            ]"
            :aria-label="`${index + 1}`"
            :aria-current="index === currentIndex ? 'step' : undefined"
            @click="goTo(index)"
          >
            {{ index + 1 }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="loading" class="space-y-3" aria-busy="true">
      <div class="h-24 animate-pulse rounded-2xl bg-muted/60" />
      <div
        v-for="n in 4"
        :key="n"
        class="h-12 animate-pulse rounded-xl bg-muted/60"
        :style="{ animationDelay: `${n * 90}ms` }"
      />
    </div>

    <div
      v-else-if="error"
      class="shake rounded-2xl border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive"
    >
      {{ error }}
    </div>

    <div v-else-if="currentQuestion" class="space-y-4">
      <div class="relative">
        <Transition
          :name="direction === 'next' ? 'q-next' : 'q-prev'"
          mode="out-in"
          @after-enter="renderMath"
        >
          <div
            :key="currentQuestion.id"
            class="rounded-2xl border border-border/70 bg-card/80 p-6 shadow-lg shadow-primary/5"
          >
            <p class="text-xs font-semibold uppercase tracking-wider text-primary">
              {{ currentIndex + 1 }} / {{ questions.length }}
            </p>
            <p class="mt-2 text-lg font-semibold text-foreground">
              <template
                v-for="(segment, idx) in splitMath(currentQuestion.question)"
                :key="`q-${currentQuestion.id}-${idx}`"
              >
                <span v-if="segment.type === 'text'">{{ segment.value }}</span>
                <span
                  v-else
                  class="inline-math"
                  :data-latex="segment.value"
                ></span>
              </template>
            </p>
            <div class="mt-5 grid gap-3">
              <button
                v-for="(option, optionIndex) in currentQuestion.options"
                :key="option.id"
                type="button"
                class="option-in group flex items-center gap-3 rounded-xl border px-4 py-3 text-left text-sm transition-all duration-300"
                :class="[
                  currentQuestion.selected_option_id === option.id
                    ? 'border-primary bg-primary/10 text-foreground shadow-md shadow-primary/20'
                    : 'border-border bg-muted/60 text-muted-foreground hover:-translate-y-0.5 hover:border-primary/60 hover:text-foreground',
                ]"
                :style="{ '--opt-delay': `${120 + optionIndex * 70}ms` }"
                :disabled="saving"
                @click="pick(option.id)"
              >
                <span
                  class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-xs font-bold transition-all duration-300"
                  :class="[
                    currentQuestion.selected_option_id === option.id
                      ? 'rotate-[-8deg] scale-110 bg-primary text-primary-foreground'
                      : 'bg-card text-muted-foreground group-hover:bg-primary/15 group-hover:text-primary',
                    lastPicked === option.id ? 'option-picked' : '',
                  ]"
                >
                  <Check
                    v-if="currentQuestion.selected_option_id === option.id"
                    :size="14"
                    :stroke-width="3"
                  />
                  <span v-else>{{ optionLetter(optionIndex) }}</span>
                </span>
                <span class="min-w-0 flex-1">
                  <template
                    v-for="(segment, idx) in splitMath(option.text)"
                    :key="`o-${option.id}-${idx}`"
                  >
                    <span v-if="segment.type === 'text'">{{ segment.value }}</span>
                    <span
                      v-else
                      class="inline-math"
                      :data-latex="segment.value"
                    ></span>
                  </template>
                </span>
              </button>
            </div>
          </div>
        </Transition>
      </div>

      <div class="flex items-center justify-between">
        <button
          class="inline-flex items-center gap-1 rounded-lg border border-border px-4 py-2 text-sm text-muted-foreground transition hover:-translate-x-0.5 hover:border-primary hover:text-primary disabled:pointer-events-none disabled:opacity-40"
          :disabled="currentIndex === 0"
          @click="goPrev"
        >
          <ChevronLeft :size="16" />
          Նախորդ
        </button>
        <p class="hidden text-[11px] text-muted-foreground sm:block">
          A–D / 1–4 · ← →
        </p>
        <div class="flex gap-2">
          <button
            class="inline-flex items-center gap-1 rounded-lg border border-border px-4 py-2 text-sm text-muted-foreground transition hover:translate-x-0.5 hover:border-primary hover:text-primary disabled:pointer-events-none disabled:opacity-40"
            :disabled="currentIndex >= questions.length - 1"
            @click="goNext"
          >
            Հաջորդ
            <ChevronRight :size="16" />
          </button>
          <button
            v-magnetic="0.15"
            class="inline-flex items-center gap-1.5 rounded-lg bg-chart-2 px-4 py-2 text-sm font-semibold text-primary-foreground shadow-lg shadow-chart-2/30 transition hover:brightness-110 disabled:opacity-50"
            :class="{ 'animate-pulse': allAnswered && !finishing }"
            :disabled="finishing"
            @click="finish"
          >
            <LoaderCircle v-if="finishing" :size="14" class="animate-spin" />
            <Flag v-else :size="14" />
            {{ finishing ? "Ավարտվում է..." : "Ավարտել" }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
