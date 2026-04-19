<script setup lang="ts">
import InputError from "@/Components/InputError.vue";
import { useForm } from "@inertiajs/vue3";
import { Key } from "lucide-vue-next";
import { onBeforeUnmount, ref } from "vue";

const currentPasswordInput = ref<HTMLInputElement | null>(null);
const passwordInput = ref<HTMLInputElement | null>(null);
const passwordSaved = ref(false);

let saveTimer: ReturnType<typeof setTimeout> | null = null;

const form = useForm({
  current_password: "",
  password: "",
  password_confirmation: "",
});

const updatePassword = () => {
  form.put(route("password.update"), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      passwordSaved.value = true;
      if (saveTimer) clearTimeout(saveTimer);
      saveTimer = setTimeout(() => {
        passwordSaved.value = false;
      }, 3000);
    },
    onError: () => {
      if (form.errors.password) {
        form.reset("password", "password_confirmation");
        passwordInput.value?.focus();
      }

      if (form.errors.current_password) {
        form.reset("current_password");
        currentPasswordInput.value?.focus();
      }
    },
  });
};

onBeforeUnmount(() => {
  if (saveTimer) clearTimeout(saveTimer);
});
</script>

<template>
  <section
    class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white/80 p-6 md:p-8 dark:border-slate-800 dark:bg-slate-900/40"
  >
    <h3 class="mb-6 flex items-center gap-2 text-xl font-semibold text-slate-900 dark:text-white">
      <Key class="text-emerald-500 dark:text-emerald-400" :size="20" /> Թարմացնել գաղտնաբառը
    </h3>

    <form class="space-y-6" @submit.prevent="updatePassword">
      <div class="space-y-2">
        <label for="current_password" class="text-sm font-medium text-slate-700 dark:text-slate-300"
          >Ներկայիս գաղտնաբառ</label
        >
        <input
          id="current_password"
          ref="currentPasswordInput"
          v-model="form.current_password"
          type="password"
          required
          placeholder="********"
          autocomplete="current-password"
          class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-slate-900 transition-all focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 dark:border-slate-700/50 dark:bg-background dark:text-white"
        />
        <InputError :message="form.errors.current_password" class="mt-1" />
      </div>

      <div class="space-y-2">
        <label for="password" class="text-sm font-medium text-slate-700 dark:text-slate-300"
          >Նոր գաղտնաբառ</label
        >
        <input
          id="password"
          ref="passwordInput"
          v-model="form.password"
          type="password"
          required
          placeholder="********"
          autocomplete="new-password"
          class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-slate-900 transition-all focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 dark:border-slate-700/50 dark:bg-background dark:text-white"
        />
        <InputError :message="form.errors.password" class="mt-1" />
      </div>

      <div class="space-y-2">
        <label
          for="password_confirmation"
          class="text-sm font-medium text-slate-700 dark:text-slate-300"
          >Հաստատել նոր գաղտնաբառը</label
        >
        <input
          id="password_confirmation"
          v-model="form.password_confirmation"
          type="password"
          required
          placeholder="********"
          autocomplete="new-password"
          class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-slate-900 transition-all focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 dark:border-slate-700/50 dark:bg-background dark:text-white"
        />
        <InputError :message="form.errors.password_confirmation" class="mt-1" />
      </div>

      <div class="flex items-center justify-between pt-2">
        <span
          class="text-sm text-emerald-400 transition-opacity duration-300"
          :style="{ opacity: passwordSaved ? 1 : 0 }"
        >
          Գաղտնաբառը հաջողությամբ փոխվեց։
        </span>

        <button
          type="submit"
          :disabled="form.processing"
          class="rounded-lg border border-slate-300 bg-white px-6 py-2.5 font-medium text-slate-800 transition-colors hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:hover:bg-slate-700"
        >
          Թարմացնել գաղտնաբառը
        </button>
      </div>
    </form>
  </section>
</template>
