<script setup lang="ts">
import { reactive, ref, computed } from "vue";
import FormInput from "@/Forms/FormInput.vue";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";

type NullableNumber = number | null;
type NumericLike = number | string | null | undefined;

type CylinderResult = {
  radius: NullableNumber;
  height: NullableNumber;
  diameter: NullableNumber;
  volume: NullableNumber;
  lateralSurface: NullableNumber;
  baseSurface: NullableNumber;
  totalSurface: NullableNumber;
  baseCircumference: NullableNumber;
};

const emit = defineEmits<{
  (
    e: "update:cylinder",
    value: { radius: NullableNumber; height: NullableNumber },
  ): void;
}>();

const note = ref("");
const result = ref<CylinderResult | null>(null);
const inputsHidden = ref(false);

const draft = reactive({
  radius: null as NullableNumber,
  height: null as NullableNumber,
});

const given = reactive({
  diameter: null as NumericLike,
  volume: null as NumericLike,
  lateralSurface: null as NumericLike,
  baseSurface: null as NumericLike,
  totalSurface: null as NumericLike,
  baseCircumference: null as NumericLike,
});

const round = (n: number, d = 4) => Number(n.toFixed(d));

const asPositive = (value: NumericLike): number | null => {
  if (value === null || value === undefined || value === "") return null;
  const p = Number(value);
  return Number.isFinite(p) && p > 0 ? p : null;
};

const sqrtPos = (v: number): number | null => (v >= 0 ? Math.sqrt(v) : null);

function isGood(v: number | null | undefined): v is number {
  return v !== null && v !== undefined && Number.isFinite(v) && v > 0;
}

function runSolver(): { r: NullableNumber; h: NullableNumber } {
  let r = asPositive(draft.radius);
  let h = asPositive(draft.height);

  const D = asPositive(given.diameter);
  const V = asPositive(given.volume);
  const Sl = asPositive(given.lateralSurface);
  const Sb = asPositive(given.baseSurface);
  const St = asPositive(given.totalSurface);
  const C = asPositive(given.baseCircumference);

  for (let iter = 0; iter < 20; iter++) {
    let changed = false;

    if (r === null) {
      let val: number | null = null;
      if (isGood(D)) val = D! / 2;
      else if (isGood(Sb)) val = sqrtPos(Sb! / Math.PI);
      else if (isGood(C)) val = C! / (2 * Math.PI);
      else if (isGood(h) && isGood(V)) val = sqrtPos(V! / (Math.PI * h!));
      else if (isGood(h) && isGood(Sl)) val = Sl! / (2 * Math.PI * h!);
      else if (isGood(h) && isGood(St)) {
        const a = 2 * Math.PI,
          b = 2 * Math.PI * h!,
          c = -St!;
        val = sqrtPos(b ** 2 - 4 * a * c);
        if (val !== null) val = (-b + val) / (2 * a);
      }
      if (isGood(val)) {
        r = val!;
        changed = true;
      }
    }

    if (h === null) {
      let val: number | null = null;
      if (isGood(r) && isGood(V)) val = V! / (Math.PI * r! ** 2);
      else if (isGood(r) && isGood(Sl)) val = Sl! / (2 * Math.PI * r!);
      else if (isGood(r) && isGood(St)) val = St! / (2 * Math.PI * r!) - r!;
      if (isGood(val)) {
        h = val!;
        changed = true;
      }
    }

    if (!changed) break;
  }

  return { r, h };
}

function buildResult(r: NullableNumber, h: NullableNumber): CylinderResult {
  const givenD = asPositive(given.diameter);
  const givenV = asPositive(given.volume);
  const givenSl = asPositive(given.lateralSurface);
  const givenSb = asPositive(given.baseSurface);
  const givenSt = asPositive(given.totalSurface);
  const givenC = asPositive(given.baseCircumference);

  const baseSurface: NullableNumber = isGood(r) ? Math.PI * r! ** 2 : givenSb;
  const lateralSurface: NullableNumber =
    isGood(r) && isGood(h) ? 2 * Math.PI * r! * h! : givenSl;
  const totalSurface: NullableNumber =
    isGood(baseSurface) && isGood(lateralSurface)
      ? 2 * baseSurface! + lateralSurface!
      : givenSt;

  const rr = (v: NullableNumber) => (v !== null ? round(v) : null);

  return {
    radius: rr(r),
    height: rr(h),
    diameter: rr(isGood(r) ? r! * 2 : givenD),
    volume: rr(isGood(r) && isGood(h) ? Math.PI * r! ** 2 * h! : givenV),
    lateralSurface: rr(lateralSurface),
    baseSurface: rr(baseSurface),
    totalSurface: rr(totalSurface),
    baseCircumference: rr(isGood(r) ? 2 * Math.PI * r! : givenC),
  };
}

const resultHasData = computed(
  () =>
    result.value !== null &&
    Object.values(result.value).some((v) => v !== null),
);

function handleCalculate() {
  note.value = "";
  const { r, h } = runSolver();
  result.value = buildResult(r, h);
  inputsHidden.value = true;

  const allSolved = r !== null && h !== null;
  if (!allSolved) {
    const missing = [r === null && "r", h === null && "h"]
      .filter(Boolean)
      .join(", ");
    note.value = `${missing} անհայտ մնաց — ցուցադրված են հաշվելի բոլոր տվյալները:`;
  } else {
    draft.radius = round(r!);
    draft.height = round(h!);
    emit("update:cylinder", { radius: round(r!), height: round(h!) });
  }
}

function clearGiven() {
  given.diameter =
    given.volume =
    given.lateralSurface =
    given.baseSurface =
    given.totalSurface =
    given.baseCircumference =
      null;
  result.value = null;
  note.value = "";
  inputsHidden.value = false;
}

function clearAll() {
  draft.radius = draft.height = null;
  result.value = null;
  note.value = "";
  inputsHidden.value = false;
  emit("update:cylinder", { radius: null, height: null });
  clearGiven();
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-end gap-2">
      <Button variant="outline" @click="clearGiven"
        >Մաքրել տրված տվյալները</Button
      >
      <Button variant="outline" @click="clearAll">Մաքրել բոլորը</Button>
      <Button v-if="!inputsHidden" @click="handleCalculate">Հաշվել</Button>
    </div>

    <Alert
      v-if="note"
      class="border-chart-3/30 bg-chart-3/10 text-chart-3 dark:border-chart-3 dark:bg-chart-3/20 dark:text-chart-3"
    >
      <AlertDescription>⚠️ {{ note }}</AlertDescription>
    </Alert>

    <div v-if="!inputsHidden" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
      <Card>
        <CardHeader class="pb-3"
          ><CardTitle class="text-sm">Չափեր</CardTitle></CardHeader
        >
        <CardContent class="space-y-3">
          <FormInput
            :model-value="draft.radius"
            type="number"
            step="any"
            label="Շառավիղ (r)"
            @update:model-value="(v: any) => (draft.radius = asPositive(v))"
          />
          <FormInput
            :model-value="draft.height"
            type="number"
            step="any"
            label="Բարձրություն (h)"
            @update:model-value="(v: any) => (draft.height = asPositive(v))"
          />
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="pb-3"
          ><CardTitle class="text-sm">Տրված տվյալներ</CardTitle></CardHeader
        >
        <CardContent class="space-y-3">
          <FormInput
            v-model="given.diameter"
            type="number"
            step="any"
            label="Տրամագիծ (D)"
          />
          <FormInput
            v-model="given.volume"
            type="number"
            step="any"
            label="Ծավալ (V)"
          />
          <FormInput
            v-model="given.lateralSurface"
            type="number"
            step="any"
            label="Կողային մակ. (Sₗ)"
          />
          <FormInput
            v-model="given.baseSurface"
            type="number"
            step="any"
            label="Հիմքի մակ. (Sᵦ)"
          />
          <FormInput
            v-model="given.totalSurface"
            type="number"
            step="any"
            label="Ընդհ. մակ. (S)"
          />
          <FormInput
            v-model="given.baseCircumference"
            type="number"
            step="any"
            label="Հիմքի շրջ. (C)"
          />
        </CardContent>
      </Card>
    </div>

    <Card v-if="resultHasData">
      <CardHeader><CardTitle>📐 Արդյունք</CardTitle></CardHeader>
      <CardContent class="space-y-6">
        <div>
          <p
            class="mb-3 text-xs font-semibold uppercase tracking-widest text-muted-foreground"
          >
            Չափեր
          </p>
          <div class="grid gap-3 sm:grid-cols-3">
            <div
              v-for="item in [
                { label: 'Շառավիղ (r)', val: result!.radius },
                { label: 'Բարձրություն (h)', val: result!.height },
                { label: 'Տրամագիծ (D)', val: result!.diameter },
              ]"
              :key="item.label"
              class="rounded-lg border p-3 transition-opacity"
              :class="item.val !== null ? 'bg-muted/30' : 'opacity-35'"
            >
              <p class="text-xs text-muted-foreground">{{ item.label }}</p>
              <p class="text-lg font-bold">{{ item.val ?? "—" }}</p>
            </div>
          </div>
        </div>

        <Separator />

        <div>
          <p
            class="mb-3 text-xs font-semibold uppercase tracking-widest text-muted-foreground"
          >
            Հատկություններ
          </p>
          <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div
              v-for="item in [
                { label: 'Ծավալ (V)', val: result!.volume },
                { label: 'Կողային մակ. (Sₗ)', val: result!.lateralSurface },
                { label: 'Հիմքի մակ. (Sᵦ)', val: result!.baseSurface },
                { label: 'Ընդհ. մակ. (S)', val: result!.totalSurface },
              ]"
              :key="item.label"
              class="rounded-lg border p-3 transition-opacity"
              :class="item.val !== null ? 'bg-primary/5' : 'opacity-35'"
            >
              <p class="text-xs text-muted-foreground">{{ item.label }}</p>
              <p class="text-lg font-bold">{{ item.val ?? "—" }}</p>
            </div>
          </div>
        </div>

        <Separator />

        <div>
          <p
            class="mb-3 text-xs font-semibold uppercase tracking-widest text-muted-foreground"
          >
            Հիմք
          </p>
          <div class="grid gap-3 sm:grid-cols-4">
            <div
              class="rounded-lg border p-3 transition-opacity"
              :class="result!.baseCircumference !== null ? '' : 'opacity-35'"
            >
              <p class="text-xs text-muted-foreground">Հիմքի շրջ. (C)</p>
              <p class="text-lg font-bold">
                {{ result!.baseCircumference ?? "—" }}
              </p>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
