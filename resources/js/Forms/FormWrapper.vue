<script setup lang="ts" generic="T extends Record<string, any>">
import { InertiaForm, useForm } from "@inertiajs/vue3";
import { reactive, watch } from "vue";
import { get, isPlainObject, set, startCase } from "lodash-es";
import type { RecursiveFormFieldBindings } from "@/types/index";

const props = defineProps<{
  data: T;
}>();

defineEmits<{
  submitForm: [value: InertiaForm<T>];
}>();

const form = useForm<T>(() => props.data);

function getError(path: string, value: unknown) {
  const typed = form.errors as Record<string, string>;

  if (typed[path]) return typed[path];

  if (Array.isArray(value)) {
    const firstChildError = Object.keys(typed).find((key) =>
      key.startsWith(`${path}.`),
    );
    if (firstChildError) return typed[firstChildError];
  }

  return undefined;
}

function buildBindRecursive<DataStructure extends object>(
  dataForStructure: DataStructure,
  parentPath: string = "",
): RecursiveFormFieldBindings<DataStructure> {
  const result: any = {};

  for (const key in dataForStructure) {
    const value = dataForStructure[key];
    const fullPath = parentPath ? `${parentPath}.${key}` : key;

    if (isPlainObject(value)) {
      result[key] = buildBindRecursive(value as object, fullPath);
    } else {
      result[key] = {
        id: fullPath,
        label: __(startCase(key)),
        get error() {
          return getError(fullPath, value);
        },
        get modelValue() {
          return get(form, fullPath);
        },
        "onUpdate:modelValue": (newValue: any) => {
          set(form, fullPath, newValue);
        },
      };
    }
  }

  return result;
}

const bind = reactive(buildBindRecursive(props.data));

watch(
  () => form.recentlySuccessful,
  (value) => {
    if (value) {
      form.reset();
    }
  },
);
</script>

<template>
  <form @submit.prevent="$emit('submitForm', form as InertiaForm<T>)">
    <slot :form="form" :bind="bind" />
  </form>
</template>
