<script setup lang="ts">
import { computed, ref, nextTick, onMounted, onUpdated } from "vue";
import { Head } from "@inertiajs/vue3";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { cmsTranslate as __ } from "@/lib/i18n";
import { Download, Sparkles } from "lucide-vue-next";
import "mathlive";
import { MathfieldElement } from "mathlive";

type Exercise = {
  question: string;
  answer: string;
  difficulty: string;
};

type Segment = {
  type: "text" | "math";
  value: string;
};

const prompt = ref("");
const title = ref("");
const exercises = ref<Exercise[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const pdfLoading = ref(false);
const pdfError = ref<string | null>(null);
const count = ref(1);

MathfieldElement.fontsDirectory = "/fonts/mathlive";
MathfieldElement.soundsDirectory = "/sounds/mathlive";

const samples = [
  "ХЌХїХҐХІХ®Х«ЦЂ Ц„ХЎХјХЎХЇХёЦ‚ХЅХЎХµХ«Х¶ Х°ХЎХѕХЎХЅХЎЦЂХёЦ‚ХґХ¶ХҐЦЂХ« Х°ХҐХ·Хї ХѕХЎЦЂХЄХёЦ‚Х©ХµХёЦ‚Х¶",
  "ХЌХїХҐХІХ®Х«ЦЂ ХїХёХЇХёХЅХ¶ХҐЦЂХ« ХґХЎХЅХ«Х¶ ХґХ«Х»Х«Х¶ ХўХЎЦЂХ¤ХёЦ‚Х©ХµХЎХ¶ Х­Х¶Х¤Х«ЦЂ",
  "ХЌХїХҐХІХ®Х«ЦЂ ХҐЦЂХЇЦЂХЎХ№ХЎЦѓХЎХЇХЎХ¶ ХѕХЎЦЂХЄХёЦ‚Х©ХµХёЦ‚Х¶ Х·ЦЂХ»ХЎХ¶Х¶ХҐЦЂХ« Х©ХҐХґХЎХµХёХѕ",
  "ХЌХїХҐХІХ®Х«ЦЂ Х°ХЎХѕХЎХЅХЎЦЂХёЦ‚ХґХ¶ХҐЦЂХ« Х°ХЎХґХЎХЇХЎЦЂХЈХ« ХўХЎЦЂХ¤ ХѕХЎЦЂХЄХёЦ‚Х©ХµХёЦ‚Х¶",
];

const canGenerate = computed(() => prompt.value.trim().length > 0);
const canDownload = computed(
  () => exercises.value.length > 0 && !pdfLoading.value,
);

async function generateExercise() {
  if (!canGenerate.value) return;

  loading.value = true;
  error.value = null;
  exercises.value = [];

  try {
    const response = await fetch("/api/exercises/generate", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        prompt: prompt.value.trim(),
        count: count.value,
      }),
    });

    if (!response.ok) {
      const errBody = await response.json().catch(() => null);
      const details =
        typeof errBody?.details === "string" ? errBody.details : null;
      const message =
        typeof errBody?.message === "string"
          ? errBody.message
          : "Х‰Х°ХЎХ»ХёХІХѕХҐЦЃ ХЅХїХҐХІХ®ХҐХ¬ ХѕХЎЦЂХЄХёЦ‚Х©ХµХёЦ‚Х¶ХЁ";
      throw new Error(details ? `${message} (${details})` : message);
    }
    const data = await response.json();
    if (data?.busy) {
      exercises.value = [];
      error.value = data.message ?? "Х„ХёХ¤ХҐХ¬ХЁ Х®ХЎХ¶ЦЂХЎХўХҐХјХ¶ХѕХЎХ® Х§";
      return;
    }
    if (data?.invalid) {
      console.warn("Gemini returned invalid JSON:", data.raw);
      exercises.value = [];
      error.value = "JSON Х±Ц‡ХЎХ№ХЎЦѓХЁ ХЅХ­ХЎХ¬ Х§ ХѕХҐЦЂХЎХ¤ХЎЦЂХ±ХѕХҐХ¬Ц‰";
      return;
    }
    if (Array.isArray(data?.exercises)) {
      exercises.value = data.exercises;
    } else if (data?.question || data?.answer) {
      exercises.value = [
        {
          question: data.question ?? "",
          answer: data.answer ?? "",
          difficulty: data.difficulty ?? "",
        },
      ];
    } else {
      exercises.value = [];
    }
  } catch (err) {
    error.value =
      err instanceof Error ? err.message : "ХЏХѕХµХЎХ¬Х¶ХҐЦЂХ« ХўХҐХјХ¶ХёЦ‚ХґХЁ Х±ХЎХ­ХёХІХѕХҐЦЃ";
  } finally {
    loading.value = false;
  }
}

async function downloadPdf() {
  if (exercises.value.length === 0) return;

  pdfLoading.value = true;
  pdfError.value = null;

  try {
    const response = await fetch("/api/exercises/pdf", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        title: title.value.trim(),
        exercises: exercises.value,
      }),
    });

    if (!response.ok) {
      const errBody = await response.json().catch(() => null);
      const details =
        typeof errBody?.details === "string" ? errBody.details : null;
      const message =
        typeof errBody?.message === "string"
          ? errBody.message
          : "Х‰Х°ХЎХ»ХёХІХѕХҐЦЃ ХєХЎХїЦЂХЎХЅХїХҐХ¬ PDF-ХЁ";
      throw new Error(details ? `${message} (${details})` : message);
    }
    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    const safeTitle = title.value.trim().replace(/\s+/g, "_");
    link.href = url;
    link.download = `${safeTitle || "exercise"}.pdf`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  } catch (err) {
    pdfError.value =
      err instanceof Error ? err.message : "PDF-Х« ХЅХїХҐХІХ®ХёЦ‚ХґХЁ Х±ХЎХ­ХёХІХѕХҐЦЃ";
  } finally {
    pdfLoading.value = false;
  }
}

function applySample(sample: string) {
  prompt.value = sample;
}

function clearAll() {
  prompt.value = "";
  title.value = "";
  exercises.value = [];
  error.value = null;
  pdfError.value = null;
}

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

    const latex = match[1] ?? match[2] ?? match[3] ?? (match[0] ?? "").trim();
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

onMounted(() => nextTick(renderMath));
onUpdated(() => nextTick(renderMath));
</script>

<template>
  <Head :title="__('AI Exercise Generator')" />

  <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
    <div
      class="flex flex-col gap-4 rounded-2xl border border-border/70 bg-card/90 p-6 shadow-sm dark:border-border/60 dark:bg-card/60"
    >
      <div class="flex flex-wrap items-center gap-3">
        <div
          class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-chart-4 text-primary-foreground"
        >
          <Sparkles :size="18" />
        </div>
        <div class="space-y-1">
          <h1
            class="text-2xl font-semibold tracking-tight text-foreground dark:text-primary-foreground"
          >
            AI ХѕХЎЦЂХЄХёЦ‚Х©ХµХёЦ‚Х¶Х¶ХҐЦЂХ« ХЈХҐХ¶ХҐЦЂХЎХїХёЦЂ
          </h1>
          <p class="text-sm text-muted-foreground dark:text-muted-foreground">
            ФіЦЂХ«ЦЂ, Х©ХҐ Х«Х¶Х№ ХѕХЎЦЂХЄХёЦ‚Х©ХµХёЦ‚Х¶ Х§ ХєХҐХїЦ„, Ц‡ ХЅХїХЎЦЃХ«ЦЂ ХєХЎХїЦЂХЎХЅХї Х°ХЎЦЂЦЃ ХёЦ‚ ХєХЎХїХЎХЅХ­ХЎХ¶Ц‰
          </p>
        </div>
        <Badge variant="secondary" class="ml-auto">AI Ц…ХЈХ¶ХЎХЇХЎХ¶</Badge>
      </div>
    </div>
    <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
      <div
        class="flex flex-col gap-5 rounded-2xl border border-border/70 bg-card p-6 shadow-sm dark:border-border/60 dark:bg-card/60"
      >
        <div class="space-y-2">
          <label
            class="text-sm font-semibold text-muted-foreground dark:text-foreground"
            >ХЋХЎЦЂХЄХёЦ‚Х©ХµХЎХ¶ Х¶ХЇХЎЦЂХЎХЈЦЂХёЦ‚Х©ХµХёЦ‚Х¶</label
          >
          <Textarea
            v-model="prompt"
            rows="6"
            placeholder="Х•ЦЂХ«Х¶ХЎХЇХќ ХЅХїХҐХІХ®Х«ЦЂ ХґХ«Х»Х«Х¶ ХўХЎЦЂХ¤ХёЦ‚Х©ХµХЎХ¶ Х°ХЎХѕХЎХЅХЎЦЂХёЦ‚ХґХ¶ХҐЦЂХ« Х­Х¶Х¤Х«ЦЂ"
            class="resize-none"
          />
        </div>
        <div class="space-y-2">
          <label
            class="text-sm font-semibold text-muted-foreground dark:text-foreground"
            >{{ __("Exercise count") }}</label
          >
          <Input v-model.number="count" type="number" min="1" max="10" />
        </div>

        <div class="space-y-2">
          <label
            class="text-sm font-semibold text-muted-foreground dark:text-foreground"
            >{{ __("PDF title (optional)") }}</label
          >
          <Input
            v-model="title"
            placeholder="Х•ЦЂХ«Х¶ХЎХЇХќ ХЂХЎХ¶ЦЂХЎХ°ХЎХ·Х«Хѕ - ХЋХЎЦЂХЄХёЦ‚Х©ХµХёЦ‚Х¶ 1"
          />
        </div>

        <div class="space-y-2">
          <p
            class="text-xs font-semibold uppercase tracking-wider text-muted-foreground"
          >
            Х•ЦЂХ«Х¶ХЎХЇ Х°ХЎЦЂЦЃХёЦ‚ХґХ¶ХҐЦЂ
          </p>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="sample in samples"
              :key="sample"
              type="button"
              class="rounded-full border border-border bg-muted px-3 py-1 text-xs font-medium text-muted-foreground transition hover:border-primary hover:text-primary dark:border-border dark:bg-card dark:text-muted-foreground dark:hover:text-primary-foreground"
              @click="applySample(sample)"
            >
              {{ sample }}
            </button>
          </div>
        </div>

        <div class="flex flex-wrap gap-3 pt-2">
          <Button
            class="gap-2"
            :disabled="loading || !canGenerate"
            @click="generateExercise"
          >
            <Sparkles :size="16" />
            {{ loading ? "ХЌХїХҐХІХ®ХѕХёЦ‚Хґ Х§..." : "ХЌХїХЎХ¶ХЎХ¬ ХѕХЎЦЂХЄХёЦ‚Х©ХµХёЦ‚Х¶" }}
          </Button>
          <Button variant="outline" :disabled="loading" @click="clearAll">
            Х„ХЎЦ„ЦЂХҐХ¬
          </Button>
        </div>

        <p v-if="error" class="text-sm text-destructive dark:text-destructive">
          {{ error }}
        </p>
      </div>

      <div
        class="flex flex-col gap-4 rounded-2xl border border-border/70 bg-card p-6 shadow-sm dark:border-border/60 dark:bg-card/60"
      >
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-foreground dark:text-primary-foreground">
            Ф±ЦЂХ¤ХµХёЦ‚Х¶Ц„
          </h2>
          <Badge variant="outline" class="text-xs">
            {{ exercises.length > 0 ? "ХЉХЎХїЦЂХЎХЅХї Х§" : "ХЌХєХЎХЅХёЦ‚Хґ Х§" }}
          </Badge>
        </div>

        <div
          v-if="exercises.length === 0 && !loading"
          class="flex flex-1 flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground dark:border-border dark:text-muted-foreground"
        >
          <Sparkles :size="24" />
          Х†ХЇХЎЦЂХЎХЈЦЂХ«ЦЂ ХѕХЎЦЂХЄХёЦ‚Х©ХµХёЦ‚Х¶ХЁХќ ХЎЦЂХ¤ХµХёЦ‚Х¶Ц„ХЁ ХЎХµХЅХїХҐХІ ЦЃХёЦ‚ХµЦЃ ХїХЎХ¬ХёЦ‚ Х°ХЎХґХЎЦЂ
        </div>

        <div v-if="loading" class="space-y-3">
          <div
            class="h-4 w-2/3 animate-pulse rounded bg-muted/70 dark:bg-card/70"
          />
          <div
            class="h-4 w-full animate-pulse rounded bg-muted/70 dark:bg-card/70"
          />
          <div
            class="h-4 w-5/6 animate-pulse rounded bg-muted/70 dark:bg-card/70"
          />
        </div>

        <div v-if="exercises.length > 0" class="space-y-6">
          <div
            v-for="(exerciseItem, index) in exercises"
            :key="index"
            class="space-y-4"
          >
            <div class="text-xs font-semibold uppercase text-muted-foreground">
              ХЋХЎЦЂХЄХёЦ‚Х©ХµХёЦ‚Х¶ {{ index + 1 }}
            </div>
            <div
              class="rounded-xl bg-muted p-4 text-sm text-muted-foreground dark:bg-card/60 dark:text-foreground"
            >
              <p class="text-xs font-semibold uppercase text-muted-foreground">ХЂХЎЦЂЦЃ</p>
              <p class="mt-2 whitespace-pre-line">
                <template
                  v-for="(segment, segmentIndex) in splitMath(
                    exerciseItem.question,
                  )"
                  :key="`q-${index}-${segmentIndex}`"
                >
                  <span v-if="segment.type === 'text'">{{
                    segment.value
                  }}</span>
                  <span
                    v-else
                    class="inline-math"
                    :data-latex="segment.value"
                  />
                </template>
              </p>
            </div>
            <div
              class="rounded-xl bg-muted p-4 text-sm text-muted-foreground dark:bg-card/60 dark:text-foreground"
            >
              <p class="text-xs font-semibold uppercase text-muted-foreground">
                ХЉХЎХїХЎХЅХ­ХЎХ¶
              </p>
              <p class="mt-2 whitespace-pre-line">
                <template
                  v-for="(segment, segmentIndex) in splitMath(
                    exerciseItem.answer,
                  )"
                  :key="`a-${index}-${segmentIndex}`"
                >
                  <span v-if="segment.type === 'text'">{{
                    segment.value
                  }}</span>
                  <span
                    v-else
                    class="inline-math"
                    :data-latex="segment.value"
                  />
                </template>
              </p>
            </div>
            <div
              class="flex items-center justify-between text-xs text-muted-foreground dark:text-muted-foreground"
            >
              <span>
                ФІХЎЦЂХ¤ХёЦ‚Х©ХµХёЦ‚Х¶:
                <span class="text-muted-foreground dark:text-foreground">{{
                  exerciseItem.difficulty || "Х‰Х¶Х·ХѕХЎХ®"
                }}</span>
              </span>
            </div>
          </div>
        </div>

        <div class="mt-auto flex flex-wrap items-center gap-3 pt-3">
          <Button
            variant="secondary"
            class="gap-2"
            :disabled="!canDownload"
            @click="downloadPdf"
          >
            <Download :size="16" />
            {{ pdfLoading ? "PDF-ХЁ ХЅХїХҐХІХ®ХѕХёЦ‚Хґ Х§..." : "ФІХҐХјХ¶ХҐХ¬ PDF" }}
          </Button>
          <p v-if="pdfError" class="text-xs text-destructive dark:text-destructive">
            {{ pdfError }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>


