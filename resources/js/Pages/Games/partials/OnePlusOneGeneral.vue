<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import type { Mode } from "@/types/game";
import { useGameEngine } from "../helpers/usageGameEngine";

const {
  gameState,
  mode,
  score,
  highScore,
  streak,
  bestStreak,
  timeLeft,
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
  <Head title="MathQuest" />

  <div
    class="z-10 h-full flex flex-col bg-muted dark:bg-card text-foreground dark:text-primary-foreground transition-colors duration-200 overflow-hidden"
  >
    <header
      class="flex items-center justify-between px-5 py-3 bg-card dark:bg-card border-b border-border dark:border-border z-50 shrink-0"
    >
      <div class="flex items-center gap-3">
        <div>
          <h1
            class="text-lg font-bold leading-none tracking-tight"
            :class="isEinstein ? 'text-destructive' : 'text-chart-5'"
          >
            MathQuest
            <span class="text-[10px] font-normal opacity-40 italic">v3.0</span>
          </h1>
          <div class="flex items-center gap-2 mt-0.5">
            <span
              class="text-[10px] px-2 py-0.5 rounded font-black uppercase tracking-wider text-primary-foreground"
              :class="
                isEinstein
                  ? 'bg-destructive'
                  : mode === 'student'
                    ? 'bg-primary'
                    : 'bg-chart-2'
              "
            >
              {{ MODES[mode].label }}
            </span>
            <span class="text-[10px] text-muted-foreground font-medium"
              >Ժամ: {{ timeLimit.toFixed(1) }}վ</span
            >
          </div>
        </div>
      </div>

      <div class="text-center">
        <div
          class="text-2xl font-bold text-chart-3 dark:text-chart-3 leading-none"
        >
          {{ score }}
        </div>
        <div
          class="text-[10px] uppercase tracking-widest text-muted-foreground mt-0.5"
        >
          Հաշիվ
        </div>
      </div>

      <div class="text-right">
        <div class="text-sm font-semibold">Լավագույն: {{ highScore }}</div>
        <div class="text-[10px] text-muted-foreground">Շարք: {{ streak }}</div>
      </div>
    </header>

    <div class="w-full h-1.5 bg-muted dark:bg-background shrink-0">
      <div
        class="h-full transition-all duration-[50ms]"
        :style="{ width: timerPct + '%', background: timerColor }"
      />
    </div>

    <div id="arena" class="flex-1 relative overflow-hidden">
      <Transition name="overlay">
        <div
          v-if="gameState === 'idle'"
          class="absolute inset-0 z-40 bg-card/95 dark:bg-background/95 flex items-center justify-center p-6"
        >
          <div class="w-full max-w-md">
            <h2 class="text-2xl font-bold text-center mb-6">
              Ընտրեք դժվարությունը
            </h2>
            <div class="flex flex-col gap-3">
              <button
                v-for="(conf, m) in MODES"
                :key="m"
                class="flex items-center justify-between p-5 border-2 rounded-3xl transition-all text-left group hover:border-chart-5"
                :class="
                  m === 'einstein'
                    ? 'border-destructive bg-destructive dark:bg-destructive/20'
                    : 'border-transparent bg-muted dark:bg-card'
                "
                @click="startGame(m as Mode)"
              >
                <div class="pr-4">
                  <h3
                    class="font-black text-xl group-hover:text-chart-5 transition-colors"
                    :class="m === 'einstein' ? 'text-destructive' : ''"
                  >
                    {{ conf.label }}
                  </h3>
                  <p
                    class="text-xs text-muted-foreground dark:text-muted-foreground mt-1 leading-snug"
                  >
                    {{ conf.desc }}
                  </p>
                </div>
                <span
                  class="text-4xl opacity-50 group-hover:opacity-100 transition-opacity"
                  >{{ conf.emoji }}</span
                >
              </button>
            </div>
          </div>
        </div>
      </Transition>

      <Transition name="overlay">
        <div
          v-if="gameState === 'ended'"
          class="absolute inset-0 z-40 bg-card/95 dark:bg-background/95 flex items-center justify-center p-6"
        >
          <div class="w-full max-w-md text-center">
            <h2 class="text-6xl font-black text-destructive mb-3">ԱՎԱՐՏ</h2>
            <p class="text-muted-foreground mb-6">
              Հաշիվ:
              <span class="text-2xl font-bold text-foreground dark:text-primary-foreground">{{
                score
              }}</span>
              &nbsp;·&nbsp; Լավագույն շարք:
              <span class="font-bold text-foreground dark:text-primary-foreground">{{
                bestStreak
              }}</span>
            </p>
            <div
              class="bg-muted dark:bg-card rounded-2xl border dark:border-border p-6 mb-6 shadow-inner"
            >
              <p
                class="text-xs text-muted-foreground uppercase tracking-widest mb-3 font-bold"
              >
                Ճիշտ պատասխանն էր
              </p>
              <div
                data-mf-answer
                class="min-h-[44px] flex items-center justify-center overflow-x-auto"
              />
              <p class="text-xs text-muted-foreground mt-2">{{ problem.display }}</p>
            </div>
            <div class="flex flex-col gap-3">
              <button
                class="rounded-2xl bg-chart-5 hover:bg-chart-5 text-primary-foreground px-6 py-3 font-bold transition"
                @click="startGame(mode)"
              >
                Նորից խաղալ — {{ MODES[mode].label }}
              </button>
              <button
                class="rounded-2xl border border-border dark:border-border px-6 py-3 text-sm font-medium hover:bg-muted dark:hover:bg-card transition"
                @click="gameState = 'idle'"
              >
                Փոխել դժվարությունը
              </button>
            </div>
          </div>
        </div>
      </Transition>

      <Transition name="question">
        <div
          v-if="gameState === 'playing'"
          class="absolute top-8 left-1/2 -translate-x-1/2 z-30 pointer-events-none"
        >
          <div
            class="px-8 py-4 bg-card/90 dark:bg-card/90 backdrop-blur-xl border border-border dark:border-border rounded-[28px] shadow-2xl text-center"
            :class="isEinstein ? 'border-destructive/40 shadow-destructive/10' : ''"
          >
            <div
              data-mf-question
              class="min-h-[52px] min-w-[180px] max-w-[540px] flex items-center justify-center overflow-x-auto"
            />
          </div>
        </div>
      </Transition>

      <TransitionGroup name="bubble">
        <button
          v-for="bubble in bubbles"
          :key="bubble.id"
          class="absolute flex items-center justify-center rounded-full border-4 border-primary-foreground/20 shadow-2xl transition-transform select-none overflow-hidden"
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
          @mousedown="handleBubble(bubble)"
          @touchstart.prevent="handleBubble(bubble)"
        >
          <div
            :data-mf-bubble="bubble.latex"
            class="w-full h-full flex items-center justify-center pointer-events-none"
          />
        </button>
      </TransitionGroup>

      <Transition name="feedback">
        <div
          v-if="feedback"
          class="absolute bottom-16 left-0 right-0 text-center pointer-events-none z-50"
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
      class="shrink-0 text-center py-1.5 text-[11px] text-muted-foreground dark:text-muted-foreground bg-card dark:bg-card border-t border-border dark:border-border"
    >
      ✓ +{{ cfg.bonusSecs }}վ բոնուս
      <span v-if="cfg.penaltySecs > 0">
        · ✗ -{{ cfg.penaltySecs }}վ տուգանք</span
      >
      · շարք ×{{ streak }} ·
      <span :style="{ color: timerColor }" class="font-medium">{{
        timerDisplay
      }}</span>
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


