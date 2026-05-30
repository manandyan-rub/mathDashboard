<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import type { Mode } from "@/types/game";
import { cmsTranslate as __ } from "@/lib/i18n";
import { useGameEngine } from "../helpers/usageGameEngine";

const {
  gameState,
  mode,
  score,
  highScore,
  streak,
  bestStreak,
  timeLimit,
  bubbles,
  problem,
  feedback,
  cfg,
  timerPct,
  timerColor,
  isEinstein,
  timerDisplay,
  startGame,
  handleBubble,
  MODES,
} = useGameEngine();
</script>

<template>
  <Head :title="__('MathQuest')" />

  <div
    class="z-10 flex h-full flex-col overflow-hidden bg-muted text-foreground transition-colors duration-200 dark:bg-card dark:text-primary-foreground"
  >
    <header
      class="z-50 flex shrink-0 items-center justify-between border-b border-border bg-card px-5 py-3 dark:border-border dark:bg-card"
    >
      <div class="flex items-center gap-3">
        <div>
          <h1
            class="text-lg font-bold leading-none tracking-tight"
            :class="isEinstein ? 'text-destructive' : 'text-chart-5'"
          >
            {{ __("MathQuest") }}
            <span class="text-[10px] font-normal italic opacity-40">v3.0</span>
          </h1>
          <div class="mt-0.5 flex items-center gap-2">
            <span
              class="rounded px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-primary-foreground"
              :class="
                isEinstein
                  ? 'bg-destructive'
                  : mode === 'student'
                    ? 'bg-primary'
                    : 'bg-chart-2'
              "
            >
              {{ __(MODES[mode].label) }}
            </span>
            <span class="text-[10px] font-medium text-muted-foreground">
              {{ __("Time") }}: {{ timeLimit.toFixed(1) }}s
            </span>
          </div>
        </div>
      </div>

      <div class="text-center">
        <div class="text-2xl font-bold leading-none text-chart-3">
          {{ score }}
        </div>
        <div class="mt-0.5 text-[10px] uppercase tracking-widest text-muted-foreground">
          {{ __("Score") }}
        </div>
      </div>

      <div class="text-right">
        <div class="text-sm font-semibold">
          {{ __("High score") }}: {{ highScore }}
        </div>
        <div class="text-[10px] text-muted-foreground">
          {{ __("Streak") }}: {{ streak }}
        </div>
      </div>
    </header>

    <div class="h-1.5 w-full shrink-0 bg-muted dark:bg-background">
      <div
        class="h-full transition-all duration-[50ms]"
        :style="{ width: timerPct + '%', background: timerColor }"
      />
    </div>

    <div id="arena" class="relative flex-1 overflow-hidden">
      <Transition name="overlay">
        <div
          v-if="gameState === 'idle'"
          class="absolute inset-0 z-40 flex items-center justify-center bg-card/95 p-6 dark:bg-background/95"
        >
          <div class="w-full max-w-md">
            <h2 class="mb-6 text-center text-2xl font-bold">
              {{ __("Choose a mode") }}
            </h2>
            <div class="flex flex-col gap-3">
              <button
                v-for="(conf, m) in MODES"
                :key="m"
                class="group flex items-center justify-between rounded-3xl border-2 p-5 text-left transition-all hover:border-chart-5"
                :class="
                  m === 'einstein'
                    ? 'border-destructive bg-destructive/10 dark:bg-destructive/20'
                    : 'border-transparent bg-muted dark:bg-card'
                "
                type="button"
                @click="startGame(m as Mode)"
              >
                <div class="pr-4">
                  <h3
                    class="text-xl font-black transition-colors group-hover:text-chart-5"
                    :class="m === 'einstein' ? 'text-destructive' : ''"
                  >
                    {{ __(conf.label) }}
                  </h3>
                  <p class="mt-1 text-xs leading-snug text-muted-foreground">
                    {{ __(conf.desc) }}
                  </p>
                </div>
                <span
                  class="text-xs font-black uppercase tracking-widest text-muted-foreground opacity-70 transition-opacity group-hover:opacity-100"
                >
                  {{ __(conf.emoji) }}
                </span>
              </button>
            </div>
          </div>
        </div>
      </Transition>

      <Transition name="overlay">
        <div
          v-if="gameState === 'ended'"
          class="absolute inset-0 z-40 flex items-center justify-center bg-card/95 p-6 dark:bg-background/95"
        >
          <div class="w-full max-w-md text-center">
            <h2 class="mb-3 text-6xl font-black text-destructive">
              {{ __("Game Over") }}
            </h2>
            <p class="mb-6 text-muted-foreground">
              {{ __("Score") }}:
              <span class="text-2xl font-bold text-foreground dark:text-primary-foreground">
                {{ score }}
              </span>
              &nbsp;-&nbsp; {{ __("Best streak") }}:
              <span class="font-bold text-foreground dark:text-primary-foreground">
                {{ bestStreak }}
              </span>
            </p>
            <div
              class="mb-6 rounded-2xl border border-border bg-muted p-6 shadow-inner dark:bg-card"
            >
              <p
                class="mb-3 text-xs font-bold uppercase tracking-widest text-muted-foreground"
              >
                {{ __("Correct answer") }}
              </p>
              <div
                data-mf-answer
                class="flex min-h-[44px] items-center justify-center overflow-x-auto"
              />
              <p class="mt-2 text-xs text-muted-foreground">{{ problem.display }}</p>
            </div>
            <div class="flex flex-col gap-3">
              <button
                class="rounded-2xl bg-chart-5 px-6 py-3 font-bold text-primary-foreground transition hover:bg-chart-5"
                type="button"
                @click="startGame(mode)"
              >
                {{ __("Play again - :mode", { mode: __(MODES[mode].label) }) }}
              </button>
              <button
                class="rounded-2xl border border-border px-6 py-3 text-sm font-medium transition hover:bg-muted dark:hover:bg-card"
                type="button"
                @click="gameState = 'idle'"
              >
                {{ __("Change mode") }}
              </button>
            </div>
          </div>
        </div>
      </Transition>

      <Transition name="question">
        <div
          v-if="gameState === 'playing'"
          class="pointer-events-none absolute left-1/2 top-8 z-30 -translate-x-1/2"
        >
          <div
            class="rounded-[28px] border border-border bg-card/90 px-8 py-4 text-center shadow-2xl backdrop-blur-xl"
            :class="isEinstein ? 'border-destructive/40 shadow-destructive/10' : ''"
          >
            <div
              data-mf-question
              class="flex min-h-[52px] min-w-[180px] max-w-[540px] items-center justify-center overflow-x-auto"
            />
          </div>
        </div>
      </Transition>

      <TransitionGroup name="bubble">
        <button
          v-for="bubble in bubbles"
          :key="bubble.id"
          class="absolute flex select-none items-center justify-center overflow-hidden rounded-full border-4 border-primary-foreground/20 shadow-2xl transition-transform"
          :class="{
            'scale-110 opacity-60': bubble.state === 'correct',
            'animate-shake': bubble.state === 'wrong',
            'hover:scale-110 active:scale-90 cursor-pointer':
              bubble.state === 'idle',
            'shadow-destructive/40': isEinstein,
          }"
          :style="{
            left: bubble.cx - bubble.size / 2 + 'px',
            top: bubble.cy - bubble.size / 2 + 'px',
            width: bubble.size + 'px',
            height: bubble.size + 'px',
            background: bubble.color,
            boxShadow: isEinstein ? `0 0 20px ${bubble.color}66` : undefined,
          }"
          type="button"
          @mousedown="handleBubble(bubble)"
          @touchstart.prevent="handleBubble(bubble)"
        >
          <div
            :data-mf-bubble="bubble.latex"
            class="pointer-events-none flex h-full w-full items-center justify-center"
          />
        </button>
      </TransitionGroup>

      <Transition name="feedback">
        <div
          v-if="feedback"
          class="pointer-events-none absolute bottom-16 left-0 right-0 z-50 text-center"
        >
          <span
            class="text-5xl font-black italic drop-shadow-2xl"
            :class="feedback.good ? 'text-chart-2' : 'text-destructive'"
          >
            {{ feedback.text }}
          </span>
        </div>
      </Transition>
    </div>

    <div
      v-if="gameState === 'playing'"
      class="shrink-0 border-t border-border bg-card py-1.5 text-center text-[11px] text-muted-foreground"
    >
      {{ __("Correct") }} +{{ cfg.bonusSecs }}s {{ __("bonus") }}
      <span v-if="cfg.penaltySecs > 0">
        - {{ __("Wrong") }} -{{ cfg.penaltySecs }}s {{ __("penalty") }}</span
      >
      - {{ __("Streak") }} x{{ streak }} -
      <span :style="{ color: timerColor }" class="font-medium">
        {{ timerDisplay }}
      </span>
    </div>
  </div>
</template>

<style scoped>
.overlay-enter-active,
.overlay-leave-active {
  transition: opacity 0.2s;
}
.overlay-enter-from,
.overlay-leave-to {
  opacity: 0;
}
.question-enter-active {
  transition:
    opacity 0.2s,
    transform 0.2s;
}
.question-enter-from {
  opacity: 0;
  transform: translateX(-50%) scale(0.9);
}
.bubble-enter-active {
  transition:
    opacity 0.2s,
    transform 0.2s;
}
.bubble-leave-active {
  transition:
    opacity 0.15s,
    transform 0.15s;
}
.bubble-enter-from {
  opacity: 0;
  transform: scale(0.3);
}
.bubble-leave-to {
  opacity: 0;
  transform: scale(0.1);
}
.feedback-enter-active {
  transition:
    opacity 0.1s,
    transform 0.1s;
}
.feedback-leave-active {
  transition:
    opacity 0.4s,
    transform 0.4s;
}
.feedback-leave-to {
  opacity: 0;
  transform: translateY(-20px);
}
@keyframes shake {
  0%,
  100% {
    transform: translateX(0);
  }
  20% {
    transform: translateX(-8px);
  }
  40% {
    transform: translateX(8px);
  }
  60% {
    transform: translateX(-5px);
  }
  80% {
    transform: translateX(5px);
  }
}
.animate-shake {
  animation: shake 0.4s ease;
}
</style>

<style>
math-field[read-only] {
  --caret-color: transparent;
  pointer-events: none;
  background: transparent !important;
  border: none !important;
  outline: none !important;
}
math-field[read-only]::part(virtual-keyboard-toggle),
math-field[read-only]::part(menu-toggle) {
  display: none;
}
</style>
