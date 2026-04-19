<script setup lang="ts">
import { adminMenu } from "@/data/adminMenu";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import AdminSidebarItem from "@/Layouts/AdminSidebarItem.vue";
import PreviewGeneral from "./partials/PreviewGeneral.vue";

defineProps<{
  quizId: number;
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
    <PreviewGeneral :quiz-id="quizId" />
  </AdminLayout>
</template>
