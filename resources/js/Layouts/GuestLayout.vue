<script setup>
import ApplicationLogo from "@/components/ApplicationLogo.vue";
import AnimatedBackground from "@/components/AnimatedBackground.vue";
import ThemeSwitcher from "@/components/ThemeSwitcher.vue";
import { Link } from "@inertiajs/vue3";
</script>

<template>
  <div
    class="relative flex min-h-screen flex-col items-center overflow-hidden bg-background pt-6 text-foreground sm:justify-center sm:pt-0"
  >
    <AnimatedBackground />

    <div class="absolute right-4 top-4 z-20">
      <ThemeSwitcher />
    </div>

    <div class="relative z-10">
      <Link href="/">
        <ApplicationLogo
          class="h-20 w-20 fill-current text-muted-foreground"
        />
      </Link>
    </div>

    <div
      class="relative z-10 mt-6 w-full overflow-hidden border border-border bg-card px-6 py-4 text-card-foreground shadow-md sm:max-w-md sm:rounded-lg"
    >
      <slot />
    </div>
  </div>
</template>
