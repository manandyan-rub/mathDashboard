<script setup lang="ts">
import { computed, ref } from "vue";
import { Head } from "@inertiajs/vue3";
import axios from "axios";
import { route } from "ziggy-js";
import { UploadCloud } from "lucide-vue-next";

const file = ref<File | null>(null);
const title = ref("");
const questionCount = ref(10);
const difficulty = ref("medium");
const language = ref("armenian");
const loading = ref(false);
const error = ref<string | null>(null);

const canSubmit = computed(() => !!file.value && !loading.value);

function onFileChange(event: Event) {
  const target = event.target as HTMLInputElement;
  if (!target.files || target.files.length === 0) {
    file.value = null;
    return;
  }
  file.value = target.files[0];
}

async function submit() {
  if (!file.value) {
    error.value = "Խնդրում ենք ընտրել PDF ֆայլ։";
    return;
  }

  loading.value = true;
  error.value = null;

  const formData = new FormData();
  formData.append("pdf", file.value);
  formData.append("title", title.value.trim());
  formData.append("question_count", String(questionCount.value));
  formData.append("difficulty", difficulty.value);
  formData.append("language", language.value);

  try {
    const response = await axios.post("/pdf-quiz/imports", formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });

    const quizId = response.data?.quiz_id;
    if (quizId) {
      window.location.href = route("pdf-quiz.preview", quizId);
      return;
    }
    error.value = "Չհաջողվեց ստեղծել քվիզ։";
  } catch (err: any) {
    error.value =
      err?.response?.data?.message ||
      err?.message ||
      "Սխալ է տեղի ունեցել PDF-ը մշակելիս։";
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <Head title="PDF քվիզ" />

  <div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
    <div
      class="rounded-2xl border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800/60 dark:bg-[#0B0F19]/60"
    >
      <div class="flex items-center gap-3">
        <div
          class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-600 text-white"
        >
          <UploadCloud :size="20" />
        </div>
        <div>
          <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">
            PDF քվիզ ստեղծում
          </h1>
          <p class="text-sm text-slate-500 dark:text-slate-300">
            Վերբեռնեք PDF ֆայլ և ստացեք ավտոմատ քվիզ։
          </p>
        </div>
      </div>
    </div>

    <div
      class="rounded-2xl border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800/60 dark:bg-[#0B0F19]/60"
    >
      <div class="grid gap-5 md:grid-cols-2">
        <div class="space-y-2">
          <label
            class="text-sm font-semibold text-slate-700 dark:text-slate-200"
            >PDF ֆայլ</label
          >
          <input
            type="file"
            accept="application/pdf"
            class="block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
            @change="onFileChange"
          />
          <p class="text-xs text-slate-400">
            Միայն տեքստային PDF-երը լավագույն արդյունքն են տալիս։
          </p>
        </div>

        <div class="space-y-2">
          <label
            class="text-sm font-semibold text-slate-700 dark:text-slate-200"
            >Վերնագիր (ըստ ցանկության)</label
          >
          <input
            v-model="title"
            type="text"
            placeholder="Օր․ Հանրահաշիվ 1"
            class="block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
          />
        </div>

        <div class="space-y-2">
          <label
            class="text-sm font-semibold text-slate-700 dark:text-slate-200"
            >Հարցերի քանակ</label
          >
          <input
            v-model.number="questionCount"
            type="number"
            min="1"
            max="30"
            class="block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
          />
        </div>

        <div class="space-y-2">
          <label
            class="text-sm font-semibold text-slate-700 dark:text-slate-200"
            >Բարդություն</label
          >
          <select
            v-model="difficulty"
            class="block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
          >
            <option value="easy">Հեշտ</option>
            <option value="medium">Միջին</option>
            <option value="hard">Բարդ</option>
          </select>
        </div>

        <div class="space-y-2">
          <label
            class="text-sm font-semibold text-slate-700 dark:text-slate-200"
            >Լեզու</label
          >
          <select
            v-model="language"
            class="block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
          >
            <option value="armenian">Հայերեն</option>
            <option value="english">English</option>
          </select>
        </div>
      </div>

      <div class="mt-6 flex flex-wrap items-center gap-3">
        <button
          class="rounded-xl bg-indigo-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:opacity-50"
          :disabled="!canSubmit"
          @click="submit"
        >
          {{ loading ? "Մշակվում է..." : "Ստեղծել քվիզ" }}
        </button>
        <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
      </div>
      <p class="mt-3 text-xs text-slate-400">
        Ստացված հարցերը կարող են պահանջել արագ վերանայում։
      </p>
    </div>
  </div>
</template>
