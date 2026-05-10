<script setup lang="ts">
import { Moon, Sun } from "lucide-vue-next";
import { useTheme } from "@/composables/useTheme";

const { theme, setTheme } = useTheme();
</script>

<template>
  <div
    class="inline-flex items-center gap-1 rounded-full border border-border bg-card p-1 text-sm shadow-sm"
  >
    <button
      type="button"
      class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition"
      :class="
        theme === 'light'
          ? 'bg-primary text-primary-foreground'
          : 'text-muted-foreground hover:text-foreground'
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
          ? 'bg-primary text-primary-foreground'
          : 'text-muted-foreground hover:text-foreground'
      "
      @click="setTheme('dark')"
    >
      <Moon :size="14" />
      Dark
    </button>
  </div>
</template>
