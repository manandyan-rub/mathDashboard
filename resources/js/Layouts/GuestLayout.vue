<script setup>
import ApplicationLogo from "@/components/ApplicationLogo.vue";
import ThemeSwitcher from "@/components/ThemeSwitcher.vue";
import { Link } from "@inertiajs/vue3";
</script>

<template>
  <div
    class="relative flex min-h-screen flex-col items-center bg-background pt-6 text-foreground sm:justify-center sm:pt-0"
  >
    <div class="absolute right-4 top-4 z-20">
      <ThemeSwitcher />
    </div>

    <div>
      <Link href="/">
        <ApplicationLogo
          class="h-20 w-20 fill-current text-slate-500 dark:text-slate-400"
        />
      </Link>
    </div>

    <div
      class="mt-6 w-full overflow-hidden border border-slate-200/70 bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg dark:border-slate-700/60 dark:bg-slate-900/70"
    >
      <slot />
    </div>
  </div>
</template>
