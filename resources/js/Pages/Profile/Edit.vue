<script setup lang="ts">
import AdminFormLayout from "@/Layouts/AdminFormLayout.vue";
import { cmsTranslate as __ } from "@/lib/i18n";
import type {
  ProfilePreferenceSetting,
  ProfileSection,
} from "@/types/profile";
import { Head, Link } from "@inertiajs/vue3";
import { Bell, LogOut, Shield, User } from "lucide-vue-next";
import { onBeforeUnmount, onMounted, ref } from "vue";
import DeleteUserForm from "./Partials/DeleteUserForm.vue";
import TwoFactorSettingsForm from "./Partials/TwoFactorSettingsForm.vue";
import UpdatePasswordForm from "./Partials/UpdatePasswordForm.vue";
import UpdateProfileInformationForm from "./Partials/UpdateProfileInformationForm.vue";

defineProps({
  mustVerifyEmail: {
    type: Boolean,
    default: false,
  },
  status: {
    type: String,
    default: "",
  },
});

const activeSection = ref<ProfileSection>("profile");
const preferencesSaved = ref(false);
const preferenceStorageKey = "mathdash-profile-preferences";
let preferenceSavedTimer: ReturnType<typeof setTimeout> | null = null;

const preferenceSettings = ref<ProfilePreferenceSetting[]>([
  {
    key: "studyReminders",
    label: "Study reminders",
    description: "Get gentle reminders when you have not practiced recently.",
    enabled: true,
  },
  {
    key: "weeklySummary",
    label: "Weekly progress summary",
    description: "Receive a short recap of your XP and completed levels.",
    enabled: true,
  },
  {
    key: "productUpdates",
    label: "Product updates",
    description: "Hear about new calculators, games, and formulas.",
    enabled: false,
  },
]);

const sectionButtonClass = (section: ProfileSection): string =>
  [
    "flex w-full items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all",
    activeSection.value === section
      ? "border border-primary/20 bg-primary/10 text-primary"
      : "text-muted-foreground hover:bg-muted hover:text-foreground dark:hover:bg-card/50 dark:hover:text-primary-foreground",
  ].join(" ");

const setSection = (section: ProfileSection) => {
  activeSection.value = section;
};

const savePreferences = () => {
  if (typeof window !== "undefined") {
    const payload = Object.fromEntries(
      preferenceSettings.value.map((setting) => [setting.key, setting.enabled]),
    );
    localStorage.setItem(preferenceStorageKey, JSON.stringify(payload));
  }

  preferencesSaved.value = true;
  if (preferenceSavedTimer) clearTimeout(preferenceSavedTimer);
  preferenceSavedTimer = setTimeout(() => {
    preferencesSaved.value = false;
  }, 3000);
};

onMounted(() => {
  if (typeof window === "undefined") return;

  try {
    const saved = JSON.parse(localStorage.getItem(preferenceStorageKey) ?? "{}");
    preferenceSettings.value = preferenceSettings.value.map((setting) => ({
      ...setting,
      enabled:
        typeof saved[setting.key] === "boolean"
          ? saved[setting.key]
          : setting.enabled,
    }));
  } catch {
    localStorage.removeItem(preferenceStorageKey);
  }
});

onBeforeUnmount(() => {
  if (preferenceSavedTimer) clearTimeout(preferenceSavedTimer);
});
</script>

<template>
  <Head :title="__('Profile Settings')" />

  <AdminFormLayout :title="__('Profile Settings')">
    <div class="mx-auto w-full max-w-6xl p-6 lg:p-12">
      <div class="mb-8">
        <h1 class="mb-2 text-3xl font-bold text-foreground">
          {{ __("Profile Settings") }}
        </h1>
        <p class="text-muted-foreground">
          {{ __("Manage your profile, preferences, and account security.") }}
        </p>
      </div>

      <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
        <aside class="col-span-1">
          <div class="space-y-2 lg:sticky lg:top-24">
            <button
              type="button"
              :class="sectionButtonClass('profile')"
              @click="setSection('profile')"
            >
              <User :size="18" />
              <span>{{ __("Personal Information") }}</span>
            </button>

            <button
              type="button"
              :class="sectionButtonClass('security')"
              @click="setSection('security')"
            >
              <Shield :size="18" />
              <span>{{ __("Security") }}</span>
            </button>

            <button
              type="button"
              :class="sectionButtonClass('preferences')"
              @click="setSection('preferences')"
            >
              <Bell :size="18" />
              <span>{{ __("Preferences") }}</span>
            </button>

            <div class="mt-4 border-t border-border/80 pt-4 dark:border-border/60">
              <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="flex w-full items-center gap-3 rounded-xl px-4 py-3 font-medium text-destructive transition-all hover:bg-destructive/10 hover:text-destructive"
              >
                <LogOut :size="18" />
                <span>{{ __("Log Out") }}</span>
              </Link>
            </div>
          </div>
        </aside>

        <div class="col-span-1 lg:col-span-2">
          <UpdateProfileInformationForm
            v-if="activeSection === 'profile'"
            :must-verify-email="mustVerifyEmail"
            :status="status"
          />

          <div v-else-if="activeSection === 'security'" class="space-y-8">
            <TwoFactorSettingsForm />

            <UpdatePasswordForm />

            <div
              class="rounded-2xl border border-border bg-card/80 p-6 md:p-8 dark:border-border dark:bg-card/40"
            >
              <DeleteUserForm />
            </div>
          </div>

          <section
            v-else
            class="relative overflow-hidden rounded-2xl border border-border bg-card/80 p-6 md:p-8 dark:border-border dark:bg-card/40"
          >
            <h3
              class="mb-2 flex items-center gap-2 text-xl font-semibold text-foreground"
            >
              <Bell class="text-primary" :size="20" />
              {{ __("Preferences") }}
            </h3>
            <p class="mb-6 text-sm text-muted-foreground">
              {{ __("Choose how MathDash keeps you updated while you study.") }}
            </p>

            <div class="space-y-4">
              <label
                v-for="setting in preferenceSettings"
                :key="setting.key"
                class="flex items-start justify-between gap-4 rounded-xl border border-border bg-background/60 px-4 py-4 transition-colors hover:bg-muted/60 dark:bg-background/40"
              >
                <span>
                  <span class="block font-medium text-foreground">
                    {{ __(setting.label) }}
                  </span>
                  <span class="mt-1 block text-sm text-muted-foreground">
                    {{ __(setting.description) }}
                  </span>
                </span>

                <input
                  v-model="setting.enabled"
                  type="checkbox"
                  class="mt-1 h-5 w-5 rounded border-border text-primary focus:ring-ring"
                />
              </label>
            </div>

            <div class="mt-8 flex items-center justify-between gap-4">
              <span
                class="text-sm text-chart-2 transition-opacity duration-300"
                :style="{ opacity: preferencesSaved ? 1 : 0 }"
              >
                {{ __("Preferences saved.") }}
              </span>

              <button
                type="button"
                class="rounded-lg bg-primary px-6 py-2.5 font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                @click="savePreferences"
              >
                {{ __("Save preferences") }}
              </button>
            </div>
          </section>
        </div>
      </div>
    </div>
  </AdminFormLayout>
</template>
