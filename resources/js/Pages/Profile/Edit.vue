<script setup lang="ts">
import AdminFormLayout from "@/Layouts/AdminFormLayout.vue";
import DeleteUserForm from "./Partials/DeleteUserForm.vue";
import UpdatePasswordForm from "./Partials/UpdatePasswordForm.vue";
import UpdateProfileInformationForm from "./Partials/UpdateProfileInformationForm.vue";
import { Head, Link } from "@inertiajs/vue3";
import { LogOut, Settings, Shield, User } from "lucide-vue-next";

defineProps({
  mustVerifyEmail: {
    type: Boolean,
    default: false,
  },
  status: {
    type: String,
    default: "",
  },
});
</script>

<template>
  <Head title="Պրոֆիլ" />

  <AdminFormLayout title="Օգտատիրոջ կարգավորումներ">
    <div class="mx-auto w-full max-w-6xl p-6 lg:p-12">
      <div class="mb-8">
        <h1 class="mb-2 text-3xl font-bold text-foreground dark:text-primary-foreground">
          Օգտատիրոջ կարգավորումներ
        </h1>
        <p class="text-muted-foreground dark:text-muted-foreground">
          Կառավարեք ձեր պրոֆիլը, նախընտրությունները և հաշվի անվտանգությունը։
        </p>
      </div>

      <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
        <div class="col-span-1 space-y-2">
          <button
            type="button"
            class="flex w-full items-center gap-3 rounded-xl border border-primary/20 bg-primary/10 px-4 py-3 font-medium text-primary transition-all dark:text-primary"
          >
            <User :size="18" /> Անձնական տվյալներ
          </button>

          <button
            type="button"
            class="flex w-full items-center gap-3 rounded-xl px-4 py-3 font-medium text-muted-foreground transition-all hover:bg-muted hover:text-foreground dark:text-muted-foreground dark:hover:bg-card/50 dark:hover:text-primary-foreground"
          >
            <Shield :size="18" /> Անվտանգություն
          </button>

          <button
            type="button"
            class="flex w-full items-center gap-3 rounded-xl px-4 py-3 font-medium text-muted-foreground transition-all hover:bg-muted hover:text-foreground dark:text-muted-foreground dark:hover:bg-card/50 dark:hover:text-primary-foreground"
          >
            <Settings :size="18" /> Նախընտրություններ
          </button>

          <div class="mt-4 border-t border-border/80 pt-4 dark:border-border/60">
            <Link
              :href="route('logout')"
              method="post"
              as="button"
              class="flex w-full items-center gap-3 rounded-xl px-4 py-3 font-medium text-destructive transition-all hover:bg-destructive/10 hover:text-destructive dark:text-destructive dark:hover:text-destructive"
            >
              <LogOut :size="18" /> Դուրս գալ
            </Link>
          </div>
        </div>

        <div class="col-span-1 space-y-8 lg:col-span-2">
          <UpdateProfileInformationForm
            :must-verify-email="mustVerifyEmail"
            :status="status"
          />

          <UpdatePasswordForm />

          <div
            class="rounded-2xl border border-border bg-card/80 p-6 md:p-8 dark:border-border dark:bg-card/40"
          >
            <DeleteUserForm />
          </div>
        </div>
      </div>
    </div>
  </AdminFormLayout>
</template>
