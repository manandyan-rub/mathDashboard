<script setup lang="ts">
import ThemeSwitcher from "@/components/ThemeSwitcher.vue";
import { cmsTranslate as __ } from "@/lib/i18n";
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
    class="relative min-h-screen overflow-hidden bg-background text-muted-foreground selection:bg-primary/20 dark:text-muted-foreground dark:selection:bg-primary/30"
  >
    <div
      class="pointer-events-none absolute inset-0 z-0 opacity-[0.04]"
      style="
        background-image:
          linear-gradient(to right, var(--border) 1px, transparent 1px),
          linear-gradient(to bottom, var(--border) 1px, transparent 1px);
        background-size: 44px 44px;
      "
    />
    <div
      class="pointer-events-none absolute left-[-12%] top-[-14%] z-0 h-[45%] w-[45%] rounded-full bg-primary/20 blur-[120px]"
    />
    <div
      class="pointer-events-none absolute bottom-[-14%] right-[-10%] z-0 h-[45%] w-[45%] rounded-full bg-chart-2/15 blur-[120px]"
    />

    <header class="relative z-20">
      <div
        class="mx-auto flex h-20 w-full max-w-7xl items-center justify-between px-6 lg:px-10"
      >
        <Link href="/" class="flex items-center gap-3">
          <div
            class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-primary to-chart-4"
          >
            <span class="font-serif text-xl italic text-primary-foreground">fx</span>
          </div>
          <div class="text-lg font-semibold text-foreground dark:text-primary-foreground">
            MathDash<span class="text-primary">.</span>
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
            class="mb-6 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-primary dark:text-primary"
          >
            <Sparkles :size="14" />
            {{ __("Adaptive Learning Workspace") }}
          </div>

          <h1
            class="text-4xl font-bold leading-tight tracking-tight text-foreground dark:text-primary-foreground md:text-6xl"
          >
            {{ __("Smarter math practice for every student") }}
            <span
              class="bg-gradient-to-r from-primary to-chart-2 bg-clip-text text-transparent"
            >
              MathDash
            </span>
          </h1>

          <p
            class="mt-6 max-w-2xl text-lg leading-relaxed text-muted-foreground dark:text-muted-foreground"
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
              class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-medium text-primary-foreground transition hover:bg-primary/90"
            >
              {{ __("Get Started") }}
              <ArrowRight :size="16" />
            </Link>

            <Link
              v-if="canLogin"
              :href="route('login')"
              class="rounded-xl border border-border bg-card/80 px-5 py-3 text-sm font-medium text-muted-foreground transition hover:bg-card dark:border-border dark:bg-card/70 dark:text-foreground dark:hover:bg-card"
            >
              {{ __("Log In") }}
            </Link>
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <article
            class="rounded-2xl border border-border/80 bg-card/80 p-5 shadow-lg backdrop-blur-sm dark:border-border/50 dark:bg-card/50"
          >
            <div
              class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-primary/15 text-primary dark:text-primary"
            >
              <ChartLine :size="18" />
            </div>
            <h3 class="text-base font-semibold text-foreground dark:text-primary-foreground">
              {{ __("Interactive visual tools") }}
            </h3>
            <p class="mt-2 text-sm text-muted-foreground dark:text-muted-foreground">
              {{
                __(
                  "Work with graphs, calculators, and visual explanations that turn abstract ideas into something easier to understand."
                )
              }}
            </p>
          </article>

          <article
            class="rounded-2xl border border-border/80 bg-card/80 p-5 shadow-lg backdrop-blur-sm dark:border-border/50 dark:bg-card/50"
          >
            <div
              class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-chart-2/15 text-chart-2 dark:text-chart-2"
            >
              <Box :size="18" />
            </div>
            <h3 class="text-base font-semibold text-foreground dark:text-primary-foreground">
              {{ __("Spatial thinking and geometry") }}
            </h3>
            <p class="mt-2 text-sm text-muted-foreground dark:text-muted-foreground">
              {{
                __(
                  "Practice with shapes, dimensions, and 3D reasoning through tools designed for hands-on exploration."
                )
              }}
            </p>
          </article>

          <article
            class="sm:col-span-2 rounded-2xl border border-border/80 bg-card/80 p-5 shadow-lg backdrop-blur-sm dark:border-border/50 dark:bg-card/50"
          >
            <div
              class="mb-3 flex items-center gap-2 text-primary dark:text-primary"
            >
              <BookOpen :size="18" />
              <span class="text-sm font-semibold">
                {{ __("Structured practice that scales") }}
              </span>
            </div>
            <p
              class="text-sm leading-relaxed text-muted-foreground dark:text-muted-foreground"
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
