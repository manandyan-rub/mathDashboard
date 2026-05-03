<script setup lang="ts">
import { reactive, ref, computed } from "vue";
import FormInput from "@/Forms/FormInput.vue";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";

type NullableNumber = number | null;
type NumericLike = number | string | null | undefined;

type SphereResult = {
  radius: NullableNumber;
  diameter: NullableNumber;
  volume: NullableNumber;
  surfaceArea: NullableNumber;
  circumference: NullableNumber;
};

type SolveStep = {
  unknown: string;
  label: string;
  fromWhat: string;
  formula: string;
  substitution: string;
  result: string;
};

const emit = defineEmits<{
  (e: "update:radius", value: NullableNumber): void;
}>();

const note = ref("");
const result = ref<SphereResult | null>(null);
const solveSteps = ref<SolveStep[]>([]);

const draftRadius = ref<NullableNumber>(null);

const given = reactive({
  diameter: null as NumericLike,
  volume: null as NumericLike,
  surfaceArea: null as NumericLike,
  circumference: null as NumericLike,
});

const round = (num: number, digits = 4) => Number(num.toFixed(digits));

const fmt = (n: number | null | undefined) => {
  if (n === null || n === undefined || !Number.isFinite(n)) return "?";
  return String(Number(n.toFixed(4)));
};

const asPositive = (value: NumericLike): number | null => {
  if (value === null || value === undefined || value === "") return null;
  const parsed = Number(value);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
};

function runSolver(): { r: NullableNumber; steps: SolveStep[] } {
  let r = asPositive(draftRadius.value);
  const hadR = r !== null;
  const steps: SolveStep[] = [];

  const D = asPositive(given.diameter);
  const V = asPositive(given.volume);
  const S = asPositive(given.surfaceArea);
  const C = asPositive(given.circumference);

  if (r === null) {
    const candidates = [
      {
        val: D !== null ? D / 2 : null,
        fromWhat: "D",
        formula: "r = D ÷ 2",
        substitution: `r = ${fmt(D)} ÷ 2`,
      },
      {
        val: V !== null ? Math.cbrt((3 * V) / (4 * Math.PI)) : null,
        fromWhat: "V",
        formula: "r = ∛(3V ÷ 4π)",
        substitution: `r = ∛(3 × ${fmt(V)} ÷ 4π)`,
      },
      {
        val: S !== null ? Math.sqrt(S / (4 * Math.PI)) : null,
        fromWhat: "S",
        formula: "r = √(S ÷ 4π)",
        substitution: `r = √(${fmt(S)} ÷ 4π)`,
      },
      {
        val: C !== null ? C / (2 * Math.PI) : null,
        fromWhat: "C",
        formula: "r = C ÷ 2π",
        substitution: `r = ${fmt(C)} ÷ 2π`,
      },
    ];

    for (const c of candidates) {
      if (c.val !== null && c.val > 0 && Number.isFinite(c.val)) {
        r = c.val;
        steps.push({
          unknown: "r",
          label: "Շառավիղ",
          fromWhat: c.fromWhat,
          formula: c.formula,
          substitution: c.substitution,
          result: `r = ${fmt(r)}`,
        });
        break;
      }
    }
  }

  return { r, steps: hadR ? [] : steps };
}

function buildResult(r: NullableNumber): SphereResult {
  const givenD = asPositive(given.diameter);
  const givenV = asPositive(given.volume);
  const givenS = asPositive(given.surfaceArea);
  const givenC = asPositive(given.circumference);

  const rr = (v: NullableNumber) => (v !== null ? round(v) : null);

  return {
    radius: rr(r),
    diameter: rr(r !== null ? r * 2 : givenD),
    volume: rr(r !== null ? (4 / 3) * Math.PI * r ** 3 : givenV),
    surfaceArea: rr(r !== null ? 4 * Math.PI * r ** 2 : givenS),
    circumference: rr(r !== null ? 2 * Math.PI * r : givenC),
  };
}

const resultHasData = computed(
  () =>
    result.value !== null &&
    Object.values(result.value).some((v) => v !== null),
);

function handleCalculate() {
  note.value = "";
  const { r, steps } = runSolver();
  result.value = buildResult(r);
  solveSteps.value = steps;

  if (r === null) {
    note.value = "r անհայտ մնաց — ցուցադրված են հաշվելի բոլոր տվյալները:";
  } else {
    draftRadius.value = round(r);
    emit("update:radius", round(r));
  }
}

function clearGiven() {
  given.diameter =
    given.volume =
    given.surfaceArea =
    given.circumference =
      null;
  note.value = "";
}

function clearAll() {
  draftRadius.value = null;
  result.value = null;
  solveSteps.value = [];
  note.value = "";
  emit("update:radius", null);
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
      <Button @click="handleCalculate">Հաշվել</Button>
    </div>

    <Alert
      v-if="note"
      class="border-chart-3/30 bg-chart-3/10 text-chart-3 dark:border-chart-3 dark:bg-chart-3/20 dark:text-chart-3"
    >
      <AlertDescription>⚠️ {{ note }}</AlertDescription>
    </Alert>

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
      <Card>
        <CardHeader class="pb-3"
          ><CardTitle class="text-sm">Չափ</CardTitle></CardHeader
        >
        <CardContent>
          <FormInput
            :model-value="draftRadius"
            type="number"
            step="any"
            label="Շառավիղ (r)"
            @update:model-value="(v: any) => (draftRadius = asPositive(v))"
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
            v-model="given.surfaceArea"
            type="number"
            step="any"
            label="Մակ. (S)"
          />
          <FormInput
            v-model="given.circumference"
            type="number"
            step="any"
            label="Մեծ շրջ. (C)"
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
          <div class="grid gap-3 sm:grid-cols-2">
            <div
              v-for="item in [
                { label: 'Շառավիղ (r)', val: result!.radius },
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
          <div class="grid gap-3 sm:grid-cols-3">
            <div
              v-for="item in [
                { label: 'Ծավալ (V)', val: result!.volume },
                { label: 'Մակ. (S)', val: result!.surfaceArea },
                { label: 'Մեծ շրջ. (C)', val: result!.circumference },
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
