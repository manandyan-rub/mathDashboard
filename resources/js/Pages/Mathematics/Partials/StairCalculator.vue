<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import FormInput from "@/Forms/FormInput.vue";
import { Separator } from "@/components/ui/separator";
import { cmsTranslate as __ } from "@/lib/i18n";

type NullableNumber = number | null;

type StairInput = {
  steps: NullableNumber;
  stepWidth: NullableNumber;
  rise: NullableNumber;
  run: NullableNumber;
  depth: NullableNumber;
};

const emit = defineEmits<{
  "update:stair": [value: StairInput];
}>();

const form = ref<StairInput>({
  steps: 8,
  stepWidth: 1.2,
  rise: 0.18,
  run: 0.28,
  depth: 0.15,
});

const errors = ref<Partial<Record<keyof StairInput, string>>>({});

const normalized = computed(() => ({
  steps: Math.max(2, Math.round(Number(form.value.steps ?? 8))),
  stepWidth: Number(form.value.stepWidth ?? 1.2),
  rise: Number(form.value.rise ?? 0.18),
  run: Number(form.value.run ?? 0.28),
  depth: Number(form.value.depth ?? 0.15),
}));

function validate(): boolean {
  errors.value = {};
  const input = normalized.value;

  if (!Number.isFinite(input.steps) || input.steps < 2 || input.steps > 200) {
    errors.value.steps = __("Enter a value between 2 and 200");
  }
  if (!Number.isFinite(input.stepWidth) || input.stepWidth <= 0) {
    errors.value.stepWidth = __("Value must be greater than 0");
  }
  if (!Number.isFinite(input.rise) || input.rise <= 0) {
    errors.value.rise = __("Value must be greater than 0");
  }
  if (!Number.isFinite(input.run) || input.run <= 0) {
    errors.value.run = __("Value must be greater than 0");
  }
  if (!Number.isFinite(input.depth) || input.depth <= 0) {
    errors.value.depth = __("Value must be greater than 0");
  }

  return Object.keys(errors.value).length === 0;
}

function submit() {
  if (validate()) {
    emit("update:stair", { ...normalized.value });
  }
}

const totalHeight = computed(() =>
  +(normalized.value.rise * normalized.value.steps).toFixed(4),
);
const totalRun = computed(() =>
  +(normalized.value.run * normalized.value.steps).toFixed(4),
);
const stringerLength = computed(
  () => +Math.sqrt(totalHeight.value ** 2 + totalRun.value ** 2).toFixed(4),
);
const angleDeg = computed(() =>
  +((Math.atan2(totalHeight.value, totalRun.value) * 180) / Math.PI).toFixed(2),
);
const stepVolume = computed(
  () =>
    +(
      normalized.value.run *
      normalized.value.stepWidth *
      normalized.value.depth
    ).toFixed(5),
);
const totalVolume = computed(
  () => +(stepVolume.value * normalized.value.steps).toFixed(4),
);
const stepTopArea = computed(
  () => +(normalized.value.run * normalized.value.stepWidth).toFixed(4),
);
const stepFrontArea = computed(
  () => +(normalized.value.rise * normalized.value.stepWidth).toFixed(4),
);
const totalTopArea = computed(
  () => +(stepTopArea.value * normalized.value.steps).toFixed(4),
);
const totalFrontArea = computed(
  () => +(stepFrontArea.value * normalized.value.steps).toFixed(4),
);
const stepPitch = computed(
  () => +(normalized.value.run + normalized.value.rise).toFixed(4),
);
const riseRunRatio = computed(
  () => +(normalized.value.rise / normalized.value.run).toFixed(4),
);
const isSafeRise = computed(
  () => normalized.value.rise >= 0.14 && normalized.value.rise <= 0.21,
);

watch(
  form,
  () => {
    if (validate()) {
      emit("update:stair", { ...normalized.value });
    }
  },
  { deep: true, immediate: true },
);
</script>

<template>
  <div class="space-y-6">
    <Card>
      <CardHeader>
        <CardTitle>{{ __("Stair inputs") }}</CardTitle>
      </CardHeader>
      <CardContent class="space-y-4">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
          <div>
            <FormInput
              v-model="form.steps"
              :label="__('Number of steps')"
              type="number"
              min="2"
              max="200"
              step="1"
              placeholder="8"
            />
            <p v-if="errors.steps" class="mt-1 text-xs text-red-500">
              {{ errors.steps }}
            </p>
          </div>

          <div>
            <FormInput
              v-model="form.stepWidth"
              :label="__('Step width (m)')"
              type="number"
              min="0.01"
              step="0.01"
              placeholder="1.2"
            />
            <p v-if="errors.stepWidth" class="mt-1 text-xs text-red-500">
              {{ errors.stepWidth }}
            </p>
          </div>

          <div>
            <FormInput
              v-model="form.rise"
              :label="__('Rise (m)')"
              type="number"
              min="0.01"
              step="0.01"
              placeholder="0.18"
            />
            <p v-if="errors.rise" class="mt-1 text-xs text-red-500">
              {{ errors.rise }}
            </p>
          </div>

          <div>
            <FormInput
              v-model="form.run"
              :label="__('Run (m)')"
              type="number"
              min="0.01"
              step="0.01"
              placeholder="0.28"
            />
            <p v-if="errors.run" class="mt-1 text-xs text-red-500">
              {{ errors.run }}
            </p>
          </div>

          <div>
            <FormInput
              v-model="form.depth"
              :label="__('Thickness (m)')"
              type="number"
              min="0.01"
              step="0.01"
              placeholder="0.15"
            />
            <p v-if="errors.depth" class="mt-1 text-xs text-red-500">
              {{ errors.depth }}
            </p>
          </div>
        </div>

        <Button class="w-full sm:w-auto" @click="submit">
          {{ __("Calculate") }}
        </Button>
      </CardContent>
    </Card>

    <Alert :variant="isSafeRise ? 'default' : 'destructive'">
      <AlertDescription>
        <span v-if="isSafeRise">
          {{ __("Rise is within recommended range (0.14-0.21 m).") }}
        </span>
        <span v-else>
          {{ __("Rise is outside recommended range (0.14-0.21 m).") }}
        </span>
      </AlertDescription>
    </Alert>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <Card
        v-for="item in [
          { label: __('Total height (m)'), value: totalHeight },
          { label: __('Total run (m)'), value: totalRun },
          { label: __('Stringer length (m)'), value: stringerLength },
          { label: __('Angle (deg)'), value: angleDeg },
          { label: __('Total volume (m3)'), value: totalVolume },
          { label: __('Total top area (m2)'), value: totalTopArea },
          { label: __('Total front area (m2)'), value: totalFrontArea },
          { label: __('Step pitch (rise+run)'), value: stepPitch },
        ]"
        :key="item.label"
      >
        <CardContent class="pt-4">
          <p class="text-xs text-muted-foreground">{{ item.label }}</p>
          <p class="mt-1 text-xl font-bold">{{ item.value }}</p>
        </CardContent>
      </Card>
    </div>

    <Separator />

    <Card>
      <CardHeader>
        <CardTitle class="text-sm">{{ __("Per step") }}</CardTitle>
      </CardHeader>
      <CardContent class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
        <div>
          <span class="text-muted-foreground">{{ __("Volume") }}:</span>
          {{ stepVolume }} m3
        </div>
        <div>
          <span class="text-muted-foreground">{{ __("Top area") }}:</span>
          {{ stepTopArea }} m2
        </div>
        <div>
          <span class="text-muted-foreground">{{ __("Front area") }}:</span>
          {{ stepFrontArea }} m2
        </div>
        <div>
          <span class="text-muted-foreground">Rise/Run:</span>
          {{ riseRunRatio }}
        </div>
      </CardContent>
    </Card>
  </div>
</template>
