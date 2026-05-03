<script setup>
import { adminMenu } from "@/data/adminMenu";
import { cmsTranslate as __ } from "@/lib/i18n";
import { usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import AdminLayout from "./AdminLayout.vue";
import AdminSidebarItem from "./AdminSidebarItem.vue";

const props = defineProps({
  title: {
    type: String,
    default: "",
  },
});

const page = usePage();
const headerTitle = computed(
  () =>
    props.title ||
    page.props.headerTitle ||
    page.props.title ||
    titleFromComponent(page.component) ||
    __("Dashboard"),
);

function titleFromComponent(component) {
  const name = String(component ?? "").split("/").filter(Boolean).pop();
  if (!name) {
    return "";
  }

  return name
    .replace(/([a-z])([A-Z])/g, "$1 $2")
    .replace(/[-_]/g, " ");
}
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
      <slot name="header">
        <h1 class="text-lg font-semibold text-foreground">
          {{ headerTitle }}
        </h1>
      </slot>
    </template>

    <slot />
  </AdminLayout>
</template>
