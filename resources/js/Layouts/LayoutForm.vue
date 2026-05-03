<script setup lang="ts">
import { adminMenu } from "@/data/adminMenu";
import AdminLayout from "./AdminLayout.vue";
import AdminSidebarItem from "./AdminSidebarItem.vue";

defineProps<{
  title?: string;
}>();
</script>

<template>
  <AdminLayout>
    <template #sidebar>
      <AdminSidebarItem
        v-for="item in adminMenu"
        :key="item.title + (item.route ?? '')"
        :item="item"
      />
    </template>

    <div class="flex justify-center">
      <div class="w-full max-w-3xl">
        <div class="mb-6">
          <h1 class="text-2xl font-semibold">
            {{ title }}
          </h1>
        </div>

        <div class="rounded-xl border border-border bg-card p-6 text-card-foreground shadow-sm">
          <slot />
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
