<script setup>
import DangerButton from "@/components/DangerButton.vue";
import InputError from "@/components/InputError.vue";
import InputLabel from "@/components/InputLabel.vue";
import Modal from "@/components/Modal.vue";
import SecondaryButton from "@/components/SecondaryButton.vue";
import TextInput from "@/components/TextInput.vue";
import { useForm } from "@inertiajs/vue3";
import { nextTick, ref } from "vue";

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
  password: "",
});

const confirmUserDeletion = () => {
  confirmingUserDeletion.value = true;

  nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
  form.delete(route("profile.destroy"), {
    preserveScroll: true,
    onSuccess: () => closeModal(),
    onError: () => passwordInput.value.focus(),
    onFinish: () => form.reset(),
  });
};

const closeModal = () => {
  confirmingUserDeletion.value = false;

  form.clearErrors();
  form.reset();
};
</script>

<template>
  <section class="space-y-6">
    <header>
      <h2 class="text-lg font-medium text-foreground dark:text-primary-foreground">
        Ջնջել հաշիվը
      </h2>

      <p class="mt-1 text-sm text-muted-foreground dark:text-muted-foreground">
        Հաշիվը ջնջելուց հետո դրա բոլոր տվյալներն ու ռեսուրսները մշտապես կջնջվեն։
        Ջնջելուց առաջ պահպանեք ձեզ անհրաժեշտ տեղեկությունները։
      </p>
    </header>

    <DangerButton @click="confirmUserDeletion">Ջնջել հաշիվը</DangerButton>

    <Modal :show="confirmingUserDeletion" @close="closeModal">
      <div class="bg-card p-6 dark:bg-card">
        <h2 class="text-lg font-medium text-foreground dark:text-foreground">
          Համոզվա՞ծ եք, որ ցանկանում եք ջնջել հաշիվը։
        </h2>

        <p class="mt-1 text-sm text-muted-foreground dark:text-muted-foreground">
          Հաշիվը ջնջվելուց հետո դրա բոլոր տվյալներն ու ռեսուրսները մշտապես
          կջնջվեն։ Մուտքագրեք ձեր գաղտնաբառը՝ հաստատելու, որ ցանկանում եք
          շարունակել։
        </p>

        <div class="mt-6">
          <InputLabel for="password" value="Գաղտնաբառ" class="sr-only" />

          <TextInput
            id="password"
            ref="passwordInput"
            v-model="form.password"
            type="password"
            class="mt-1 block w-3/4"
            placeholder="Գաղտնաբառ"
            @keyup.enter="deleteUser"
          />

          <InputError :message="form.errors.password" class="mt-2" />
        </div>

        <div class="mt-6 flex justify-end">
          <SecondaryButton @click="closeModal"> Չեղարկել </SecondaryButton>

          <DangerButton
            class="ms-3"
            :class="{ 'opacity-25': form.processing }"
            :disabled="form.processing"
            @click="deleteUser"
          >
            Ջնջել հաշիվը
          </DangerButton>
        </div>
      </div>
    </Modal>
  </section>
</template>
