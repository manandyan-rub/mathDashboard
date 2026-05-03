<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from "vue";
import {
  Activity,
  Box,
  Calculator,
  ChartLine,
  Layers,
  Lock,
  Sparkles,
} from "lucide-vue-next";
import { cmsTranslate as __ } from "@/lib/i18n";
import { adminMenu } from "@/data/adminMenu";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import AdminSidebarItem from "@/Layouts/AdminSidebarItem.vue";

const mounted = ref(false);

const moduleItems = [
  { icon: ChartLine, labelKey: "2D Graphing" },
  { icon: Box, labelKey: "3D Geometry" },
  { icon: Calculator, labelKey: "Algebra Engine" },
  { icon: Activity, labelKey: "Calculus Steps" },
  { icon: Layers, labelKey: "Matrices & Vectors" },
];

let animationTimer: ReturnType<typeof setTimeout> | null = null;

onMounted(() => {
  animationTimer = setTimeout(() => {
    mounted.value = true;
  }, 20);
});

onBeforeUnmount(() => {
  if (animationTimer) clearTimeout(animationTimer);
});
</script>

<template>
  <AdminLayout>
    <template #sidebar>
      <AdminSidebarItem
        v-for="item in adminMenu"
        :key="item.title + (item.route ?? '')"
        :item="item"
      />
    </template>

    <template #header>
      <h1 class="text-lg font-semibold text-foreground dark:text-foreground">
        {{ __("Calculators") }}
      </h1>
    </template>

    <div class="-m-6">
      <section
        class="relative min-h-[calc(100vh-8.25rem)] overflow-hidden bg-background text-muted-foreground selection:bg-primary/20 dark:text-muted-foreground dark:selection:bg-primary/30"
      >
        <div
          class="pointer-events-none absolute inset-0 z-0 opacity-[0.03]"
          style="
            background-image:
              linear-gradient(to right, var(--border) 1px, transparent 1px),
              linear-gradient(to bottom, var(--border) 1px, transparent 1px);
            background-size: 40px 40px;
          "
        />

        <div
          class="pointer-events-none absolute left-[-10%] top-[-10%] z-0 h-[40%] w-[40%] rounded-full bg-primary/20 blur-[120px]"
        />
        <div
          class="pointer-events-none absolute bottom-[-10%] right-[-10%] z-0 h-[40%] w-[40%] rounded-full bg-chart-2/10 blur-[120px]"
        />

        <div
          class="relative z-10 mx-auto flex w-full max-w-5xl flex-col p-6 lg:p-12"
        >
          <div
            class="transition-all duration-1000"
            :class="
              mounted ? 'translate-y-0 opacity-100' : 'translate-y-10 opacity-0'
            "
          >
            <div
              class="mb-6 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-primary dark:text-primary"
            >
              <Sparkles :size="14" />
              {{ __("Adaptive Learning Workspace") }}
            </div>

            <h2
              class="mb-6 text-4xl font-bold leading-tight tracking-tight text-foreground dark:text-primary-foreground md:text-6xl"
            >
              {{ __("Smarter math practice for every student") }}
            </h2>

            <p
              class="mb-10 max-w-2xl text-lg leading-relaxed text-muted-foreground dark:text-muted-foreground"
            >
              {{
                __(
                  "Explore graphing, geometry, calculators, and guided exercises in one focused workspace built to make learning math feel clear and engaging.",
                )
              }}
            </p>
          </div>

          <div
            class="mt-16 grid grid-cols-1 gap-6 transition-all delay-300 duration-1000 md:grid-cols-3"
            :class="
              mounted ? 'translate-y-0 opacity-100' : 'translate-y-10 opacity-0'
            "
          >
            <div
              class="group relative col-span-1 overflow-hidden rounded-2xl border border-border bg-card/90 p-6 shadow-xl shadow-border/60 backdrop-blur-sm dark:border-border dark:bg-card/40 dark:shadow-none md:col-span-2"
            >
              <div
                class="absolute right-4 top-4 flex items-center gap-1 rounded border border-border bg-muted px-2 py-1 font-mono text-[10px] text-muted-foreground dark:border-border dark:bg-card dark:text-muted-foreground"
              >
                <Lock :size="10" /> {{ __("Coming Soon") }}
              </div>

              <h3
                class="mb-2 flex items-center gap-2 text-xl font-semibold text-foreground dark:text-primary-foreground"
              >
                <Box class="text-primary" /> {{ __("3D Geometry") }}
              </h3>

              <p
                class="mb-8 max-w-sm text-sm text-muted-foreground dark:text-muted-foreground"
              >
                {{
                  __(
                    "Practice with shapes, dimensions, and 3D reasoning through tools designed for hands-on exploration.",
                  )
                }}
              </p>

              <div
                class="absolute -bottom-10 -right-10 h-64 w-64 opacity-60 transition-opacity duration-500 group-hover:opacity-100"
              >
                <div class="cube-3d relative h-full w-full">
                  <div
                    class="cube-face border-primary/50 bg-primary/10"
                  />
                  <div
                    class="cube-face cube-back border-chart-2/50 bg-chart-2/10"
                  />
                  <div
                    class="cube-face cube-right border-chart-4/50 bg-chart-4/10"
                  />
                  <div
                    class="cube-face cube-left border-primary/50 bg-primary/10"
                  />
                  <div
                    class="cube-face cube-top border-chart-5/50 bg-chart-5/10"
                  />
                  <div
                    class="cube-face cube-bottom border-primary/50 bg-primary/10"
                  />

                  <div
                    class="absolute left-0 right-0 top-1/2 h-px bg-destructive"
                    style="transform: translateZ(0)"
                  />
                  <div
                    class="absolute bottom-0 left-1/2 top-0 w-px bg-chart-2"
                    style="transform: translateZ(0)"
                  />
                  <div
                    class="absolute bottom-0 left-1/2 top-0 w-px bg-chart-5"
                    style="transform: rotateX(90deg) translateZ(0)"
                  />
                </div>
              </div>
            </div>

            <div
              class="group relative col-span-1 flex flex-col overflow-hidden rounded-2xl border border-border bg-card/90 p-6 shadow-xl shadow-border/60 backdrop-blur-sm dark:border-border dark:bg-card/40 dark:shadow-none"
            >
              <div
                class="absolute right-4 top-4 flex items-center gap-1 rounded border border-border bg-muted px-2 py-1 font-mono text-[10px] text-muted-foreground dark:border-border dark:bg-card dark:text-muted-foreground"
              >
                <Lock :size="10" /> {{ __("Coming Soon") }}
              </div>

              <h3
                class="mb-2 flex items-center gap-2 text-xl font-semibold text-foreground dark:text-primary-foreground"
              >
                <ChartLine class="text-chart-2" /> {{ __("2D Graphing") }}
              </h3>

              <p class="mb-6 text-sm text-muted-foreground dark:text-muted-foreground">
                {{
                  __(
                    "Work with graphs, calculators, and visual explanations that turn abstract ideas into something easier to understand.",
                  )
                }}
              </p>

              <div
                class="relative mt-auto h-32 w-full border-b border-l border-border dark:border-border"
              >
                <div class="absolute inset-0 flex flex-col justify-between">
                  <div
                    v-for="line in 4"
                    :key="`h-${line}`"
                    class="h-px w-full bg-muted/70 dark:bg-card/50"
                  />
                </div>

                <div class="absolute inset-0 flex justify-between">
                  <div
                    v-for="line in 6"
                    :key="`v-${line}`"
                    class="h-full w-px bg-muted/70 dark:bg-card/50"
                  />
                </div>

                <svg
                  class="absolute inset-0 h-full w-full text-chart-2"
                  preserveAspectRatio="none"
                  viewBox="0 0 100 100"
                >
                  <path
                    d="M 0 50 Q 25 0, 50 50 T 100 50"
                    fill="none"
                    stroke="currentColor"
                    stroke-dasharray="200"
                    stroke-dashoffset="0"
                    stroke-width="3"
                    class="graph-line"
                  />
                </svg>

                <div
                  class="absolute left-1/2 top-1/2 h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-chart-2 bg-background shadow-lg shadow-chart-2/50"
                />
              </div>
            </div>
          </div>

          <div
            class="mt-16 rounded-2xl border border-border bg-card/85 p-6 shadow-lg shadow-border/50 dark:border-border/60 dark:bg-card/30 dark:shadow-none"
          >
            <p
              class="mb-4 text-xs font-semibold uppercase tracking-wider text-muted-foreground"
            >
              {{ __("Modules") }}
            </p>

            <div class="grid gap-3 md:grid-cols-2">
              <div
                v-for="item in moduleItems"
                :key="item.labelKey"
                class="group flex cursor-not-allowed items-center justify-between rounded-lg px-3 py-2.5 text-muted-foreground transition-all hover:bg-muted hover:text-foreground dark:text-muted-foreground dark:hover:bg-card/50 dark:hover:text-primary-foreground"
              >
                <div class="flex items-center gap-3">
                  <component :is="item.icon" :size="18" />
                  <span class="text-sm font-medium">{{ __(item.labelKey) }}</span>
                </div>

                <Lock
                  :size="12"
                  class="text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 dark:text-muted-foreground"
                />
              </div>
            </div>
          </div>

          <footer
            class="mt-16 flex flex-col items-center justify-between border-t border-border/80 pt-8 text-sm text-muted-foreground dark:border-border/60 md:flex-row"
          >
            <p>&copy; 2026 MathDash. {{ __("All rights reserved.") }}</p>

            <div class="mt-4 flex gap-6 md:mt-0">
              <a
                href="#"
                class="transition-colors hover:text-foreground dark:hover:text-primary-foreground"
                >{{ __("About") }}</a
              >
              <a
                href="#"
                class="transition-colors hover:text-foreground dark:hover:text-primary-foreground"
                >{{ __("Careers") }}</a
              >
              <a
                href="#"
                class="transition-colors hover:text-foreground dark:hover:text-primary-foreground"
                >{{ __("Blog") }}</a
              >
            </div>
          </footer>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>

<style scoped>
@keyframes cube-spin {
  to {
    transform: rotateX(-20deg) rotateY(405deg);
  }
}

@keyframes dash {
  to {
    stroke-dashoffset: -400;
  }
}

.cube-3d {
  animation: cube-spin 10s linear infinite;
  transform: rotateX(-20deg) rotateY(45deg);
  transform-style: preserve-3d;
}

.cube-face {
  position: absolute;
  inset: 0;
  border-width: 1px;
  backdrop-filter: blur(2px);
  transform: translateZ(64px);
}

.cube-back {
  transform: rotateY(180deg) translateZ(64px);
}

.cube-right {
  transform: rotateY(90deg) translateZ(64px);
}

.cube-left {
  transform: rotateY(-90deg) translateZ(64px);
}

.cube-top {
  transform: rotateX(90deg) translateZ(64px);
}

.cube-bottom {
  transform: rotateX(-90deg) translateZ(64px);
}

.graph-line {
  animation: dash 3s linear infinite;
}
</style>
