<script setup lang="ts">
import { adminMenu } from "@/data/adminMenu";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import AdminSidebarItem from "@/Layouts/AdminSidebarItem.vue";
import LevelsGeneral from "./partials/LevelsGeneral.vue";
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
    <LevelsGeneral />
  </AdminLayout>
</template>
