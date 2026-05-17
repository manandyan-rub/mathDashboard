<script setup lang="ts">
import { adminMenu } from "@/data/adminMenu";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import AdminSidebarItem from "@/Layouts/AdminSidebarItem.vue";
import OnePlusOneGeneral from "./partials/OnePlusOneGeneral.vue";
</script>

<template>
  <AdminLayout class="z-10" content-width="big" content-height="full">
    <template #sidebar>
      <AdminSidebarItem
        v-for="item in adminMenu"
        :key="item.title + (item.route ?? '')"
        :item="item"
      />
    </template>
    <OnePlusOneGeneral />
  </AdminLayout>
</template>
