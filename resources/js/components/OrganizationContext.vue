<script setup lang="ts">
import type { TenantContext } from "@/types/auth";
import { usePage } from "@inertiajs/vue3";
import { Building2, GraduationCap } from "lucide-vue-next";
import { computed } from "vue";

const page = usePage();
const tenant = computed<TenantContext | null>(
  () => ((page.props as any).auth?.tenant as TenantContext | null) ?? null,
);
const visibleMemberships = computed(
  () => tenant.value?.memberships.slice(0, 2) ?? [],
);
const remainingCount = computed(() =>
  Math.max(0, (tenant.value?.memberships.length ?? 0) - 2),
);
</script>

<template>
  <div v-if="tenant" class="space-y-2">
    <div class="flex items-start gap-2">
      <Building2 :size="15" class="mt-0.5 shrink-0 text-primary" />
      <div class="min-w-0">
        <p class="truncate text-xs font-semibold text-foreground">
          {{ tenant.organization.name }}
        </p>
        <p class="text-[11px] text-muted-foreground">
          {{ tenant.organization.plan }}
        </p>
      </div>
    </div>

    <div
      v-for="membership in visibleMemberships"
      :key="membership.class_id"
      class="flex items-start gap-2"
    >
      <GraduationCap :size="15" class="mt-0.5 shrink-0 text-muted-foreground" />
      <div class="min-w-0">
        <p class="truncate text-xs font-medium text-foreground">
          {{ membership.class_name }}
        </p>
        <p class="truncate text-[11px] text-muted-foreground">
          {{ membership.school_name }} · {{ membership.academic_year }}
        </p>
      </div>
    </div>

    <p v-if="remainingCount > 0" class="pl-6 text-[11px] text-muted-foreground">
      +{{ remainingCount }} more
    </p>
  </div>
</template>
