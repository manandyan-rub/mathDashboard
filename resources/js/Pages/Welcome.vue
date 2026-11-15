<script setup lang="ts">
import AnimatedBackground from "@/components/AnimatedBackground.vue";
import FormulaMarquee from "@/components/motion/FormulaMarquee.vue";
import GraphSketch from "@/components/motion/GraphSketch.vue";
import SplitText from "@/components/motion/SplitText.vue";
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

const features = [
  {
    icon: ChartLine,
    tone: "bg-primary/15 text-primary",
    title: __("Interactive visual tools"),
    body: __(
      "Work with graphs, calculators, and visual explanations that turn abstract ideas into something easier to understand.",
    ),
  },
  {
    icon: Box,
    tone: "bg-chart-2/15 text-chart-2",
    title: __("Spatial thinking and geometry"),
    body: __(
      "Practice with shapes, dimensions, and 3D reasoning through tools designed for hands-on exploration.",
    ),
  },
  {
    icon: BookOpen,
    tone: "bg-chart-4/15 text-chart-4",
    title: __("Structured practice that scales"),
    body: __(
      "From quick exercises to generated quizzes, MathDash helps learners build confidence step by step.",
    ),
  },
];
</script>

<template>
  <Head title="MathDash" />

  <section
    v-spotlight
    class="relative min-h-screen overflow-hidden bg-background text-muted-foreground selection:bg-primary/20 dark:selection:bg-primary/30"
  >
    <AnimatedBackground />

    <div
      class="pointer-events-none absolute inset-0 z-0 opacity-[0.05]"
      style="
        background-image:
          linear-gradient(to right, var(--border) 1px, transparent 1px),
          linear-gradient(to bottom, var(--border) 1px, transparent 1px);
        background-size: 44px 44px;
        mask-image: radial-gradient(ellipse at center, #000 30%, transparent 75%);
      "
    />
    <div
      class="pointer-events-none absolute left-[-10%] top-[-15%] z-0 h-[45%] w-[45%] rounded-full bg-primary/20 blur-[120px] [animation:aurora-a_14s_ease-in-out_infinite_alternate]"
    />
    <div
      class="pointer-events-none absolute bottom-[-15%] right-[-10%] z-0 h-[45%] w-[45%] rounded-full bg-chart-2/15 blur-[120px] [animation:aurora-b_18s_ease-in-out_infinite_alternate]"
    />

    <header v-reveal="'down'" class="relative z-20">
      <div
        class="mx-auto flex h-20 w-full max-w-7xl items-center justify-between px-6 lg:px-10"
      >
        <Link href="/" class="group flex items-center gap-3">
          <div
            class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-primary to-chart-4 shadow-lg shadow-primary/30 transition-transform duration-500 group-hover:rotate-[-12deg] group-hover:scale-110"
          >
            <span class="font-serif text-xl italic text-primary-foreground">fx</span>
          </div>
          <div class="text-lg font-semibold text-foreground">
            MathDash<span class="text-primary">.</span>
          </div>
        </Link>

        <div class="flex items-center gap-3">
          <Link
            v-if="canLogin"
            :href="route('login')"
            class="hidden text-sm font-medium text-muted-foreground transition hover:text-foreground sm:inline"
          >
            {{ __("Log In") }}
          </Link>
          <ThemeSwitcher />
        </div>
      </div>
    </header>

    <main
      class="relative z-10 mx-auto flex min-h-[calc(100svh-5rem)] max-w-7xl flex-col justify-center px-6 py-8 lg:px-10"
    >
      <div class="grid w-full items-center gap-12 lg:grid-cols-[1.05fr_1fr]">
        <div>
          <div
            v-reveal="'blur'"
            class="glow-border mb-6 inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wider text-primary"
            style="--glow-fill: color-mix(in srgb, var(--primary) 10%, var(--background))"
          >
            <Sparkles :size="14" class="spin-slow" />
            {{ __("Adaptive Learning Workspace") }}
          </div>

          <h1
            class="text-4xl font-extrabold leading-[1.08] tracking-tight text-foreground md:text-6xl"
          >
            <SplitText :text="__('Smarter math practice for every student')" :delay="100" />
            <span
              class="split-word text-shine"
              :style="{ '--word-delay': '650ms' }"
              >MathDash</span
            >
          </h1>

          <p
            v-reveal="{ variant: 'up', delay: 750 }"
            class="mt-6 max-w-2xl text-lg leading-relaxed text-muted-foreground"
          >
            {{
              __(
                "Explore graphing, geometry, calculators, and guided exercises in one focused workspace built to make learning math feel clear and engaging."
              )
            }}
          </p>

          <div
            v-reveal="{ variant: 'up', delay: 900 }"
            class="mt-8 flex flex-wrap items-center gap-3"
          >
            <Link
              v-if="canRegister"
              v-magnetic="0.18"
              :href="route('register')"
              class="btn-sheen inline-flex items-center gap-2 rounded-xl px-6 py-3 text-sm font-semibold text-primary-foreground"
            >
              {{ __("Get Started") }}
              <ArrowRight :size="16" class="btn-arrow" />
            </Link>

            <Link
              v-if="canLogin"
              v-magnetic="0.18"
              :href="route('login')"
              class="rounded-xl border border-border bg-card/80 px-6 py-3 text-sm font-medium text-foreground backdrop-blur transition-colors hover:border-primary/50 hover:text-primary"
            >
              {{ __("Log In") }}
            </Link>
          </div>
        </div>

        <div class="space-y-4">
          <div
            v-reveal="{ variant: 'scale', delay: 300 }"
            v-tilt="5"
            class="rounded-2xl border border-border/80 bg-card/80 p-4 shadow-2xl shadow-primary/10 backdrop-blur"
          >
            <div class="mb-3 flex items-center gap-2 px-1">
              <span class="h-2.5 w-2.5 rounded-full bg-destructive/70" />
              <span class="h-2.5 w-2.5 rounded-full bg-rank-first/80" />
              <span class="h-2.5 w-2.5 rounded-full bg-chart-2/80" />
              <span class="ml-auto inline-flex items-center gap-2 text-xs text-muted-foreground">
                <span class="pulse-dot" />
                {{ __("Interactive visual tools") }}
              </span>
            </div>
            <GraphSketch />
          </div>

          <div class="grid gap-4 sm:grid-cols-3">
            <article
              v-for="(feature, index) in features"
              :key="feature.title"
              v-reveal="{ variant: 'up', delay: 500 + index * 120 }"
              v-tilt
              class="rounded-2xl border border-border/80 bg-card/80 p-4 shadow-lg backdrop-blur"
            >
              <div
                class="motion-pop mb-3 flex h-10 w-10 items-center justify-center rounded-lg"
                :class="feature.tone"
              >
                <component :is="feature.icon" :size="18" />
              </div>
              <h3 class="text-sm font-semibold text-foreground">
                {{ feature.title }}
              </h3>
              <p class="mt-2 text-xs leading-relaxed text-muted-foreground lg:hidden xl:block">
                {{ feature.body }}
              </p>
            </article>
          </div>
        </div>
      </div>

      <div v-reveal="{ variant: 'up', delay: 1000 }" class="mt-14 space-y-3">
        <FormulaMarquee :speed="45" />
        <FormulaMarquee :speed="55" reverse />
      </div>
    </main>
  </section>
</template>
