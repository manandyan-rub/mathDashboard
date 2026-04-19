<script setup lang="ts">
import { computed, ref } from "vue";
import { Link } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import type { MenuItem } from "@/data/adminMenu";
import AdminSidebarItem from "@/Layouts/AdminSidebarItem.vue";

const props = defineProps<{
  item: MenuItem;
  level?: number;
}>();

const level = computed(() => props.level ?? 0);

const hasChildren = computed(() => !!props.item.children?.length);

const isActive = (routeName?: string) => {
  if (!routeName) return false;
  return route().current(routeName);
};

const hasActiveChild = (item: MenuItem): boolean => {
  if (item.route && isActive(item.route)) return true;
  if (!item.children) return false;

  return item.children.some((child) => hasActiveChild(child));
};

const opened = ref(hasActiveChild(props.item));
</script>

<template>
  <div>
    <template v-if="hasChildren">
      <button
        type="button"
        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm transition"
        :class="
          hasActiveChild(item)
            ? 'border border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-slate-700/60 dark:bg-slate-800 dark:text-white'
            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800/50 dark:hover:text-white'
        "
        :style="{ paddingLeft: `${12 + level * 16}px` }"
        @click="opened = !opened"
      >
        <span class="flex items-center gap-3">
          <component v-if="item.icon" :is="item.icon" :size="18" />
          <span>{{ __(item.title) }}</span>
        </span>

        <span class="text-xs">
          {{ opened ? "-" : "+" }}
        </span>
      </button>

      <div v-if="opened" class="mt-1 space-y-1">
        <AdminSidebarItem
          v-for="child in item.children"
          :key="child.title + (child.route ?? '')"
          :item="child"
          :level="level + 1"
        />
      </div>
    </template>

    <template v-else>
      <Link
        :href="item.route ? route(item.route) : '#'"
        class="block rounded-lg px-3 py-2 text-sm transition"
        :class="
          isActive(item.route)
            ? 'border border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-slate-700/60 dark:bg-slate-800 dark:text-white'
            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800/50 dark:hover:text-white'
        "
        :style="{ paddingLeft: `${12 + level * 16}px` }"
      >
        <span class="flex items-center gap-3">
          <component v-if="item.icon" :is="item.icon" :size="18" />
          <span>{{ __(item.title) }}</span>
        </span>
      </Link>
    </template>
  </div>
</template>
