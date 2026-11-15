<script setup>
import GuestLayout from "@/Layouts/GuestLayout.vue";
import InputError from "@/components/InputError.vue";
import InputLabel from "@/components/InputLabel.vue";
import PrimaryButton from "@/components/PrimaryButton.vue";
import TextInput from "@/components/TextInput.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { cmsTranslate as __ } from "@/lib/i18n";
import { ArrowRight, Check, ChartLine, Trophy, X, Zap } from "lucide-vue-next";
import { computed, ref } from "vue";

const form = useForm({
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
});

const shaking = ref(false);

const submit = () => {
  form.post(route("register"), {
    onError: () => {
      shaking.value = false;
      requestAnimationFrame(() => (shaking.value = true));
      window.setTimeout(() => (shaking.value = false), 550);
    },
    onFinish: () => form.reset("password", "password_confirmation"),
  });
};

/** Rough client-side strength hint only; the server rules still decide. */
const strength = computed(() => {
  const value = form.password;
  if (!value) return 0;
  let score = 0;
  if (value.length >= 8) score += 1;
  if (value.length >= 12) score += 1;
  if (/[A-Z]/.test(value) && /[a-z]/.test(value)) score += 1;
  if (/\d/.test(value) && /[^A-Za-z0-9]/.test(value)) score += 1;
  return Math.max(1, score);
});

const strengthMeta = computed(
  () =>
    [
      { label: "", color: "bg-muted" },
      { label: __("Weak"), color: "bg-destructive" },
      { label: __("Fair"), color: "bg-rank-first" },
      { label: __("Good"), color: "bg-chart-5" },
      { label: __("Strong"), color: "bg-chart-2" },
    ][strength.value],
);

const confirmationState = computed(() => {
  if (!form.password_confirmation) return null;
  return form.password_confirmation === form.password;
});

const perks = [
  { icon: Zap, text: __("Earn XP for every solved exercise") },
  { icon: ChartLine, text: __("Track your progress with live charts") },
  { icon: Trophy, text: __("Climb the leaderboard") },
];
</script>

<template>
  <GuestLayout
    :heading="__('Start learning with')"
    highlight="MathDash"
    :tagline="__('Create a free account and turn practice into progress.')"
    :shake="shaking"
  >
    <Head :title="__('Register')" />

    <template #aside>
      <ul class="space-y-3">
        <li
          v-for="(perk, index) in perks"
          :key="perk.text"
          v-reveal="{ variant: 'left', delay: 750 + index * 110 }"
          class="group flex items-center gap-3 text-sm text-foreground"
        >
          <span
            class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary transition-transform duration-500 group-hover:rotate-[-10deg] group-hover:scale-110"
          >
            <component :is="perk.icon" :size="16" />
          </span>
          {{ perk.text }}
        </li>
      </ul>
    </template>

    <div class="mb-6">
      <h2 class="text-2xl font-semibold text-foreground">
        {{ __("Create account") }}
      </h2>
      <p class="mt-1 text-sm text-muted-foreground">
        {{ __("It only takes a minute.") }}
      </p>
    </div>

    <form class="space-y-4" @submit.prevent="submit">
      <div>
        <InputLabel for="name" :value="__('Name')" />

        <TextInput
          id="name"
          type="text"
          class="mt-1 block w-full"
          v-model="form.name"
          required
          autofocus
          autocomplete="name"
        />

        <InputError class="mt-2" :message="form.errors.name" />
      </div>

      <div>
        <InputLabel for="email" :value="__('Email')" />

        <TextInput
          id="email"
          type="email"
          class="mt-1 block w-full"
          v-model="form.email"
          required
          autocomplete="username"
        />

        <InputError class="mt-2" :message="form.errors.email" />
      </div>

      <div>
        <InputLabel for="password" :value="__('Password')" />

        <TextInput
          id="password"
          type="password"
          class="mt-1 block w-full"
          v-model="form.password"
          required
          autocomplete="new-password"
        />

        <div class="mt-2 flex items-center gap-3" aria-live="polite">
          <div class="grid flex-1 grid-cols-4 gap-1.5">
            <span
              v-for="segment in 4"
              :key="segment"
              class="h-1.5 overflow-hidden rounded-full bg-muted"
            >
              <span
                class="strength-segment block h-full rounded-full"
                :class="strengthMeta.color"
                :style="{ transform: `scaleX(${segment <= strength ? 1 : 0})` }"
              />
            </span>
          </div>
          <Transition
            mode="out-in"
            enter-active-class="transition duration-300"
            enter-from-class="opacity-0 translate-y-1"
            leave-active-class="transition duration-150"
            leave-to-class="opacity-0 -translate-y-1"
          >
            <span
              :key="strengthMeta.label"
              class="w-14 text-right text-xs font-medium text-muted-foreground"
              >{{ strengthMeta.label }}</span
            >
          </Transition>
        </div>

        <InputError class="mt-2" :message="form.errors.password" />
      </div>

      <div>
        <InputLabel
          for="password_confirmation"
          :value="__('Confirm Password')"
        />

        <div class="relative">
          <TextInput
            id="password_confirmation"
            type="password"
            class="mt-1 block w-full pr-10"
            v-model="form.password_confirmation"
            required
            autocomplete="new-password"
          />
          <Transition
            enter-active-class="transition duration-500 ease-[cubic-bezier(0.34,1.56,0.64,1)]"
            enter-from-class="scale-0 rotate-[-90deg] opacity-0"
            leave-active-class="transition duration-200"
            leave-to-class="scale-0 opacity-0"
            mode="out-in"
          >
            <span
              v-if="confirmationState !== null"
              :key="String(confirmationState)"
              class="pointer-events-none absolute right-3 top-1/2 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full"
              :class="
                confirmationState
                  ? 'bg-chart-2/15 text-chart-2'
                  : 'bg-destructive/15 text-destructive'
              "
              :aria-label="confirmationState ? __('Passwords match') : __('Passwords do not match')"
            >
              <Check v-if="confirmationState" :size="13" :stroke-width="3" />
              <X v-else :size="13" :stroke-width="3" />
            </span>
          </Transition>
        </div>

        <InputError class="mt-2" :message="form.errors.password_confirmation" />
      </div>

      <div class="pt-2">
        <PrimaryButton
          class="group flex w-full items-center justify-center gap-2 text-sm normal-case tracking-normal"
          :class="{ 'opacity-60': form.processing }"
          :disabled="form.processing"
        >
          {{ __("Register") }}
          <ArrowRight
            :size="16"
            class="transition-transform duration-300 group-hover:translate-x-1"
          />
        </PrimaryButton>
      </div>

      <p class="text-center text-sm text-muted-foreground">
        {{ __("Already registered?") }}
        <Link
          :href="route('login')"
          class="ml-1 font-medium text-primary underline decoration-primary/50 underline-offset-2 transition hover:decoration-primary"
        >
          {{ __("Log In") }}
        </Link>
      </p>
    </form>
  </GuestLayout>
</template>
