<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import axios from "axios";
import { computed, nextTick, onMounted, onUpdated, ref } from "vue";
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
    currentIndex.value += 1;
  }
}

function goPrev() {
  if (currentIndex.value > 0) {
    currentIndex.value -= 1;
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
      class="rounded-2xl border border-border/70 bg-card p-6 shadow-sm dark:border-border/60 dark:bg-card/60"
    >
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-semibold text-foreground dark:text-primary-foreground">
            {{ levelTitle }}
          </h1>
          <p class="text-sm text-muted-foreground dark:text-muted-foreground">
            XP {{ xpReward }}
          </p>
        </div>
        <div class="text-sm text-muted-foreground dark:text-muted-foreground">
          {{ progressText }}
        </div>
      </div>
    </div>

    <div
      v-if="loading"
      class="rounded-2xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground dark:border-border dark:text-muted-foreground"
    >
      Բեռնվում է...
    </div>

    <div
      v-else-if="error"
      class="rounded-2xl border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive"
    >
      {{ error }}
    </div>

    <div v-else-if="currentQuestion" class="space-y-4">
      <div
        class="rounded-2xl border border-border/70 bg-card p-6 shadow-sm dark:border-border/60 dark:bg-card/60"
      >
        <p class="text-lg font-semibold text-foreground dark:text-primary-foreground">
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
        <div class="mt-4 grid gap-3">
          <button
            v-for="option in currentQuestion.options"
            :key="option.id"
            type="button"
            class="rounded-xl border px-4 py-3 text-left text-sm transition"
            :class="
              currentQuestion.selected_option_id === option.id
                ? 'border-primary/30 bg-accent text-accent-foreground dark:border-primary dark:bg-primary/20 dark:text-primary'
                : 'border-border bg-muted text-muted-foreground hover:border-primary dark:border-border dark:bg-card/60 dark:text-muted-foreground'
            "
            :disabled="saving"
            @click="chooseOption(option.id)"
          >
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
          </button>
        </div>
      </div>

      <div class="flex items-center justify-between">
        <button
          class="rounded-lg border border-border px-4 py-2 text-sm text-muted-foreground transition hover:border-primary hover:text-primary dark:border-border dark:text-muted-foreground"
          :disabled="currentIndex === 0"
          @click="goPrev"
        >
          Նախորդ
        </button>
        <div class="flex gap-2">
          <button
            class="rounded-lg border border-border px-4 py-2 text-sm text-muted-foreground transition hover:border-primary hover:text-primary dark:border-border dark:text-muted-foreground"
            :disabled="currentIndex >= questions.length - 1"
            @click="goNext"
          >
            Հաջորդ
          </button>
          <button
            class="rounded-lg bg-chart-2 px-4 py-2 text-sm font-semibold text-primary-foreground transition hover:bg-chart-2 disabled:opacity-50"
            :disabled="finishing"
            @click="finish"
          >
            {{ finishing ? "Ավարտվում է..." : "Ավարտել" }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
