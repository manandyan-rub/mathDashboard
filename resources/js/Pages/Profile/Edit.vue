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
        <h1 class="mb-2 text-3xl font-bold text-slate-900 dark:text-white">
          Օգտատիրոջ կարգավորումներ
        </h1>
        <p class="text-slate-600 dark:text-slate-400">
          Կառավարեք ձեր պրոֆիլը, նախընտրությունները և հաշվի անվտանգությունը։
        </p>
      </div>

      <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
        <div class="col-span-1 space-y-2">
          <button
            type="button"
            class="flex w-full items-center gap-3 rounded-xl border border-indigo-500/20 bg-indigo-500/10 px-4 py-3 font-medium text-indigo-700 transition-all dark:text-indigo-300"
          >
            <User :size="18" /> Անձնական տվյալներ
          </button>

          <button
            type="button"
            class="flex w-full items-center gap-3 rounded-xl px-4 py-3 font-medium text-slate-600 transition-all hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800/50 dark:hover:text-white"
          >
            <Shield :size="18" /> Անվտանգություն
          </button>

          <button
            type="button"
            class="flex w-full items-center gap-3 rounded-xl px-4 py-3 font-medium text-slate-600 transition-all hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800/50 dark:hover:text-white"
          >
            <Settings :size="18" /> Նախընտրություններ
          </button>

          <div class="mt-4 border-t border-slate-200/80 pt-4 dark:border-slate-800/60">
            <Link
              :href="route('logout')"
              method="post"
              as="button"
              class="flex w-full items-center gap-3 rounded-xl px-4 py-3 font-medium text-red-600 transition-all hover:bg-red-500/10 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
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
            class="rounded-2xl border border-slate-200 bg-white/80 p-6 md:p-8 dark:border-slate-800 dark:bg-slate-900/40"
          >
            <DeleteUserForm />
          </div>
        </div>
      </div>
    </div>
  </AdminFormLayout>
</template>
