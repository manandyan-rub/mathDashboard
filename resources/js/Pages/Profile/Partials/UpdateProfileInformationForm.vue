<script setup lang="ts">
import InputError from "@/components/InputError.vue";
import { Link, useForm, usePage } from "@inertiajs/vue3";
import { Camera, User } from "lucide-vue-next";
import { computed, onBeforeUnmount, ref } from "vue";

const props = defineProps({
  mustVerifyEmail: {
    type: Boolean,
    default: false,
  },
  status: {
    type: String,
    default: "",
  },
});

const user = usePage().props.auth.user as {
  name?: string;
  email?: string;
  email_verified_at?: string | null;
};

const splitName = (fullName?: string) => {
  const value = (fullName ?? "").trim();
  if (!value) return { first: "", last: "" };
  const parts = value.split(/\s+/);
  return {
    first: parts[0] ?? "",
    last: parts.slice(1).join(" "),
  };
};

const parsedName = splitName(user.name);
const firstName = ref(parsedName.first);
const lastName = ref(parsedName.last);
const institution = ref("MIT");
const profileSaved = ref(false);

let saveTimer: ReturnType<typeof setTimeout> | null = null;

const form = useForm({
  name: user.name ?? "",
  email: user.email ?? "",
});

const initials = computed(() => {
  const firstInitial = firstName.value.trim().charAt(0);
  const lastInitial = lastName.value.trim().charAt(0);
  const value = `${firstInitial}${lastInitial}`.toUpperCase();
  return value || "Օ";
});

const saveProfile = () => {
  form.name = [firstName.value.trim(), lastName.value.trim()]
    .filter(Boolean)
    .join(" ");

  form.patch(route("profile.update"), {
    preserveScroll: true,
    onSuccess: () => {
      profileSaved.value = true;
      if (saveTimer) clearTimeout(saveTimer);
      saveTimer = setTimeout(() => {
        profileSaved.value = false;
      }, 3000);
    },
  });
};

onBeforeUnmount(() => {
  if (saveTimer) clearTimeout(saveTimer);
});
</script>

<template>
  <section
    class="relative overflow-hidden rounded-2xl border border-border bg-card/80 p-6 md:p-8 dark:border-border dark:bg-card/40"
  >
    <h3
      class="mb-6 flex items-center gap-2 text-xl font-semibold text-foreground dark:text-primary-foreground"
    >
      <User class="text-primary dark:text-primary" :size="20" /> Անձնական
      տվյալներ
    </h3>

    <form class="space-y-6" @submit.prevent="saveProfile">
      <div class="flex items-center gap-6">
        <div class="group relative cursor-pointer">
          <div
            class="flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-tr from-primary to-chart-4 text-2xl font-bold text-primary-foreground shadow-lg"
          >
            {{ initials }}
          </div>
          <div
            class="absolute inset-0 flex items-center justify-center rounded-full bg-foreground/50 opacity-0 transition-opacity group-hover:opacity-100"
          >
            <Camera :size="20" class="text-primary-foreground" />
          </div>
        </div>

        <div>
          <h4 class="font-medium text-foreground dark:text-primary-foreground">
            Պրոֆիլի նկար
          </h4>
          <p class="mt-1 text-sm text-muted-foreground dark:text-muted-foreground">
            PNG, JPG մինչև 5MB
          </p>
          <button
            type="button"
            class="mt-2 text-xs font-medium text-primary transition-colors hover:text-primary dark:text-primary dark:hover:text-primary"
          >
            Վերբեռնել նոր նկար
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div class="space-y-2">
          <label
            for="first_name"
            class="text-sm font-medium text-muted-foreground dark:text-muted-foreground"
            >Անուն</label
          >
          <input
            id="first_name"
            v-model="firstName"
            type="text"
            required
            autofocus
            autocomplete="given-name"
            class="w-full rounded-lg border border-border bg-card px-4 py-2.5 text-foreground transition-all focus:border-primary focus:outline-none focus:ring-1 focus:ring-ring dark:border-border/50 dark:bg-background dark:text-primary-foreground"
          />
        </div>

        <div class="space-y-2">
          <label
            for="last_name"
            class="text-sm font-medium text-muted-foreground dark:text-muted-foreground"
            >Ազգանուն</label
          >
          <input
            id="last_name"
            v-model="lastName"
            type="text"
            autocomplete="family-name"
            class="w-full rounded-lg border border-border bg-card px-4 py-2.5 text-foreground transition-all focus:border-primary focus:outline-none focus:ring-1 focus:ring-ring dark:border-border/50 dark:bg-background dark:text-primary-foreground"
          />
        </div>
      </div>

      <InputError class="mt-1" :message="form.errors.name" />

      <div class="space-y-2">
        <label
          for="email"
          class="text-sm font-medium text-muted-foreground dark:text-muted-foreground"
          >Էլ․ հասցե</label
        >
        <input
          id="email"
          v-model="form.email"
          type="email"
          required
          autocomplete="username"
          class="w-full rounded-lg border border-border bg-card px-4 py-2.5 text-foreground transition-all focus:border-primary focus:outline-none focus:ring-1 focus:ring-ring dark:border-border/50 dark:bg-background dark:text-primary-foreground"
        />
        <InputError class="mt-1" :message="form.errors.email" />
      </div>

      <div class="space-y-2">
        <label
          for="institution"
          class="text-sm font-medium text-muted-foreground dark:text-muted-foreground"
          >Համալսարան / Կազմակերպություն</label
        >
        <input
          id="institution"
          v-model="institution"
          type="text"
          class="w-full rounded-lg border border-border bg-card px-4 py-2.5 text-foreground transition-all focus:border-primary focus:outline-none focus:ring-1 focus:ring-ring dark:border-border/50 dark:bg-background dark:text-primary-foreground"
        />
      </div>

      <div
        v-if="props.mustVerifyEmail && user.email_verified_at === null"
        class="rounded-lg border border-chart-3/40 bg-chart-3/10 px-4 py-3 text-sm text-chart-3 dark:border-chart-3/20 dark:bg-chart-3/10 dark:text-chart-3"
      >
        Ձեր էլ․ հասցեն հաստատված չէ։
        <Link
          :href="route('verification.send')"
          method="post"
          as="button"
          class="ml-1 font-medium text-primary underline decoration-primary/60 underline-offset-2 transition-colors hover:text-primary dark:text-primary dark:hover:text-primary"
        >
          Սեղմեք այստեղ՝ հաստատման նամակը նորից ուղարկելու համար։
        </Link>

        <p
          v-if="props.status === 'verification-link-sent'"
          class="mt-2 text-sm font-medium text-chart-2"
        >
          Հաստատման նոր հղումը ուղարկվել է ձեր էլ․ հասցեին։
        </p>
      </div>

      <div class="flex items-center justify-between pt-2">
        <span
          class="text-sm text-chart-2 transition-opacity duration-300"
          :style="{ opacity: profileSaved ? 1 : 0 }"
        >
          Պրոֆիլը հաջողությամբ թարմացվեց։
        </span>

        <button
          type="submit"
          :disabled="form.processing"
          class="rounded-lg bg-primary px-6 py-2.5 font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
        >
          Պահպանել փոփոխությունները
        </button>
      </div>
    </form>
  </section>
</template>
