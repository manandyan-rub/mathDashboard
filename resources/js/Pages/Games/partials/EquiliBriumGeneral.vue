<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { useEquilibriumEngine } from "../helpers/useEquilibriumEngine";

const {
  gameState,
  score,
  level,
  mode,
  modeLabel,
  modeColor,
  timeLeft,
  maxTime,
  leftItems,
  rightItems,
  hand,
  leftSum,
  rightSum,
  scaleClass,
  canBalance,
  MODE_CONFIG,
  start,
  addToScale,
  removeFromScale,
  resetCurrent,
  balance,
} = useEquilibriumEngine();
</script>

<template>
  <Head title="Հավասարակշռություն" />

  <div class="relative z-10 flex h-full w-full flex-col overflow-hidden">
    <div
      class="relative flex flex-1 flex-col overflow-hidden rounded-[2rem] border border-slate-200 bg-gradient-to-br from-zinc-100 via-white to-zinc-100 text-zinc-900 shadow-2xl shadow-zinc-300/60 dark:border-zinc-800 dark:from-zinc-950 dark:via-zinc-900 dark:to-zinc-950 dark:text-zinc-100 dark:shadow-zinc-950/70"
    >
      <div class="flex items-end justify-between gap-3 px-5 pb-4 pt-5 sm:px-8">
        <div class="min-w-0">
          <h1
            class="max-w-full text-2xl font-black leading-tight tracking-tight text-emerald-600 [overflow-wrap:anywhere] dark:text-emerald-400 sm:text-3xl md:text-4xl"
          >
            ՀԱՎԱՍԱՐԱԿՇՌՈՒԹՅՈՒՆ
          </h1>
          <div class="mt-2 flex flex-wrap gap-2">
            <span
              class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-white"
              :class="modeColor"
            >
              {{ modeLabel }}
            </span>
            <span
              class="rounded-full bg-zinc-200 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"
            >
              Մակարդակ {{ level }}
            </span>
          </div>
        </div>

        <div class="shrink-0 text-right">
          <span
            class="text-[11px] font-bold uppercase tracking-wider text-zinc-500"
          >
            Կայունության միավոր
          </span>
          <div class="text-4xl font-black leading-none text-emerald-500">
            {{ score }}
          </div>
        </div>
      </div>

      <div class="px-5 pb-2 sm:px-8">
        <div
          class="h-2 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800"
        >
          <div
            class="h-full bg-emerald-500 transition-all duration-100"
            :style="{ width: `${(timeLeft / maxTime) * 100}%` }"
          />
        </div>
      </div>

      <div class="flex flex-1 flex-col justify-center px-5 pb-4 pt-2 sm:px-8">
        <div
          class="flex w-full items-center justify-between gap-3 transition-transform duration-500 ease-out sm:gap-4"
          :class="scaleClass"
        >
          <div
            class="relative flex min-h-[230px] flex-1 flex-col items-center justify-center rounded-[2.2rem] border-4 bg-white p-5 shadow-xl transition-colors dark:bg-zinc-900"
            :class="
              canBalance
                ? 'border-emerald-500 shadow-emerald-500/20'
                : 'border-zinc-200 dark:border-zinc-800'
            "
          >
            <span
              class="absolute -top-3 rounded-full bg-zinc-200 px-4 py-1 text-[10px] font-black uppercase tracking-widest text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"
            >
              Կողմ A
            </span>

            <div
              class="text-6xl font-black"
              :class="canBalance ? 'text-emerald-500' : 'text-zinc-400'"
            >
              {{ leftSum }}
            </div>

            <div class="mt-4 flex max-w-full flex-wrap justify-center gap-2">
              <button
                v-for="(item, idx) in leftItems"
                :key="item.id"
                type="button"
                class="added-item rounded-lg bg-zinc-100 px-2.5 py-1 text-sm font-black dark:bg-zinc-800"
                title="Հպեք՝ հեռացնելու համար"
                @click="removeFromScale(idx)"
              >
                +{{ item.val }}
              </button>
            </div>

            <div
              v-if="leftItems.length > 0"
              class="mt-2 text-[9px] font-bold uppercase tracking-wider text-zinc-400"
            >
              Հպեք թվին՝ հեռացնելու համար
            </div>
          </div>

          <div class="flex flex-col items-center text-zinc-400">
            <span class="mb-2 text-4xl">⚖️</span>
            <div class="h-12 w-1 rounded-full bg-zinc-400" />
          </div>

          <div
            class="relative flex min-h-[230px] flex-1 flex-col items-center justify-center rounded-[2.2rem] border-4 border-zinc-200 bg-white p-5 shadow-xl dark:border-zinc-800 dark:bg-zinc-900"
          >
            <span
              class="absolute -top-3 rounded-full bg-zinc-200 px-4 py-1 text-[10px] font-black uppercase tracking-widest text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"
            >
              Կողմ B
            </span>

            <div class="text-6xl font-black text-blue-500">
              <span
                v-if="mode === 'einstein' && gameState === 'playing'"
                class="animate-pulse"
              >
                ?
              </span>
              <span v-else>{{ rightSum }}</span>
            </div>

            <div
              v-if="mode === 'einstein'"
              class="mt-2 text-[10px] font-bold uppercase tracking-wider text-zinc-500"
            >
              Գումարեք ներքևի թվերը
            </div>

            <div class="mt-4 flex flex-wrap justify-center gap-1.5 opacity-90">
              <span
                v-for="(n, idx) in rightItems"
                :key="`r-${idx}`"
                class="rounded-lg bg-blue-50 px-2.5 py-1 text-sm font-bold text-blue-600 dark:bg-blue-900/30 dark:text-blue-400"
              >
                {{ n }}
              </span>
            </div>
          </div>
        </div>

        <div class="mt-10 h-16 text-center">
          <button
            v-if="canBalance"
            type="button"
            class="rounded-full bg-emerald-500 px-10 py-3 text-xl font-black text-white shadow-lg shadow-emerald-500/40 transition hover:scale-105 active:scale-95 sm:px-16 sm:py-4 sm:text-2xl"
            @click="balance"
          >
            ՀԱՎԱՍԱՐԵՑՆԵԼ
          </button>
        </div>
      </div>

      <div
        v-if="gameState === 'playing'"
        class="border-t border-zinc-200 p-5 dark:border-zinc-800 sm:p-8"
      >
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <button
            v-for="item in hand"
            :key="item.id"
            type="button"
            class="h-16 rounded-2xl border-b-4 border-zinc-200 bg-white text-2xl font-black shadow-sm transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-25 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:bg-zinc-800"
            :disabled="item.used"
            @click="addToScale(item)"
          >
            {{ item.val }}
          </button>

          <button
            type="button"
            class="col-span-2 mt-1 h-14 rounded-2xl bg-zinc-200 text-xs font-black uppercase tracking-[0.2em] text-zinc-500 transition hover:bg-red-100 hover:text-red-600 dark:bg-zinc-800 dark:hover:bg-red-900/30 dark:hover:text-red-300 sm:col-span-4"
            @click="resetCurrent"
          >
            Մաքրել բոլորը
          </button>
        </div>
      </div>
    </div>

    <Transition name="overlay">
      <div
        v-if="gameState !== 'playing'"
        class="absolute inset-0 z-50 flex items-center justify-center bg-white/75 p-6 backdrop-blur-md dark:bg-black/70"
      >
        <div
          class="w-full max-w-lg rounded-3xl border border-zinc-200 bg-white p-6 text-center shadow-2xl dark:border-zinc-700 dark:bg-zinc-900 sm:p-8"
        >
          <div v-if="gameState === 'idle'">
            <div class="mb-5 text-6xl">🧠</div>
            <p
              class="mb-8 text-sm font-medium text-zinc-500 dark:text-zinc-400 sm:text-base"
            >
              Հավասարակշռեք կշեռքը։ Էյնշտեյն ռեժիմում աջ կողմի ընդհանուր թիվը
              թաքնված է, պետք է հաշվեք ներքևի բաղադրիչներով։
            </p>
          </div>

          <div v-if="gameState === 'ended'" class="mb-8">
            <h2 class="mb-2 text-5xl font-black text-red-500 sm:text-6xl">
              ՊԱՐՏՎԵՑԻՔ
            </h2>
            <p class="mb-2 font-bold text-zinc-500 dark:text-zinc-400">
              Կողմ B-ի գումարը՝ {{ rightSum }}
            </p>
            <p class="text-lg font-medium">
              Վերջնական միավոր:
              <span class="font-black text-emerald-500">{{ score }}</span>
            </p>
          </div>

          <div class="grid gap-3 text-left">
            <button
              type="button"
              class="flex w-full items-center justify-between rounded-3xl border-2 border-transparent bg-white px-6 py-4 text-lg font-black shadow transition hover:border-emerald-500 dark:bg-zinc-800"
              @click="start('scholastic')"
            >
              <span>{{ MODE_CONFIG.scholastic.label }}</span>
              <span class="text-xs uppercase tracking-widest text-zinc-400">
                Սկիզբ
              </span>
            </button>

            <button
              type="button"
              class="flex w-full items-center justify-between rounded-3xl border-2 border-transparent bg-white px-6 py-4 text-lg font-black shadow transition hover:border-blue-500 dark:bg-zinc-800"
              @click="start('student')"
            >
              <span>{{ MODE_CONFIG.student.label }}</span>
              <span class="text-xs uppercase tracking-widest text-zinc-400">
                Ստանդարտ
              </span>
            </button>

            <button
              type="button"
              class="flex w-full items-center justify-between rounded-3xl border-2 border-transparent bg-zinc-900 px-6 py-4 text-lg font-black text-white shadow transition hover:border-red-500 dark:bg-white dark:text-black"
              @click="start('einstein')"
            >
              <span>{{ MODE_CONFIG.einstein.label }}</span>
              <span class="text-xs uppercase tracking-widest text-zinc-400">
                Թաքնված X
              </span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.scale-tilt-left {
  transform: rotate(-6deg);
}

.scale-tilt-right {
  transform: rotate(6deg);
}

.scale-perfect {
  transform: rotate(0deg);
}

.added-item {
  transition:
    transform 0.2s,
    background-color 0.2s,
    color 0.2s;
}

.added-item:hover {
  transform: scale(1.08);
  background-color: #fee2e2;
  color: #dc2626;
}

.dark .added-item:hover {
  background-color: #7f1d1d;
  color: #fca5a5;
}

.overlay-enter-active,
.overlay-leave-active {
  transition: opacity 0.2s ease;
}

.overlay-enter-from,
.overlay-leave-to {
  opacity: 0;
}
</style>
