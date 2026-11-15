<script setup lang="ts">
import AnimatedBackground from "@/components/AnimatedBackground.vue";
import SplitText from "@/components/motion/SplitText.vue";
import ThemeSwitcher from "@/components/ThemeSwitcher.vue";
import { Link } from "@inertiajs/vue3";
import { Sparkles } from "lucide-vue-next";

/**
 * Shared shell for auth pages.
 * - Without `heading`: a centered animated card (forgot/reset/verify/confirm).
 * - With `heading`: a split hero + card layout (register), matching Login.
 * - `shake`: flip to true for one frame to shake the card (e.g. on errors).
 */
withDefaults(
  defineProps<{
    heading?: string;
    highlight?: string;
    headingAfter?: string;
    tagline?: string;
    badge?: string;
    shake?: boolean;
  }>(),
  {
    heading: "",
    highlight: "",
    headingAfter: "",
    tagline: "",
    badge: "MathDash",
    shake: false,
  },
);
</script>

<template>
  <section
    v-spotlight
    class="relative min-h-screen overflow-hidden bg-background text-muted-foreground selection:bg-primary/20 dark:selection:bg-primary/30"
  >
    <AnimatedBackground />

    <div
      class="pointer-events-none absolute inset-0 z-0 opacity-[0.04]"
      style="
        background-image:
          linear-gradient(to right, var(--border) 1px, transparent 1px),
          linear-gradient(to bottom, var(--border) 1px, transparent 1px);
        background-size: 40px 40px;
        mask-image: radial-gradient(ellipse at center, #000 30%, transparent 75%);
      "
    />
    <div
      class="pointer-events-none absolute left-[-10%] top-[-10%] z-0 h-[40%] w-[40%] rounded-full bg-primary/20 blur-[120px] [animation:aurora-a_14s_ease-in-out_infinite_alternate]"
    />
    <div
      class="pointer-events-none absolute bottom-[-10%] right-[-10%] z-0 h-[40%] w-[40%] rounded-full bg-chart-2/15 blur-[120px] [animation:aurora-b_18s_ease-in-out_infinite_alternate]"
    />

    <header
      v-reveal="'down'"
      class="relative z-20 mx-auto flex h-20 w-full max-w-6xl items-center justify-between px-6 lg:px-12"
    >
      <Link href="/" class="group flex items-center gap-3">
        <div
          class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-primary to-chart-4 shadow-lg shadow-primary/30 transition-transform duration-500 group-hover:rotate-[-12deg] group-hover:scale-110"
        >
          <span class="font-serif text-xl italic text-primary-foreground">fx</span>
        </div>
        <span class="text-lg font-semibold text-foreground">
          MathDash<span class="text-primary">.</span>
        </span>
      </Link>
      <ThemeSwitcher />
    </header>

    <div
      class="relative z-10 mx-auto flex min-h-[calc(100svh-5rem)] max-w-6xl items-center px-6 pb-10 lg:px-12"
    >
      <div
        class="grid w-full gap-10"
        :class="heading ? 'lg:grid-cols-2' : 'justify-items-center'"
      >
        <div v-if="heading" class="flex flex-col justify-center">
          <div
            v-reveal="'blur'"
            class="glow-border mb-6 inline-flex w-fit items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wider text-primary"
            style="--glow-fill: color-mix(in srgb, var(--primary) 10%, var(--background))"
          >
            <Sparkles :size="14" class="spin-slow" />
            {{ badge }}
          </div>

          <h1
            class="mb-6 text-4xl font-extrabold leading-tight tracking-tight text-foreground md:text-6xl"
          >
            <SplitText :text="heading" :delay="100" />
            <span
              v-if="highlight"
              class="split-word text-shine"
              :style="{ '--word-delay': '380ms' }"
              >{{ highlight }}</span
            >
            <SplitText v-if="headingAfter" :text="headingAfter" :delay="480" />
          </h1>

          <p
            v-if="tagline"
            v-reveal="{ variant: 'up', delay: 600 }"
            class="max-w-xl text-lg leading-relaxed text-muted-foreground"
          >
            {{ tagline }}
          </p>

          <div v-reveal="{ variant: 'up', delay: 700 }" class="mt-6">
            <slot name="aside" />
          </div>
        </div>

        <div
          v-reveal="{ variant: 'scale', delay: heading ? 250 : 80 }"
          class="flex w-full items-center justify-center"
        >
          <div
            class="auth-card glow-border w-full max-w-md rounded-2xl p-6 text-card-foreground shadow-2xl shadow-primary/15 sm:p-8"
            :class="{ shake }"
          >
            <slot />
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
