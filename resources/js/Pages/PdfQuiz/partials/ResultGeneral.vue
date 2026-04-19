<script setup lang="ts">
import { onMounted, ref } from "vue";
import axios from "axios";
import { Head } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { Award } from "lucide-vue-next";

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
  quizId: number;
  attemptId: number;
}>();

const summary = ref({
  score: 0,
  correct_answers: 0,
  wrong_answers: 0,
  total_questions: 0,
  quiz_title: "",
});
const answers = ref<AnswerItem[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

async function loadResult() {
  loading.value = true;
  error.value = null;
  try {
    const response = await axios.get(
      `/pdf-quiz/attempts/${props.attemptId}`,
    );
    summary.value = response.data?.attempt ?? summary.value;
    answers.value = response.data?.answers ?? [];
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
</script>

<template>
  <Head title="PDF քվիզ - արդյունք" />

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
              {{ summary.quiz_title || "Քվիզի արդյունք" }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-300">
              Վերջնական արդյունքներ
            </p>
          </div>
        </div>
        <button
          class="rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:border-indigo-200 hover:text-indigo-700 dark:border-slate-700 dark:text-slate-300"
          @click="window.location.href = route('pdf-quiz.upload')"
        >
          Նոր քվիզ
        </button>
      </div>

      <div class="mt-5 grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl bg-slate-50 p-4 text-center dark:bg-slate-900/60">
          <p class="text-xs uppercase tracking-wider text-slate-400">Գնահատական</p>
          <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-white">
            {{ summary.score }}%
          </p>
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
          {{ answer.question }}
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
            {{ option.text }}
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
