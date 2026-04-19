<script setup lang="ts">
import { adminMenu } from "@/data/adminMenu";
import AdminLayout from "./AdminLayout.vue";
import AdminSidebarItem from "./AdminSidebarItem.vue";
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
      <h1 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
        Dashboard
      </h1>
    </template>

    <slot />
  </AdminLayout>
</template>
