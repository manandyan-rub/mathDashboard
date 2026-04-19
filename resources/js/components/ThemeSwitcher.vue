<script setup lang="ts">
import { Moon, Sun } from "lucide-vue-next";
import { useTheme } from "@/composables/useTheme";

const { theme, setTheme } = useTheme();
</script>

<template>
  <div
    class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white/80 p-1 text-sm shadow-sm backdrop-blur dark:border-slate-700 dark:bg-slate-900/70"
  >
    <button
      type="button"
      class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition"
      :class="
        theme === 'light'
          ? 'bg-slate-900 text-white dark:bg-slate-200 dark:text-slate-900'
          : 'text-slate-500 hover:text-slate-800 dark:text-slate-300 dark:hover:text-white'
      "
      @click="setTheme('light')"
    >
      <Sun :size="14" />
      Light
    </button>

    <button
      type="button"
      class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition"
      :class="
        theme === 'dark'
          ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900'
          : 'text-slate-500 hover:text-slate-800 dark:text-slate-300 dark:hover:text-white'
      "
      @click="setTheme('dark')"
    >
      <Moon :size="14" />
      Dark
    </button>
  </div>
</template>
