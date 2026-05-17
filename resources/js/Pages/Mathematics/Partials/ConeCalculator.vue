<script setup lang="ts">
import { reactive, ref, computed } from "vue";
import FormInput from "@/Forms/FormInput.vue";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";

type NullableNumber = number | null;
type NumericLike = number | string | null | undefined;

type ConeResult = {
  radius: NullableNumber;
  height: NullableNumber;
  slantHeight: NullableNumber;
  volume: NullableNumber;
  lateralSurface: NullableNumber;
  baseSurface: NullableNumber;
  totalSurface: NullableNumber;
};

const emit = defineEmits<{
  (
    e: "update:cone",
    value: { radius: NullableNumber; height: NullableNumber },
  ): void;
}>();

const note = ref("");
const result = ref<ConeResult | null>(null);
const inputsHidden = ref(false);

const draft = reactive({
  radius: null as NullableNumber,
  height: null as NullableNumber,
});

const given = reactive({
  slantHeight: null as NumericLike,
  volume: null as NumericLike,
  lateralSurface: null as NumericLike,
  baseSurface: null as NumericLike,
  totalSurface: null as NumericLike,
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

  const l = asPositive(given.slantHeight);
  const V = asPositive(given.volume);
  const Sl = asPositive(given.lateralSurface);
  const Sb = asPositive(given.baseSurface);
  const St = asPositive(given.totalSurface);

  for (let iter = 0; iter < 20; iter++) {
    let changed = false;

    if (r === null) {
      let val: number | null = null;
      if (isGood(Sb)) val = sqrtPos(Sb / Math.PI);
      else if (isGood(h) && isGood(l)) val = sqrtPos(l ** 2 - h ** 2);
      else if (isGood(h) && isGood(V)) val = sqrtPos((3 * V) / (Math.PI * h));
      else if (isGood(h) && isGood(Sl)) {
        let rr = 1;
        for (let k = 0; k < 50; k++) {
          const ll = Math.sqrt(rr ** 2 + h ** 2);
          const f = Math.PI * rr * ll - Sl;
          const df = Math.PI * (ll + rr ** 2 / ll);
          const nr = rr - f / df;
          if (Math.abs(nr - rr) < 1e-10) {
            rr = nr;
            break;
          }
          rr = Math.max(nr, 1e-9);
        }
        val = rr > 0 ? rr : null;
      } else if (isGood(h) && isGood(St)) {
        let rr = 1;
        for (let k = 0; k < 50; k++) {
          const ll = Math.sqrt(rr ** 2 + h ** 2);
          const f = Math.PI * rr * (rr + ll) - St;
          const df = Math.PI * (2 * rr + ll + rr ** 2 / ll);
          const nr = rr - f / df;
          if (Math.abs(nr - rr) < 1e-10) {
            rr = nr;
            break;
          }
          rr = Math.max(nr, 1e-9);
        }
        val = rr > 0 ? rr : null;
      }
      if (isGood(val)) {
        r = val!;
        changed = true;
      }
    }

    if (h === null) {
      let val: number | null = null;
      if (isGood(r) && isGood(l)) val = sqrtPos(l ** 2 - r ** 2);
      else if (isGood(r) && isGood(V)) val = (3 * V) / (Math.PI * r ** 2);
      else if (isGood(r) && isGood(Sl)) {
        const ll = Sl / (Math.PI * r);
        val = sqrtPos(ll ** 2 - r ** 2);
      } else if (isGood(r) && isGood(St)) {
        const ll = St / (Math.PI * r) - r;
        val = sqrtPos(ll ** 2 - r ** 2);
      }
      if (isGood(val)) {
        h = val!;
        changed = true;
      }
    }

    if (!changed) break;
  }

  return { r, h };
}

function buildResult(r: NullableNumber, h: NullableNumber): ConeResult {
  const givenL = asPositive(given.slantHeight);
  const givenV = asPositive(given.volume);
  const givenSl = asPositive(given.lateralSurface);
  const givenSb = asPositive(given.baseSurface);
  const givenSt = asPositive(given.totalSurface);

  const slantHeight: NullableNumber =
    isGood(r) && isGood(h) ? Math.sqrt(r! ** 2 + h! ** 2) : givenL;
  const baseSurface: NullableNumber = isGood(r) ? Math.PI * r! ** 2 : givenSb;
  const lateralSurface: NullableNumber =
    isGood(r) && isGood(slantHeight) ? Math.PI * r! * slantHeight! : givenSl;
  const totalSurface: NullableNumber =
    isGood(baseSurface) && isGood(lateralSurface)
      ? baseSurface! + lateralSurface!
      : givenSt;
  const volume: NullableNumber =
    isGood(r) && isGood(h) ? (1 / 3) * Math.PI * r! ** 2 * h! : givenV;

  const rr = (v: NullableNumber) => (v !== null ? round(v) : null);

  return {
    radius: rr(r),
    height: rr(h),
    slantHeight: rr(slantHeight),
    volume: rr(volume),
    lateralSurface: rr(lateralSurface),
    baseSurface: rr(baseSurface),
    totalSurface: rr(totalSurface),
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
    emit("update:cone", { radius: round(r!), height: round(h!) });
  }
}

function clearGiven() {
  given.slantHeight =
    given.volume =
    given.lateralSurface =
    given.baseSurface =
    given.totalSurface =
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
  emit("update:cone", { radius: null, height: null });
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
            label="Հիմքի շառ. (r)"
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
            v-model="given.slantHeight"
            type="number"
            step="any"
            label="Կողային կող (l)"
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
                { label: 'Հիմքի շառ. (r)', val: result!.radius },
                { label: 'Բարձրություն (h)', val: result!.height },
                { label: 'Կողային կող (l)', val: result!.slantHeight },
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
      </CardContent>
    </Card>
  </div>
</template>
