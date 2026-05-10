<script setup lang="ts">
import { computed, ref } from "vue";
import { usePage } from "@inertiajs/vue3";
import AnimatedBackground from "@/components/AnimatedBackground.vue";
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

const props = withDefaults(
  defineProps<{
    contentWidth?: "little" | "big";
  }>(),
  {
    contentWidth: "little",
  },
);

const contentShellClass = computed(() =>
  props.contentWidth === "big"
    ? "admin-content-shell--big"
    : "admin-content-shell--little",
);

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
    <div class="relative z-10 flex h-screen">
      <aside
        class="sticky top-0 hidden h-screen w-72 border-r border-sidebar-border/70 bg-sidebar text-sidebar-foreground md:flex md:flex-col"
      >
        <div
          class="flex h-18 items-center border-b border-sidebar-border/70 px-6"
        >
          <div
            class="flex items-center gap-2 text-xl font-bold tracking-tight text-sidebar-foreground"
          >
            <div
              class="flex h-8 w-8 items-center justify-center rounded bg-gradient-to-br from-primary to-accent-foreground"
            >
              <span class="text-lg font-serif italic text-primary-foreground shadow-sm"
                >fx</span
              >
            </div>
            MathDash<span class="text-primary">.</span>
          </div>
        </div>

        <div class="flex-1 overflow-hidden p-4">
          <p
            class="mb-3 mt-2 px-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground"
          >
            {{ __("Modules") }}
          </p>

          <nav class="space-y-1">
            <slot name="sidebar" />
          </nav>

          <p
            class="mb-4 mt-8 px-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground"
          >
            {{ __("Coming Soon") }}
          </p>
          <nav class="space-y-1">
            <div
              v-for="item in comingSoonModules"
              :key="item.label"
              class="group flex cursor-not-allowed items-center justify-between rounded-lg px-3 py-2.5 text-muted-foreground transition-all hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
            >
              <div class="flex items-center gap-3">
                <component :is="item.icon" :size="18" />
                <span class="text-sm font-medium">{{ __(item.label) }}</span>
              </div>
              <Lock
                :size="12"
                class="text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100"
              />
            </div>
          </nav>
        </div>
      </aside>

      <div class="flex h-screen flex-1 flex-col overflow-hidden">
        <header
          class="sticky top-0 z-[120] border-b border-border/70 bg-background px-6 py-4 text-foreground"
        >
          <div class="flex items-center justify-between gap-4">
            <div class="min-w-0 flex-1">
              <slot name="header" />
            </div>

            <div class="flex items-center gap-3">
              <select
                :value="language"
                @change="changeLanguage"
                class="rounded-lg border border-border bg-input px-3 py-2 text-sm font-medium text-foreground shadow-sm transition hover:border-ring focus:border-ring focus:outline-none"
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
                content-classes="py-1 bg-popover text-popover-foreground"
              >
                <template #trigger>
                  <div class="flex items-center justify-center gap-4">
                    <div>
                      <p class="text-sm">{{ user.name }}</p>
                      <p class="text-sm">{{ user.email }}</p>
                    </div>
                    <button
                      type="button"
                      class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full border border-border bg-gradient-to-br from-primary to-accent-foreground text-sm font-semibold text-primary-foreground shadow-sm transition hover:scale-[1.03]"
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
                      class="text-sm font-semibold text-popover-foreground"
                    >
                      {{ user?.name ?? __("User") }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                      {{ user?.email ?? "" }}
                    </p>
                  </div>

                  <DropdownLink :href="route('profile.edit')">{{
                    __("Profile")
                  }}</DropdownLink>

                  <div class="px-4 py-2">
                    <p
                      class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                    >
                      {{ __("Theme") }}
                    </p>
                    <div class="grid grid-cols-2 gap-2">
                      <button
                        type="button"
                        class="inline-flex items-center justify-center gap-1 rounded-md border px-2 py-1.5 text-xs font-medium transition"
                        :class="
                          theme === 'light'
                            ? 'border-primary/30 bg-accent text-accent-foreground'
                            : 'border-border text-muted-foreground hover:bg-accent hover:text-accent-foreground'
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
                            ? 'border-primary/30 bg-accent text-accent-foreground'
                            : 'border-border text-muted-foreground hover:bg-accent hover:text-accent-foreground'
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

        <main class="relative flex-1 overflow-y-auto bg-background">
          <div
            class="admin-side-background pointer-events-none absolute inset-0 z-0 overflow-hidden"
          >
            <AnimatedBackground />
          </div>

          <div
            class="admin-content-shell relative z-10 mx-auto min-h-full w-full border-x border-border bg-background p-6"
            :class="contentShellClass"
          >
            <slot />
          </div>
        </main>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-content-shell {
  box-shadow: 0 0 36px color-mix(in srgb, var(--ring) 10%, transparent);
}

.admin-content-shell--little {
  max-width: 72rem;
}

.admin-content-shell--big {
  max-width: 1548px;
}

@media (max-width: 80rem) {
  .admin-side-background {
    display: none;
  }

  .admin-content-shell {
    border-left: 0;
    border-right: 0;
  }
}
</style>
