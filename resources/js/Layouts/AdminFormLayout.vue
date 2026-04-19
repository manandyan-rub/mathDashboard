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

    <template #header>
      <h1 class="text-lg font-semibold">
        {{ title }}
      </h1>
    </template>

    <div class="relative min-h-[calc(100vh-8rem)]">
      <div
        class="absolute inset-0 z-0 opacity-[0.03]"
        style="
          background-image:
            linear-gradient(to right, #ffffff 1px, transparent 1px),
            linear-gradient(to bottom, #ffffff 1px, transparent 1px);
          background-size: 40px 40px;
        "
      />

      <div class="relative z-10 mx-auto w-full max-w-7xl">
        <div class="rounded-xl bg-background">
          <slot />
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
