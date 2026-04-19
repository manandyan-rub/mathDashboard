<script setup lang="ts">
import Checkbox from "@/components/Checkbox.vue";
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
    class="relative min-h-screen overflow-hidden bg-background text-slate-700 selection:bg-indigo-500/20 dark:text-slate-300 dark:selection:bg-indigo-500/30"
  >
    <div class="absolute right-6 top-6 z-20 flex items-center gap-3">
      <select
        :value="language"
        @change="changeLanguage"
        class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm transition hover:border-indigo-400 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
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
      class="relative z-10 mx-auto flex min-h-screen max-w-6xl items-center p-6 lg:p-12"
    >
      <div class="grid w-full gap-10 lg:grid-cols-2">
        <div class="flex flex-col justify-center">
          <div
            class="mb-6 inline-flex w-fit items-center gap-2 rounded-full border border-indigo-500/20 bg-indigo-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-indigo-500 dark:text-indigo-400"
          >
            <Sparkles :size="14" />
            MathDash
          </div>

          <h1
            class="mb-6 text-4xl font-bold leading-tight tracking-tight text-slate-900 dark:text-white md:text-6xl"
          >
            {{ __("Sign in to") }}
            <span
              class="bg-gradient-to-r from-indigo-400 to-emerald-400 bg-clip-text text-transparent"
            >
              MathDash
            </span>
            {{ __("platform") }}
          </h1>

          <p
            class="max-w-xl text-lg leading-relaxed text-slate-600 dark:text-slate-400"
          >
            {{ __("Learn, practice, and improve your math every day.") }}
          </p>
        </div>

        <div class="flex items-center justify-center">
          <div
            class="w-full max-w-md rounded-2xl border border-slate-200/80 bg-white/80 p-6 shadow-2xl backdrop-blur-xl dark:border-slate-700/50 dark:bg-slate-900/40"
          >
            <div class="mb-6 flex items-center justify-between">
              <div>
                <h2
                  class="text-2xl font-semibold text-slate-900 dark:text-white"
                >
                  {{ __("Welcome back") }}
                </h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                  {{ __("Sign in to your account") }}
                </p>
              </div>

              <div class="flex items-center gap-3">
                <button
                  type="button"
                  class="text-slate-500 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                >
                  <Bell :size="18" />
                </button>
                <div
                  class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-300 bg-slate-100 text-slate-500 dark:border-slate-700 dark:bg-slate-800"
                >
                  <Lock :size="14" />
                </div>
              </div>
            </div>

            <div
              v-if="status"
              class="mb-4 rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm font-medium text-emerald-500 dark:text-emerald-400"
            >
              {{ status }}
            </div>

            <form class="space-y-5" @submit.prevent="submit">
              <div>
                <label
                  for="email"
                  class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200"
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
                  class="block w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400 focus:border-indigo-500/60 focus:ring-1 focus:ring-indigo-500/60 dark:border-slate-700/50 dark:bg-slate-900/50 dark:text-white dark:placeholder:text-slate-600 dark:focus:border-indigo-500/50 dark:focus:ring-indigo-500/50"
                />

                <InputError class="mt-2" :message="form.errors.email" />
              </div>

              <div>
                <label
                  for="password"
                  class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200"
                >
                  {{ __("Password") }}
                </label>

                <TextInput
                  id="password"
                  v-model="form.password"
                  type="password"
                  required
                  autocomplete="current-password"
                  class="block w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400 focus:border-indigo-500/60 focus:ring-1 focus:ring-indigo-500/60 dark:border-slate-700/50 dark:bg-slate-900/50 dark:text-white dark:placeholder:text-slate-600 dark:focus:border-indigo-500/50 dark:focus:ring-indigo-500/50"
                />

                <InputError class="mt-2" :message="form.errors.password" />
              </div>

              <div class="flex items-center justify-between gap-4">
                <label class="flex items-center">
                  <Checkbox
                    name="remember"
                    v-model:checked="form.remember"
                    class="rounded border-slate-300 bg-white text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900"
                  />
                  <span class="ms-2 text-sm text-slate-500 dark:text-slate-400">
                    {{ __("Remember me") }}
                  </span>
                </label>

                <Link
                  v-if="canResetPassword"
                  :href="route('password.request')"
                  class="text-sm text-slate-500 underline transition hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                >
                  {{ __("Forgot password?") }}
                </Link>
              </div>

              <PrimaryButton
                class="flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-3 text-sm font-medium text-white transition-colors hover:bg-indigo-500"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
              >
                {{ __("Sign In") }}
                <ArrowRight :size="16" />
              </PrimaryButton>

              <div
                v-if="canRegister"
                class="pt-1 text-center text-sm text-slate-500 dark:text-slate-400"
              >
                {{ __("Don't have an account?") }}
                <Link
                  :href="route('register')"
                  class="ml-1 font-medium text-indigo-500 underline decoration-indigo-400/60 underline-offset-2 transition hover:text-indigo-400"
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
