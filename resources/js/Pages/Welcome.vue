<script setup lang="ts">
import ThemeSwitcher from "@/components/ThemeSwitcher.vue";
import { Head, Link } from "@inertiajs/vue3";
import {
  ArrowRight,
  BookOpen,
  Box,
  ChartLine,
  Sparkles,
} from "lucide-vue-next";
import { route } from "ziggy-js";

defineProps<{
  canLogin?: boolean;
  canRegister?: boolean;
}>();
</script>

<template>
  <Head title="MathDash" />

  <section
    class="relative min-h-screen overflow-hidden bg-background text-slate-700 selection:bg-indigo-500/20 dark:text-slate-300 dark:selection:bg-indigo-500/30"
  >
    <div
      class="pointer-events-none absolute inset-0 z-0 opacity-[0.04]"
      style="
        background-image:
          linear-gradient(to right, #ffffff 1px, transparent 1px),
          linear-gradient(to bottom, #ffffff 1px, transparent 1px);
        background-size: 44px 44px;
      "
    />
    <div
      class="pointer-events-none absolute left-[-12%] top-[-14%] z-0 h-[45%] w-[45%] rounded-full bg-indigo-600/20 blur-[120px]"
    />
    <div
      class="pointer-events-none absolute bottom-[-14%] right-[-10%] z-0 h-[45%] w-[45%] rounded-full bg-emerald-600/15 blur-[120px]"
    />

    <header class="relative z-20">
      <div
        class="mx-auto flex h-20 w-full max-w-7xl items-center justify-between px-6 lg:px-10"
      >
        <Link href="/" class="flex items-center gap-3">
          <div
            class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600"
          >
            <span class="font-serif text-xl italic text-white">fx</span>
          </div>
          <div class="text-lg font-semibold text-slate-900 dark:text-white">
            MathDash<span class="text-indigo-500">.</span>
          </div>
        </Link>

        <div class="flex items-center gap-3">
          <ThemeSwitcher />
        </div>
      </div>
    </header>

    <main
      class="relative z-10 mx-auto flex min-h-[calc(100svh-5rem)] max-w-7xl items-center px-6 py-8 lg:px-10"
    >
      <div class="grid w-full items-center gap-10 lg:grid-cols-2">
        <div>
          <div
            class="mb-6 inline-flex items-center gap-2 rounded-full border border-indigo-500/20 bg-indigo-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-300"
          >
            <Sparkles :size="14" />
            {{ __("Adaptive Learning Workspace") }}
          </div>

          <h1
            class="text-4xl font-bold leading-tight tracking-tight text-slate-900 dark:text-white md:text-6xl"
          >
            {{ __("Smarter math practice for every student") }}
            <span
              class="bg-gradient-to-r from-indigo-500 to-emerald-500 bg-clip-text text-transparent"
            >
              MathDash
            </span>
          </h1>

          <p
            class="mt-6 max-w-2xl text-lg leading-relaxed text-slate-600 dark:text-slate-400"
          >
            {{
              __(
                "Explore graphing, geometry, calculators, and guided exercises in one focused workspace built to make learning math feel clear and engaging."
              )
            }}
          </p>

          <div class="mt-8 flex flex-wrap items-center gap-3">
            <Link
              v-if="canRegister"
              :href="route('register')"
              class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-medium text-white transition hover:bg-indigo-500"
            >
              {{ __("Get Started") }}
              <ArrowRight :size="16" />
            </Link>

            <Link
              v-if="canLogin"
              :href="route('login')"
              class="rounded-xl border border-slate-300 bg-white/80 px-5 py-3 text-sm font-medium text-slate-700 transition hover:bg-white dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200 dark:hover:bg-slate-900"
            >
              {{ __("Log In") }}
            </Link>
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <article
            class="rounded-2xl border border-slate-200/80 bg-white/80 p-5 shadow-lg backdrop-blur-sm dark:border-slate-700/50 dark:bg-slate-900/50"
          >
            <div
              class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-500/15 text-indigo-600 dark:text-indigo-300"
            >
              <ChartLine :size="18" />
            </div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">
              {{ __("Interactive visual tools") }}
            </h3>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
              {{
                __(
                  "Work with graphs, calculators, and visual explanations that turn abstract ideas into something easier to understand."
                )
              }}
            </p>
          </article>

          <article
            class="rounded-2xl border border-slate-200/80 bg-white/80 p-5 shadow-lg backdrop-blur-sm dark:border-slate-700/50 dark:bg-slate-900/50"
          >
            <div
              class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-300"
            >
              <Box :size="18" />
            </div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">
              {{ __("Spatial thinking and geometry") }}
            </h3>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
              {{
                __(
                  "Practice with shapes, dimensions, and 3D reasoning through tools designed for hands-on exploration."
                )
              }}
            </p>
          </article>

          <article
            class="sm:col-span-2 rounded-2xl border border-slate-200/80 bg-white/80 p-5 shadow-lg backdrop-blur-sm dark:border-slate-700/50 dark:bg-slate-900/50"
          >
            <div
              class="mb-3 flex items-center gap-2 text-indigo-600 dark:text-indigo-300"
            >
              <BookOpen :size="18" />
              <span class="text-sm font-semibold">
                {{ __("Structured practice that scales") }}
              </span>
            </div>
            <p
              class="text-sm leading-relaxed text-slate-600 dark:text-slate-400"
            >
              {{
                __(
                  "From quick exercises to generated quizzes, MathDash helps learners build confidence step by step."
                )
              }}
            </p>
          </article>
        </div>
      </div>
    </main>
  </section>
</template>
