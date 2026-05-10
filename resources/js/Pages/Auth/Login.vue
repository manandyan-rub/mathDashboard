<script setup lang="ts">
import Checkbox from "@/components/Checkbox.vue";
import AnimatedBackground from "@/components/AnimatedBackground.vue";
import InputError from "@/components/InputError.vue";
import PrimaryButton from "@/components/PrimaryButton.vue";
import TextInput from "@/components/TextInput.vue";
import ThemeSwitcher from "@/components/ThemeSwitcher.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { cmsTranslate as __, getLanguage, setLanguage } from "@/lib/i18n";
import type { LocaleCode } from "@/lib/i18n";
import { ArrowRight, Bell, Lock, Sparkles } from "lucide-vue-next";
import { route } from "ziggy-js";
import { ref } from "vue";

defineProps<{
  canResetPassword?: boolean;
  canRegister?: boolean;
  status?: string;
}>();

const form = useForm({
  email: "",
  password: "",
  remember: false,
});

const language = ref<LocaleCode>(getLanguage());
const languages = [
  { value: "en", label: "English" },
  { value: "hy", label: "Armenian" },
  { value: "fr", label: "French" },
  { value: "ru", label: "Russian" },
];

const submit = () => {
  form.post(route("login"), {
    onFinish: () => form.reset("password"),
  });
};

function changeLanguage(event: Event) {
  const value = (event.target as HTMLSelectElement).value as LocaleCode;
  if (value === language.value) return;
  language.value = value;
  setLanguage(value);
  window.location.reload();
}
</script>

<template>
  <Head :title="__('Sign In')" />

  <section
    class="relative min-h-screen overflow-hidden bg-background text-muted-foreground selection:bg-primary/20 dark:text-muted-foreground dark:selection:bg-primary/30"
  >
    <AnimatedBackground />

    <div class="absolute right-6 top-6 z-20 flex items-center gap-3">
      <select
        :value="language"
        @change="changeLanguage"
        class="rounded-lg border border-border bg-card px-3 py-1.5 text-sm font-medium text-muted-foreground shadow-sm transition hover:border-primary focus:border-primary focus:outline-none dark:border-border dark:bg-card dark:text-foreground"
      >
        <option v-for="lang in languages" :key="lang.value" :value="lang.value">
          {{ __(lang.label) }}
        </option>
      </select>
      <ThemeSwitcher />
    </div>

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
      class="relative z-10 mx-auto flex min-h-screen max-w-6xl items-center p-6 lg:p-12"
    >
      <div class="grid w-full gap-10 lg:grid-cols-2">
        <div class="flex flex-col justify-center">
          <div
            class="mb-6 inline-flex w-fit items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-primary dark:text-primary"
          >
            <Sparkles :size="14" />
            MathDash
          </div>

          <h1
            class="mb-6 text-4xl font-bold leading-tight tracking-tight text-foreground dark:text-primary-foreground md:text-6xl"
          >
            {{ __("Sign in to") }}
            <span
              class="bg-gradient-to-r from-primary to-chart-2 bg-clip-text text-transparent"
            >
              MathDash
            </span>
            {{ __("platform") }}
          </h1>

          <p
            class="max-w-xl text-lg leading-relaxed text-muted-foreground dark:text-muted-foreground"
          >
            {{ __("Learn, practice, and improve your math every day.") }}
          </p>
        </div>

        <div class="flex items-center justify-center">
          <div
            class="w-full max-w-md rounded-2xl border border-border/80 bg-card p-6 shadow-2xl dark:border-border/50 dark:bg-card"
          >
            <div class="mb-6 flex items-center justify-between">
              <div>
                <h2
                  class="text-2xl font-semibold text-foreground dark:text-primary-foreground"
                >
                  {{ __("Welcome back") }}
                </h2>
                <p class="mt-1 text-sm text-muted-foreground dark:text-muted-foreground">
                  {{ __("Sign in to your account") }}
                </p>
              </div>

              <div class="flex items-center gap-3">
                <button
                  type="button"
                  class="text-muted-foreground transition-colors hover:text-foreground dark:text-muted-foreground dark:hover:text-primary-foreground"
                >
                  <Bell :size="18" />
                </button>
                <div
                  class="flex h-8 w-8 items-center justify-center rounded-full border border-border bg-muted text-muted-foreground dark:border-border dark:bg-card"
                >
                  <Lock :size="14" />
                </div>
              </div>
            </div>

            <div
              v-if="status"
              class="mb-4 rounded-lg border border-chart-2/20 bg-chart-2/10 px-4 py-3 text-sm font-medium text-chart-2 dark:text-chart-2"
            >
              {{ status }}
            </div>

            <form class="space-y-5" @submit.prevent="submit">
              <div>
                <label
                  for="email"
                  class="mb-2 block text-sm font-medium text-muted-foreground dark:text-foreground"
                >
                  {{ __("Email") }}
                </label>

                <TextInput
                  id="email"
                  v-model="form.email"
                  type="email"
                  required
                  autofocus
                  autocomplete="username"
                  class="block w-full rounded-lg border border-border bg-card px-4 py-3 text-foreground placeholder:text-muted-foreground focus:border-primary/60 focus:ring-1 focus:ring-ring/60 dark:border-border/50 dark:bg-card dark:text-primary-foreground dark:placeholder:text-muted-foreground dark:focus:border-primary/50 dark:focus:ring-ring/50"
                />

                <InputError class="mt-2" :message="form.errors.email" />
              </div>

              <div>
                <label
                  for="password"
                  class="mb-2 block text-sm font-medium text-muted-foreground dark:text-foreground"
                >
                  {{ __("Password") }}
                </label>

                <TextInput
                  id="password"
                  v-model="form.password"
                  type="password"
                  required
                  autocomplete="current-password"
                  class="block w-full rounded-lg border border-border bg-card px-4 py-3 text-foreground placeholder:text-muted-foreground focus:border-primary/60 focus:ring-1 focus:ring-ring/60 dark:border-border/50 dark:bg-card dark:text-primary-foreground dark:placeholder:text-muted-foreground dark:focus:border-primary/50 dark:focus:ring-ring/50"
                />

                <InputError class="mt-2" :message="form.errors.password" />
              </div>

              <div class="flex items-center justify-between gap-4">
                <label class="flex items-center">
                  <Checkbox
                    name="remember"
                    v-model:checked="form.remember"
                    class="rounded border-border bg-card text-primary shadow-sm focus:ring-ring dark:border-border dark:bg-card"
                  />
                  <span class="ms-2 text-sm text-muted-foreground dark:text-muted-foreground">
                    {{ __("Remember me") }}
                  </span>
                </label>

                <Link
                  v-if="canResetPassword"
                  :href="route('password.request')"
                  class="text-sm text-muted-foreground underline transition hover:text-foreground dark:text-muted-foreground dark:hover:text-primary-foreground"
                >
                  {{ __("Forgot password?") }}
                </Link>
              </div>

              <PrimaryButton
                class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-3 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
              >
                {{ __("Sign In") }}
                <ArrowRight :size="16" />
              </PrimaryButton>

              <div
                v-if="canRegister"
                class="pt-1 text-center text-sm text-muted-foreground dark:text-muted-foreground"
              >
                {{ __("Don't have an account?") }}
                <Link
                  :href="route('register')"
                  class="ml-1 font-medium text-primary underline decoration-primary/60 underline-offset-2 transition hover:text-primary"
                >
                  {{ __("Register") }}
                </Link>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
