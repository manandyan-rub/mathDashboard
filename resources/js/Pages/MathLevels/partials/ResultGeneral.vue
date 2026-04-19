<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import axios from "axios";
import { nextTick, onMounted, onUpdated, ref } from "vue";
import { route } from "ziggy-js";
import { Award, Star } from "lucide-vue-next";
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
      class="rounded-2xl border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800/60 dark:bg-[#0B0F19]/60"
    >
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <div
            class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-600 text-white"
          >
            <Award :size="20" />
          </div>
          <div>
            <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">
              {{ summary.level_title || "Մակարդակի արդյունք" }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-300">
              XP {{ summary.xp_earned }}
            </p>
          </div>
        </div>
        <Link
          :href="route('math-levels.index')"
          class="rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:border-indigo-200 hover:text-indigo-700 dark:border-slate-700 dark:text-slate-300"
        >
          Վերադառնալ մակարդակներին
        </Link>
      </div>

      <div class="mt-5 grid gap-3 sm:grid-cols-4">
        <div class="rounded-xl bg-slate-50 p-4 text-center dark:bg-slate-900/60">
          <p class="text-xs uppercase tracking-wider text-slate-400">Գնահատական</p>
          <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-white">
            {{ summary.score }}%
          </p>
        </div>
        <div class="rounded-xl bg-slate-50 p-4 text-center dark:bg-slate-900/60">
          <p class="text-xs uppercase tracking-wider text-slate-400">Աստղեր</p>
          <div class="mt-2 flex items-center justify-center gap-1 text-amber-500">
            <Star v-for="star in summary.stars" :key="star" :size="20" />
          </div>
        </div>
        <div class="rounded-xl bg-slate-50 p-4 text-center dark:bg-slate-900/60">
          <p class="text-xs uppercase tracking-wider text-slate-400">Ճիշտ</p>
          <p class="mt-2 text-3xl font-semibold text-emerald-600">
            {{ summary.correct_answers }}
          </p>
        </div>
        <div class="rounded-xl bg-slate-50 p-4 text-center dark:bg-slate-900/60">
          <p class="text-xs uppercase tracking-wider text-slate-400">Սխալ</p>
          <p class="mt-2 text-3xl font-semibold text-red-500">
            {{ summary.wrong_answers }}
          </p>
        </div>
      </div>
    </div>

    <div
      v-if="loading"
      class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-400 dark:border-slate-800 dark:text-slate-400"
    >
      Բեռնվում է...
    </div>

    <div
      v-else-if="error"
      class="rounded-2xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-500"
    >
      {{ error }}
    </div>

    <div v-else class="space-y-5">
      <div
        v-for="(answer, index) in answers"
        :key="answer.question_id"
        class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm dark:border-slate-800/60 dark:bg-[#0B0F19]/60"
      >
        <div class="flex items-center justify-between gap-2">
          <div class="text-sm font-semibold text-slate-500">
            Հարց {{ index + 1 }}
          </div>
          <span
            class="rounded-full px-3 py-1 text-xs font-semibold"
            :class="
              answer.is_correct
                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200'
                : 'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-200'
            "
          >
            {{ answer.is_correct ? "Ճիշտ" : "Սխալ" }}
          </span>
        </div>
        <p class="mt-2 text-base text-slate-900 dark:text-white">
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
                ? 'border-emerald-400 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/20 dark:text-emerald-200'
                : option.is_selected
                  ? 'border-red-400 bg-red-50 text-red-600 dark:border-red-500/40 dark:bg-red-500/20 dark:text-red-200'
                  : 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-300'
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
          class="mt-3 text-sm text-slate-500 dark:text-slate-300"
        >
          Մեկնաբանություն: {{ answer.explanation }}
        </p>
      </div>
    </div>
  </div>
</template>
