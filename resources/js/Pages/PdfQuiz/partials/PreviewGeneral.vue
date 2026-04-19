<script setup lang="ts">
import { onMounted, ref } from "vue";
import axios from "axios";
import { Head } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { CheckCircle2 } from "lucide-vue-next";

type OptionItem = { id: number; text: string };
type QuestionItem = {
  id: number;
  question: string;
  explanation?: string | null;
  options: OptionItem[];
};

const props = defineProps<{
  quizId: number;
}>();

const quizTitle = ref("");
const difficulty = ref("");
const questions = ref<QuestionItem[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const starting = ref(false);

async function loadQuiz() {
  loading.value = true;
  error.value = null;
  try {
    const response = await axios.get(`/pdf-quiz/quizzes/${props.quizId}`);
    quizTitle.value = response.data?.quiz?.title ?? "";
    difficulty.value = response.data?.quiz?.difficulty ?? "";
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

async function startQuiz() {
  starting.value = true;
  error.value = null;
  try {
    const response = await axios.post(
      `/pdf-quiz/quizzes/${props.quizId}/attempts`,
    );
    const attemptId = response.data?.attempt_id;
    if (attemptId) {
      window.location.href = route("pdf-quiz.session", attemptId);
      return;
    }
    error.value = "Չհաջողվեց սկսել քվիզը։";
  } catch (err: any) {
    error.value =
      err?.response?.data?.message ||
      err?.message ||
      "Չհաջողվեց սկսել քվիզը։";
  } finally {
    starting.value = false;
  }
}

onMounted(loadQuiz);
</script>

<template>
  <Head title="PDF քվիզ - նախադիտում" />

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
            Նախադիտեք հարցերը և սկսեք քվիզը։
          </p>
        </div>
        <button
          class="rounded-xl bg-emerald-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:opacity-50"
          :disabled="starting || loading || questions.length === 0"
          @click="startQuiz"
        >
          {{ starting ? "Բեռնվում է..." : "Սկսել քվիզը" }}
        </button>
      </div>
      <p v-if="difficulty" class="mt-2 text-xs text-slate-400">
        Բարդություն: {{ difficulty }}
      </p>
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
          <CheckCircle2 :size="16" class="text-emerald-500" />
          Հարց {{ index + 1 }}
        </div>
        <p class="mt-2 text-base text-slate-900 dark:text-white">
          {{ question.question }}
        </p>
        <ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
          <li
            v-for="option in question.options"
            :key="option.id"
            class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 dark:border-slate-800 dark:bg-slate-900/60"
          >
            {{ option.text }}
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
