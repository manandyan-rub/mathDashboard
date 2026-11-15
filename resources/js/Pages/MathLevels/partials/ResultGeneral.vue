<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import axios from "axios";
import { computed, nextTick, onMounted, onUpdated, ref } from "vue";
import AnimatedNumber from "@/components/motion/AnimatedNumber.vue";
import ConfettiBurst from "@/components/motion/ConfettiBurst.vue";
import { route } from "ziggy-js";
import { ArrowLeft, Award, Check, Star, X, Zap } from "lucide-vue-next";
import "mathlive";
import { MathfieldElement } from "mathlive";

MathfieldElement.fontsDirectory = "/fonts/mathlive";
MathfieldElement.soundsDirectory = "/sounds/mathlive";

type OptionItem = {
  id: number;
  text: string;
  is_correct: boolean;
  is_selected: boolean;
};

type AnswerItem = {
  question_id: number;
  question: string;
  explanation?: string | null;
  is_correct: boolean;
  options: OptionItem[];
};

const props = defineProps<{
  attemptId: number;
}>();

const summary = ref({
  score: 0,
  stars: 0,
  passed: false,
  xp_earned: 0,
  correct_answers: 0,
  wrong_answers: 0,
  level_title: "",
});
const answers = ref<AnswerItem[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
type Segment = {
  type: "text" | "math";
  value: string;
};

async function loadResult() {
  loading.value = true;
  error.value = null;
  try {
    const response = await axios.get(
      `/api/math-levels/attempts/${props.attemptId}/result`,
    );
    summary.value = response.data?.attempt ?? summary.value;
    answers.value = response.data?.answers ?? [];
    await nextTick();
    renderMath();
  } catch (err: any) {
    error.value =
      err?.response?.data?.message ||
      err?.message ||
      "Չհաջողվեց բեռնել արդյունքները։";
  } finally {
    loading.value = false;
  }
}

const RING_RADIUS = 52;
const RING_CIRCUMFERENCE = 2 * Math.PI * RING_RADIUS;
const ringOffset = computed(() => {
  if (loading.value) return RING_CIRCUMFERENCE;
  const score = Math.min(100, Math.max(0, Number(summary.value.score) || 0));
  return RING_CIRCUMFERENCE * (1 - score / 100);
});
const celebrate = computed(() => !loading.value && summary.value.passed);

onMounted(loadResult);
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
  <Head title="Մակարդակի արդյունք" />

  <div class="mx-auto flex w-full max-w-5xl flex-col gap-6">
    <div
      v-reveal="'scale'"
      class="aurora relative overflow-hidden rounded-2xl border border-border/70 bg-card/70 p-6 shadow-sm backdrop-blur-sm md:p-8"
    >
      <ConfettiBurst v-if="celebrate" />

      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <div
            class="flex h-12 w-12 items-center justify-center rounded-xl text-primary-foreground shadow-lg"
            :class="summary.passed ? 'bg-chart-2 shadow-chart-2/40' : 'bg-primary shadow-primary/40'"
          >
            <Award :size="22" :class="{ 'spin-slow': celebrate }" />
          </div>
          <div>
            <h1 class="text-shine text-2xl font-extrabold tracking-tight">
              {{ summary.level_title || "Մակարդակի արդյունք" }}
            </h1>
            <p class="mt-0.5 inline-flex items-center gap-1 text-sm font-semibold text-primary">
              <Zap :size="14" />
              +<AnimatedNumber :value="Number(summary.xp_earned) || 0" /> XP
            </p>
          </div>
        </div>
        <Link
          v-magnetic="0.15"
          :href="route('math-levels.index')"
          class="group inline-flex items-center gap-1.5 rounded-xl border border-border bg-card/80 px-4 py-2 text-sm text-muted-foreground transition-colors hover:border-primary hover:text-primary"
        >
          <ArrowLeft :size="15" class="transition-transform group-hover:-translate-x-1" />
          Վերադառնալ մակարդակներին
        </Link>
      </div>

      <div class="mt-6 grid items-center gap-6 md:grid-cols-[auto_1fr]">
        <div class="relative mx-auto h-36 w-36">
          <svg viewBox="0 0 120 120" class="h-full w-full -rotate-90">
            <circle cx="60" cy="60" :r="RING_RADIUS" fill="none" stroke="var(--muted)" stroke-width="10" />
            <circle
              cx="60"
              cy="60"
              :r="RING_RADIUS"
              fill="none"
              :stroke="summary.passed ? 'var(--chart-2)' : 'var(--primary)'"
              stroke-width="10"
              stroke-linecap="round"
              :stroke-dasharray="RING_CIRCUMFERENCE"
              :stroke-dashoffset="ringOffset"
              class="transition-[stroke-dashoffset] duration-[1600ms] ease-[cubic-bezier(0.16,1,0.3,1)]"
              style="filter: drop-shadow(0 0 6px color-mix(in srgb, var(--primary) 50%, transparent))"
            />
          </svg>
          <div class="absolute inset-0 flex flex-col items-center justify-center">
            <p class="text-3xl font-extrabold text-foreground">
              <AnimatedNumber :value="loading ? 0 : Number(summary.score) || 0" />%
            </p>
            <p class="text-[11px] uppercase tracking-wider text-muted-foreground">Գնահատական</p>
          </div>
        </div>

        <div class="space-y-4">
          <div v-if="!loading" class="flex items-center justify-center gap-3 md:justify-start">
            <span
              v-for="star in 3"
              :key="star"
              class="star-pop"
              :style="{ '--star-delay': `${600 + star * 260}ms` }"
            >
              <Star
                :size="40"
                :class="
                  star <= summary.stars
                    ? 'star-glow fill-chart-3 text-chart-3'
                    : 'text-muted-foreground/30'
                "
              />
            </span>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div
              v-reveal="{ variant: 'up', delay: 300 }"
              v-tilt
              class="rounded-xl border border-chart-2/30 bg-chart-2/10 p-4"
            >
              <p class="inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wider text-chart-2">
                <Check :size="13" :stroke-width="3" /> Ճիշտ
              </p>
              <p class="mt-1 text-3xl font-bold text-chart-2">
                <AnimatedNumber :value="Number(summary.correct_answers) || 0" />
              </p>
            </div>
            <div
              v-reveal="{ variant: 'up', delay: 400 }"
              v-tilt
              class="rounded-xl border border-destructive/30 bg-destructive/10 p-4"
            >
              <p class="inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wider text-destructive">
                <X :size="13" :stroke-width="3" /> Սխալ
              </p>
              <p class="mt-1 text-3xl font-bold text-destructive">
                <AnimatedNumber :value="Number(summary.wrong_answers) || 0" />
              </p>
            </div>
          </div>
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

    <div v-else class="space-y-5">
      <div
        v-for="(answer, index) in answers"
        :key="answer.question_id"
        v-reveal="{ variant: 'up', delay: Math.min(index, 3) * 80 }"
        class="rounded-2xl border bg-card/80 p-5 shadow-sm"
        :class="answer.is_correct ? 'border-chart-2/30' : 'border-destructive/30'"
      >
        <div class="flex items-center justify-between gap-2">
          <div class="text-sm font-semibold text-muted-foreground">
            Հարց {{ index + 1 }}
          </div>
          <span
            class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold"
            :class="
              answer.is_correct
                ? 'bg-chart-2/10 text-chart-2 dark:bg-chart-2/20 dark:text-chart-2'
                : 'bg-destructive/10 text-destructive dark:bg-destructive/20 dark:text-destructive'
            "
          >
            <Check v-if="answer.is_correct" :size="12" :stroke-width="3" />
            <X v-else :size="12" :stroke-width="3" />
            {{ answer.is_correct ? "Ճիշտ" : "Սխալ" }}
          </span>
        </div>
        <p class="mt-2 text-base text-foreground dark:text-primary-foreground">
          <template
            v-for="(segment, idx) in splitMath(answer.question)"
            :key="`rq-${answer.question_id}-${idx}`"
          >
            <span v-if="segment.type === 'text'">{{ segment.value }}</span>
            <span
              v-else
              class="inline-math"
              :data-latex="segment.value"
            ></span>
          </template>
        </p>
        <div class="mt-3 grid gap-2">
          <div
            v-for="option in answer.options"
            :key="option.id"
            class="rounded-xl border px-4 py-3 text-sm"
            :class="
              option.is_correct
                ? 'border-chart-2/30 bg-chart-2/10 text-chart-2 dark:border-chart-2/40 dark:bg-chart-2/20 dark:text-chart-2'
                : option.is_selected
                  ? 'border-destructive/30 bg-destructive/10 text-destructive dark:border-destructive/40 dark:bg-destructive/20 dark:text-destructive'
                  : 'border-border bg-muted text-muted-foreground dark:border-border dark:bg-card/60 dark:text-muted-foreground'
            "
          >
            <template
              v-for="(segment, idx) in splitMath(option.text)"
              :key="`ro-${option.id}-${idx}`"
            >
              <span v-if="segment.type === 'text'">{{ segment.value }}</span>
              <span
                v-else
                class="inline-math"
                :data-latex="segment.value"
              ></span>
            </template>
          </div>
        </div>
        <p
          v-if="answer.explanation"
          class="mt-3 text-sm text-muted-foreground dark:text-muted-foreground"
        >
          Մեկնաբանություն: {{ answer.explanation }}
        </p>
      </div>
    </div>
  </div>
</template>
