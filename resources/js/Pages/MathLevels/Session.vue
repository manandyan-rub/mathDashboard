<script setup lang="ts">
import { adminMenu } from "@/data/adminMenu";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import AdminSidebarItem from "@/Layouts/AdminSidebarItem.vue";
import SessionGeneral from "./partials/SessionGeneral.vue";

defineProps<{
  attemptId: number;
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
    <SessionGeneral :attempt-id="attemptId" />
  </AdminLayout>
</template>
