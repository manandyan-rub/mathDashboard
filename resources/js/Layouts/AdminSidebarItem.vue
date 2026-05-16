<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import type { MenuItem } from "@/data/adminMenu";
import type { UserRole } from "@/types/auth";
import AdminSidebarItem from "@/Layouts/AdminSidebarItem.vue";

const props = defineProps<{
  item: MenuItem;
  level?: number;
}>();

const level = computed(() => props.level ?? 0);

const page = usePage();
const isUserRole = (role: unknown): role is UserRole =>
  role === "student" || role === "lecturer" || role === "admin";
const currentRole = computed<UserRole>(() => {
  const role = (page.props as any).auth?.user?.role;
  return isUserRole(role) ? role : "student";
});
const canView = (item: MenuItem) =>
  !item.roles?.length || item.roles.includes(currentRole.value);
const visibleChildren = computed(
  () => props.item.children?.filter((child) => canView(child)) ?? [],
);
const isVisible = computed(
  () =>
    canView(props.item) &&
    (!props.item.children?.length || visibleChildren.value.length > 0),
);
const hasChildren = computed(() => visibleChildren.value.length > 0);
const normalizePath = (path: string) => {
  const normalized = path.split("?")[0].replace(/\/+$/, "");
  return normalized === "" ? "/" : normalized;
};

const currentPath = computed(() => normalizePath(page.url));

const routePath = (routeName: string) => {
  try {
    const origin =
      typeof window === "undefined"
        ? "http://localhost"
        : window.location.origin;

    return normalizePath(new URL(route(routeName), origin).pathname);
  } catch {
    return "";
  }
};

const isActive = (routeName?: string) => {
  if (!routeName) return false;
  return currentPath.value === routePath(routeName);
};

const hasActiveChild = (item: MenuItem): boolean => {
  if (!canView(item)) return false;
  if (item.route && isActive(item.route)) return true;
  if (!item.children) return false;

  return item.children.some((child) => canView(child) && hasActiveChild(child));
};

const opened = ref(hasActiveChild(props.item));

watch(
  () => hasActiveChild(props.item),
  (active) => {
    if (active) {
      opened.value = true;
    }
  },
);
</script>

<template>
  <div v-if="isVisible">
    <template v-if="hasChildren">
      <button
        type="button"
        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm transition"
        :class="
          hasActiveChild(item)
            ? 'border border-sidebar-ring/30 bg-sidebar-accent text-sidebar-accent-foreground'
            : 'text-sidebar-foreground/80 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'
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
          v-for="child in visibleChildren"
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
            ? 'border border-sidebar-ring/30 bg-sidebar-accent text-sidebar-accent-foreground'
            : 'text-sidebar-foreground/80 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'
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
