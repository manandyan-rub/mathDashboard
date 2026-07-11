<script setup lang="ts">
import { cmsTranslate as __ } from "@/lib/i18n";
import { useForm, usePage } from "@inertiajs/vue3";
import { ShieldCheck } from "lucide-vue-next";
import { computed, onBeforeUnmount, ref } from "vue";

const user = usePage().props.auth.user as {
  must_enable_2fa?: boolean;
};

const saved = ref(false);
let saveTimer: ReturnType<typeof setTimeout> | null = null;

const form = useForm({
  must_enable_2fa: Boolean(user?.must_enable_2fa),
});

const statusLabel = computed(() =>
  form.must_enable_2fa ? __("Enabled") : __("Disabled"),
);

const statusClass = computed(() =>
  form.must_enable_2fa
    ? "bg-chart-2/10 text-chart-2 border-chart-2/30"
    : "bg-muted text-muted-foreground border-border",
);

const saveTwoFactorPreference = () => {
  form.patch(route("profile.two-factor.update"), {
    preserveScroll: true,
    onSuccess: () => {
      saved.value = true;
      if (saveTimer) clearTimeout(saveTimer);
      saveTimer = setTimeout(() => {
        saved.value = false;
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
    class="rounded-2xl border border-border bg-card/80 p-6 md:p-8 dark:border-border dark:bg-card/40"
  >
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
      <div>
        <h3 class="flex items-center gap-2 text-xl font-semibold text-foreground">
          <ShieldCheck class="text-primary" :size="20" />
          {{ __("Two-factor authentication") }}
        </h3>
        <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
          {{
            __(
              "Require an extra verification step for this account. When enabled, the database stores 1. When disabled, it stores 0.",
            )
          }}
        </p>
      </div>

      <span
        class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em]"
        :class="statusClass"
      >
        {{ statusLabel }}
      </span>
    </div>

    <div
      class="mt-6 rounded-2xl border border-border bg-background/60 p-5 dark:bg-background/40"
    >
      <label class="flex items-start justify-between gap-4">
        <div class="pr-4">
          <span class="block font-medium text-foreground">
            {{ __("Enable 2FA requirement") }}
          </span>
          <span class="mt-1 block text-sm text-muted-foreground">
            {{
              __(
                "Turn this on if this user must pass two-factor authentication before account access is considered complete.",
              )
            }}
          </span>
        </div>

        <input
          v-model="form.must_enable_2fa"
          type="checkbox"
          class="mt-1 h-5 w-5 rounded border-border text-primary focus:ring-ring"
        />
      </label>
    </div>

    <div class="mt-6 flex items-center justify-between gap-4">
      <span
        class="text-sm text-chart-2 transition-opacity duration-300"
        :style="{ opacity: saved ? 1 : 0 }"
      >
        {{ __("Two-factor setting saved.") }}
      </span>

      <button
        type="button"
        :disabled="form.processing"
        class="rounded-lg bg-primary px-6 py-2.5 font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
        @click="saveTwoFactorPreference"
      >
        {{ __("Save 2FA settings") }}
      </button>
    </div>
  </section>
</template>
