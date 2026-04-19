<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import axios from "axios";
import { Head } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { CircleCheck } from "lucide-vue-next";

type OptionItem = { id: number; text: string };
type QuestionItem = {
  id: number;
  question: string;
  options: OptionItem[];
};

const props = defineProps<{
  quizId: number;
  attemptId: number;
}>();

const quizTitle = ref("");
const questions = ref<QuestionItem[]>([]);
const selected = ref<Record<number, number>>({});
const loading = ref(true);
const error = ref<string | null>(null);
const submitting = ref(false);

const answeredCount = computed(() =>
  Object.values(selected.value).filter(Boolean).length,
);

async function loadQuiz() {
  loading.value = true;
  error.value = null;
  try {
    const response = await axios.get(`/pdf-quiz/quizzes/${props.quizId}`);
    quizTitle.value = response.data?.quiz?.title ?? "";
    questions.value = response.data?.questions ?? [];
  } catch (err: any) {
    error.value =
      err?.response?.data?.message ||
      err?.message ||
      "Չհաջողվեց բեռնել քվիզը։";
  } finally {
    loading.value = false;
  }
}

function selectOption(questionId: number, optionId: number) {
  selected.value = { ...selected.value, [questionId]: optionId };
}

async function submit() {
  submitting.value = true;
  error.value = null;
  try {
    const answers = questions.value.map((question) => ({
      question_id: question.id,
      option_id: selected.value[question.id] ?? null,
    }));

    await axios.post(`/pdf-quiz/attempts/${props.attemptId}/submit`, {
      answers,
    });

    window.location.href = route("pdf-quiz.result", props.attemptId);
  } catch (err: any) {
    error.value =
      err?.response?.data?.message ||
      err?.message ||
      "Չհաջողվեց ուղարկել պատասխանները։";
  } finally {
    submitting.value = false;
  }
}

onMounted(loadQuiz);
</script>

<template>
  <Head title="PDF քվիզ - լուծում" />

  <div class="mx-auto flex w-full max-w-5xl flex-col gap-6">
    <div
      class="rounded-2xl border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800/60 dark:bg-[#0B0F19]/60"
    >
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">
            {{ quizTitle || "PDF քվիզ" }}
          </h1>
          <p class="text-sm text-slate-500 dark:text-slate-300">
            Պատասխանեք հարցերին և ավարտեք քվիզը։
          </p>
        </div>
        <div class="text-sm text-slate-500 dark:text-slate-300">
          Պատասխանված՝ {{ answeredCount }} / {{ questions.length }}
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
        v-for="(question, index) in questions"
        :key="question.id"
        class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm dark:border-slate-800/60 dark:bg-[#0B0F19]/60"
      >
        <div class="flex items-center gap-2 text-sm font-semibold text-slate-500">
          <CircleCheck :size="16" class="text-indigo-500" />
          Հարց {{ index + 1 }}
        </div>
        <p class="mt-2 text-base text-slate-900 dark:text-white">
          {{ question.question }}
        </p>

        <div class="mt-4 grid gap-3">
          <button
            v-for="option in question.options"
            :key="option.id"
            type="button"
            class="rounded-xl border px-4 py-3 text-left text-sm transition"
            :class="
              selected[question.id] === option.id
                ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-400 dark:bg-indigo-500/20 dark:text-indigo-200'
                : 'border-slate-200 bg-slate-50 text-slate-700 hover:border-indigo-200 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-300'
            "
            @click="selectOption(question.id, option.id)"
          >
            {{ option.text }}
          </button>
        </div>
      </div>
    </div>

    <div class="flex justify-end">
      <button
        class="rounded-xl bg-emerald-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:opacity-50"
        :disabled="submitting || questions.length === 0"
        @click="submit"
      >
        {{ submitting ? "Ուղարկվում է..." : "Ավարտել քվիզը" }}
      </button>
    </div>
  </div>
</template>
