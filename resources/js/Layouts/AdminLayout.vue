<script setup lang="ts">
import { computed, ref } from "vue";
import { usePage } from "@inertiajs/vue3";
import Dropdown from "@/components/Dropdown.vue";
import DropdownLink from "@/components/DropdownLink.vue";
import { useTheme } from "@/composables/useTheme";
import { getLanguage, setLanguage } from "@/lib/i18n";
import type { LocaleCode } from "@/lib/i18n";
import {
  Activity,
  Box,
  Calculator,
  ChartLine,
  Layers,
  Lock,
  Moon,
  Sun,
} from "lucide-vue-next";
import { route } from "ziggy-js";

const comingSoonModules = [
  { icon: ChartLine, label: "2D Graphing" },
  { icon: Calculator, label: "Algebra Engine" },
  { icon: Activity, label: "Calculus Steps" },
  { icon: Layers, label: "Matrices & Vectors" },
];

const page = usePage();
const user = computed(() => (page.props as any).auth?.user);
const initials = computed(() => {
  const name = (user.value?.name ?? "User").trim();
  const parts = name.split(/\s+/).filter(Boolean);
  if (parts.length === 0) return "U";
  if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
  return `${parts[0].charAt(0)}${parts[1].charAt(0)}`.toUpperCase();
});

const { theme, setTheme } = useTheme();
const language = ref<LocaleCode>(getLanguage());
const languages = [
  { value: "en", label: "English" },
  { value: "hy", label: "Armenian" },
  { value: "fr", label: "French" },
  { value: "ru", label: "Russian" },
];

function changeLanguage(event: Event) {
  const value = (event.target as HTMLSelectElement).value as LocaleCode;
  if (value === language.value) return;
  language.value = value;
  setLanguage(value);
  window.location.reload();
}
</script>

<template>
  <div class="relative h-screen overflow-hidden bg-background text-foreground">
    <div class="flex h-screen">
      <aside
        class="sticky top-0 hidden h-screen w-72 border-r border-slate-200/70 bg-white/90 text-slate-700 backdrop-blur-xl dark:border-slate-800/60 dark:bg-[#0B0F19]/90 dark:text-slate-300 md:flex md:flex-col"
      >
        <div
          class="flex h-18 items-center border-b border-slate-200/70 px-6 dark:border-slate-800/60"
        >
          <div
            class="flex items-center gap-2 text-xl font-bold tracking-tight text-slate-900 dark:text-white"
          >
            <div
              class="flex h-8 w-8 items-center justify-center rounded bg-gradient-to-br from-indigo-500 to-purple-600"
            >
              <span class="text-lg font-serif italic text-white shadow-sm"
                >fx</span
              >
            </div>
            MathDash<span class="text-indigo-500">.</span>
          </div>
        </div>

        <div class="flex-1 overflow-hidden p-4">
          <p
            class="mb-3 mt-2 px-2 text-xs font-semibold uppercase tracking-wider text-slate-500"
          >
            {{ __("Modules") }}
          </p>

          <nav class="space-y-1">
            <slot name="sidebar" />
          </nav>

          <p
            class="mb-4 mt-8 px-2 text-xs font-semibold uppercase tracking-wider text-slate-500"
          >
            {{ __("Coming Soon") }}
          </p>
          <nav class="space-y-1">
            <div
              v-for="item in comingSoonModules"
              :key="item.label"
              class="group flex cursor-not-allowed items-center justify-between rounded-lg px-3 py-2.5 text-slate-500 transition-all hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800/50 dark:hover:text-white"
            >
              <div class="flex items-center gap-3">
                <component :is="item.icon" :size="18" />
                <span class="text-sm font-medium">{{ __(item.label) }}</span>
              </div>
              <Lock
                :size="12"
                class="text-slate-400 opacity-0 transition-opacity group-hover:opacity-100 dark:text-slate-500"
              />
            </div>
          </nav>
        </div>
      </aside>

      <div class="flex h-screen flex-1 flex-col overflow-hidden">
        <header
          class="sticky top-0 z-[120] border-b border-slate-200/70 bg-white/85 px-6 py-4 text-slate-900 backdrop-blur-xl dark:border-slate-800/60 dark:bg-[#0B0F19]/85 dark:text-slate-100"
        >
          <div class="flex items-center justify-between gap-4">
            <div class="min-w-0 flex-1">
              <slot name="header" />
            </div>

            <div class="flex items-center gap-3">
              <select
                :value="language"
                @change="changeLanguage"
                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-indigo-400 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
              >
                <option
                  v-for="lang in languages"
                  :key="lang.value"
                  :value="lang.value"
                >
                  {{ __(lang.label) }}
                </option>
              </select>

              <Dropdown
                align="right"
                width="48"
                content-classes="py-1 bg-white dark:bg-slate-800"
              >
                <template #trigger>
                  <div class="flex items-center justify-center gap-4">
                    <div>
                      <p class="text-sm">{{ user.name }}</p>
                      <p class="text-sm">{{ user.email }}</p>
                    </div>
                    <button
                      type="button"
                      class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full border border-slate-300 bg-gradient-to-br from-indigo-500 to-violet-600 text-sm font-semibold text-white shadow-sm transition hover:scale-[1.03] dark:border-slate-700"
                    >
                      <img
                        v-if="user?.profile_photo_url"
                        :src="user.profile_photo_url"
                        alt="Profile"
                        class="h-full w-full object-cover"
                      />
                      <span v-else>{{ initials }}</span>
                    </button>
                  </div>
                </template>

                <template #content>
                  <div class="px-4 py-2">
                    <p
                      class="text-sm font-semibold text-slate-800 dark:text-slate-100"
                    >
                      {{ user?.name ?? __("User") }}
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-300">
                      {{ user?.email ?? "" }}
                    </p>
                  </div>

                  <DropdownLink :href="route('profile.edit')">{{
                    __("Profile")
                  }}</DropdownLink>

                  <div class="px-4 py-2">
                    <p
                      class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-300"
                    >
                      {{ __("Theme") }}
                    </p>
                    <div class="grid grid-cols-2 gap-2">
                      <button
                        type="button"
                        class="inline-flex items-center justify-center gap-1 rounded-md border px-2 py-1.5 text-xs font-medium transition"
                        :class="
                          theme === 'light'
                            ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-400 dark:bg-indigo-500/20 dark:text-indigo-200'
                            : 'border-slate-300 text-slate-600 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700'
                        "
                        @click.stop="setTheme('light')"
                      >
                        <Sun :size="14" />
                        {{ __("Light") }}
                      </button>
                      <button
                        type="button"
                        class="inline-flex items-center justify-center gap-1 rounded-md border px-2 py-1.5 text-xs font-medium transition"
                        :class="
                          theme === 'dark'
                            ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-400 dark:bg-indigo-500/20 dark:text-indigo-200'
                            : 'border-slate-300 text-slate-600 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700'
                        "
                        @click.stop="setTheme('dark')"
                      >
                        <Moon :size="14" />
                        {{ __("Dark") }}
                      </button>
                    </div>
                  </div>

                  <DropdownLink
                    :href="route('logout')"
                    method="post"
                    as="button"
                  >
                    {{ __("Logout") }}
                  </DropdownLink>
                </template>
              </Dropdown>
            </div>
          </div>
        </header>

        <main class="flex-1 overflow-y-auto bg-background p-6">
          <slot />
        </main>
      </div>
    </div>
  </div>
</template>
