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
import { adminMenu } from "@/data/adminMenu";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import AdminSidebarItem from "@/Layouts/AdminSidebarItem.vue";

const mounted = ref(false);

const moduleItems = [
  { icon: ChartLine, label: "2D գրաֆիկներ" },
  { icon: Box, label: "3D երկրաչափություն" },
  { icon: Calculator, label: "Հանրահաշիվյի շարժիչ" },
  { icon: Activity, label: "Կալկուլուսի քայլեր" },
  { icon: Layers, label: "Մատրիցներ և վեկտորներ" },
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
      <h1 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
        Մաթեմատիկա
      </h1>
    </template>

    <div class="-m-6">
      <section
        class="relative min-h-[calc(100vh-8.25rem)] overflow-hidden bg-background text-slate-800 selection:bg-indigo-500/20 dark:text-slate-300 dark:selection:bg-indigo-500/30"
      >
        <div
          class="pointer-events-none absolute inset-0 z-0 opacity-[0.03]"
          style="
            background-image:
              linear-gradient(to right, #ffffff 1px, transparent 1px),
              linear-gradient(to bottom, #ffffff 1px, transparent 1px);
            background-size: 40px 40px;
          "
        />

        <div
          class="pointer-events-none absolute left-[-10%] top-[-10%] z-0 h-[40%] w-[40%] rounded-full bg-indigo-600/20 blur-[120px]"
        />
        <div
          class="pointer-events-none absolute bottom-[-10%] right-[-10%] z-0 h-[40%] w-[40%] rounded-full bg-emerald-600/10 blur-[120px]"
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
              class="mb-6 inline-flex items-center gap-2 rounded-full border border-indigo-500/20 bg-indigo-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-indigo-500 dark:text-indigo-400"
            >
              <Sparkles :size="14" />
              Նախագծի հայտարարություն
            </div>

            <h2
              class="mb-6 text-4xl font-bold leading-tight tracking-tight text-slate-950 dark:text-white md:text-6xl"
            >
              Վերջնական աշխատանքային միջավայր տեսողական մաթեմատիկայի համար։
            </h2>

            <p
              class="mb-10 max-w-2xl text-lg leading-relaxed text-slate-600 dark:text-slate-400"
            >
              Մենք կառուցում ենք ինտերակտիվ հարթակ, որտեղ ուսանողները կարող են
              լուծել, պատկերացնել և խորությամբ հասկանալ բարդ մաթեմատիկական
              խնդիրներ՝ 2D ֆունկցիաներից մինչև 3D երկրաչափություն։
            </p>
          </div>

          <div
            class="mt-16 grid grid-cols-1 gap-6 transition-all delay-300 duration-1000 md:grid-cols-3"
            :class="
              mounted ? 'translate-y-0 opacity-100' : 'translate-y-10 opacity-0'
            "
          >
            <div
              class="group relative col-span-1 overflow-hidden rounded-2xl border border-slate-200 bg-white/90 p-6 shadow-xl shadow-slate-200/60 backdrop-blur-sm dark:border-slate-800 dark:bg-slate-900/40 dark:shadow-none md:col-span-2"
            >
              <div
                class="absolute right-4 top-4 flex items-center gap-1 rounded border border-slate-300 bg-slate-100 px-2 py-1 font-mono text-[10px] text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400"
              >
                <Lock :size="10" /> ՆԱԽԱԴԻՏՈՒՄ
              </div>

              <h3
                class="mb-2 flex items-center gap-2 text-xl font-semibold text-slate-900 dark:text-white"
              >
                <Box class="text-indigo-400" /> 3D տարածական շարժիչ
              </h3>

              <p
                class="mb-8 max-w-sm text-sm text-slate-600 dark:text-slate-400"
              >
                Պտտեք, մոտեցրեք և ուսումնասիրեք երկրաչափական մարմիններն ու
                բազմափոփոխական կալկուլուսի գաղափարները իրական ժամանակում։
              </p>

              <div
                class="absolute -bottom-10 -right-10 h-64 w-64 opacity-60 transition-opacity duration-500 group-hover:opacity-100"
              >
                <div class="cube-3d relative h-full w-full">
                  <div
                    class="cube-face border-indigo-500/50 bg-indigo-500/10"
                  />
                  <div
                    class="cube-face cube-back border-emerald-500/50 bg-emerald-500/10"
                  />
                  <div
                    class="cube-face cube-right border-purple-500/50 bg-purple-500/10"
                  />
                  <div
                    class="cube-face cube-left border-indigo-500/50 bg-indigo-500/10"
                  />
                  <div
                    class="cube-face cube-top border-blue-500/50 bg-blue-500/10"
                  />
                  <div
                    class="cube-face cube-bottom border-indigo-500/50 bg-indigo-500/10"
                  />

                  <div
                    class="absolute left-0 right-0 top-1/2 h-px bg-red-500"
                    style="transform: translateZ(0)"
                  />
                  <div
                    class="absolute bottom-0 left-1/2 top-0 w-px bg-green-500"
                    style="transform: translateZ(0)"
                  />
                  <div
                    class="absolute bottom-0 left-1/2 top-0 w-px bg-blue-500"
                    style="transform: rotateX(90deg) translateZ(0)"
                  />
                </div>
              </div>
            </div>

            <div
              class="group relative col-span-1 flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white/90 p-6 shadow-xl shadow-slate-200/60 backdrop-blur-sm dark:border-slate-800 dark:bg-slate-900/40 dark:shadow-none"
            >
              <div
                class="absolute right-4 top-4 flex items-center gap-1 rounded border border-slate-300 bg-slate-100 px-2 py-1 font-mono text-[10px] text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400"
              >
                <Lock :size="10" /> ՆԱԽԱԴԻՏՈՒՄ
              </div>

              <h3
                class="mb-2 flex items-center gap-2 text-xl font-semibold text-slate-900 dark:text-white"
              >
                <ChartLine class="text-emerald-400" /> 2D գրաֆիկներ
              </h3>

              <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">
                Կառուցեք ֆունկցիաների գրաֆիկները, գտեք հատման կետերը և վերլուծեք
                կորի վարքը քայլ առ քայլ։
              </p>

              <div
                class="relative mt-auto h-32 w-full border-b border-l border-slate-300 dark:border-slate-700"
              >
                <div class="absolute inset-0 flex flex-col justify-between">
                  <div
                    v-for="line in 4"
                    :key="`h-${line}`"
                    class="h-px w-full bg-slate-300/70 dark:bg-slate-800/50"
                  />
                </div>

                <div class="absolute inset-0 flex justify-between">
                  <div
                    v-for="line in 6"
                    :key="`v-${line}`"
                    class="h-full w-px bg-slate-300/70 dark:bg-slate-800/50"
                  />
                </div>

                <svg
                  class="absolute inset-0 h-full w-full text-emerald-400"
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
                  class="absolute left-1/2 top-1/2 h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-emerald-400 bg-background shadow-[0_0_10px_rgba(52,211,153,0.5)]"
                />
              </div>
            </div>
          </div>

          <div
            class="mt-16 rounded-2xl border border-slate-200 bg-white/85 p-6 shadow-lg shadow-slate-200/50 dark:border-slate-800/60 dark:bg-slate-900/30 dark:shadow-none"
          >
            <p
              class="mb-4 text-xs font-semibold uppercase tracking-wider text-slate-500"
            >
              Մոդուլներ
            </p>

            <div class="grid gap-3 md:grid-cols-2">
              <div
                v-for="item in moduleItems"
                :key="item.label"
                class="group flex cursor-not-allowed items-center justify-between rounded-lg px-3 py-2.5 text-slate-500 transition-all hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800/50 dark:hover:text-white"
              >
                <div class="flex items-center gap-3">
                  <component :is="item.icon" :size="18" />
                  <span class="text-sm font-medium">{{ item.label }}</span>
                </div>

                <Lock
                  :size="12"
                  class="text-slate-400 opacity-0 transition-opacity group-hover:opacity-100 dark:text-slate-500"
                />
              </div>
            </div>
          </div>

          <footer
            class="mt-16 flex flex-col items-center justify-between border-t border-slate-200/80 pt-8 text-sm text-slate-500 dark:border-slate-800/60 md:flex-row"
          >
            <p>
              &copy; 2026 MathDash հարթակ։ Բոլոր իրավունքները պաշտպանված են։
            </p>

            <div class="mt-4 flex gap-6 md:mt-0">
              <a
                href="#"
                class="transition-colors hover:text-slate-900 dark:hover:text-white"
                >Գաղտնիություն</a
              >
              <a
                href="#"
                class="transition-colors hover:text-slate-900 dark:hover:text-white"
                >Պայմաններ</a
              >
              <a
                href="#"
                class="transition-colors hover:text-slate-900 dark:hover:text-white"
                >Կապ</a
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
