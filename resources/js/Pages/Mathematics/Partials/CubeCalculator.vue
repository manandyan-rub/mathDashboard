<script setup lang="ts">
import { reactive, ref, computed, watch } from "vue";
import FormInput from "@/Forms/FormInput.vue";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import { cmsTranslate } from "@/lib/i18n";

type NullableNumber = number | null;
type NumericLike = number | string | null | undefined;

type Dimensions = {
  length: NullableNumber;
  width: NullableNumber;
  height: NullableNumber;
};

type PartialResult = {
  length: NullableNumber;
  width: NullableNumber;
  height: NullableNumber;
  volume: NullableNumber;
  surfaceArea: NullableNumber;
  lateralSurface: NullableNumber;
  edgeSum: NullableNumber;
  spaceDiagonal: NullableNumber;
  radiusOfCircumscribedSphere: NullableNumber;
  faceAreas: { lw: NullableNumber; lh: NullableNumber; wh: NullableNumber };
  faceDiagonals: { lw: NullableNumber; lh: NullableNumber; wh: NullableNumber };
  facePerimeters: {
    lw: NullableNumber;
    lh: NullableNumber;
    wh: NullableNumber;
  };
};

const props = defineProps<{ dimensions: Dimensions }>();

const emit = defineEmits<{
  (e: "update:dimensions", value: Dimensions): void;
  (e: "submit", value: Dimensions): void;
}>();

const note = ref("");
const result = ref<PartialResult | null>(null);

const draftDimensions = reactive<Dimensions>({
  length: props.dimensions.length,
  width: props.dimensions.width,
  height: props.dimensions.height,
});

const given = reactive({
  volume: null as NumericLike,
  surfaceArea: null as NumericLike,
  spaceDiagonal: null as NumericLike,
  radiusOfCircumscribedSphere: null as NumericLike,
  faceAreas: {
    lw: null as NumericLike,
    lh: null as NumericLike,
    wh: null as NumericLike,
  },
  faceDiagonals: {
    lw: null as NumericLike,
    lh: null as NumericLike,
    wh: null as NumericLike,
  },
  facePerimeters: {
    lw: null as NumericLike,
    lh: null as NumericLike,
    wh: null as NumericLike,
  },
});

const round = (num: number, digits = 4) => Number(num.toFixed(digits));

const asPositive = (value: NumericLike): number | null => {
  if (value === null || value === undefined || value === "") return null;
  const parsed = Number(value);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
};

const sqrtPos = (v: number): number | null => (v >= 0 ? Math.sqrt(v) : null);

function firstValid(
  ...values: Array<number | null | undefined>
): number | null {
  for (const v of values)
    if (v !== null && v !== undefined && Number.isFinite(v) && v > 0) return v;
  return null;
}

function runSolver(): {
  l: NullableNumber;
  w: NullableNumber;
  h: NullableNumber;
} {
  let l = asPositive(draftDimensions.length);
  let w = asPositive(draftDimensions.width);
  let h = asPositive(draftDimensions.height);

  const V = asPositive(given.volume);
  const S = asPositive(given.surfaceArea);
  const D = asPositive(given.spaceDiagonal);
  const R = asPositive(given.radiusOfCircumscribedSphere);
  const fd = D ?? (R ? 2 * R : null);
  const A_lw = asPositive(given.faceAreas.lw);
  const A_lh = asPositive(given.faceAreas.lh);
  const A_wh = asPositive(given.faceAreas.wh);
  const d_lw = asPositive(given.faceDiagonals.lw);
  const d_lh = asPositive(given.faceDiagonals.lh);
  const d_wh = asPositive(given.faceDiagonals.wh);
  const p_lw = asPositive(given.facePerimeters.lw);
  const p_lh = asPositive(given.facePerimeters.lh);
  const p_wh = asPositive(given.facePerimeters.wh);

  for (let iter = 0; iter < 20; iter++) {
    let changed = false;

    if (l === null) {
      const c = firstValid(
        w && h && V ? V / (w * h) : null,
        w && h && S ? (S / 2 - w * h) / (w + h) : null,
        w && h && fd ? sqrtPos(fd ** 2 - w ** 2 - h ** 2) : null,
        w && A_lw ? A_lw / w : null,
        h && A_lh ? A_lh / h : null,
        w && d_lw ? sqrtPos(d_lw ** 2 - w ** 2) : null,
        h && d_lh ? sqrtPos(d_lh ** 2 - h ** 2) : null,
        w && p_lw ? p_lw / 2 - w : null,
        h && p_lh ? p_lh / 2 - h : null,
      );
      if (c) {
        l = c;
        changed = true;
      }
    }
    if (w === null) {
      const c = firstValid(
        l && h && V ? V / (l * h) : null,
        l && h && S ? (S / 2 - l * h) / (l + h) : null,
        l && h && fd ? sqrtPos(fd ** 2 - l ** 2 - h ** 2) : null,
        l && A_lw ? A_lw / l : null,
        h && A_wh ? A_wh / h : null,
        l && d_lw ? sqrtPos(d_lw ** 2 - l ** 2) : null,
        h && d_wh ? sqrtPos(d_wh ** 2 - h ** 2) : null,
        l && p_lw ? p_lw / 2 - l : null,
        h && p_wh ? p_wh / 2 - h : null,
      );
      if (c) {
        w = c;
        changed = true;
      }
    }
    if (h === null) {
      const c = firstValid(
        l && w && V ? V / (l * w) : null,
        l && w && S ? (S / 2 - l * w) / (l + w) : null,
        l && w && fd ? sqrtPos(fd ** 2 - l ** 2 - w ** 2) : null,
        l && A_lh ? A_lh / l : null,
        w && A_wh ? A_wh / w : null,
        l && d_lh ? sqrtPos(d_lh ** 2 - l ** 2) : null,
        w && d_wh ? sqrtPos(d_wh ** 2 - w ** 2) : null,
        l && p_lh ? p_lh / 2 - l : null,
        w && p_wh ? p_wh / 2 - w : null,
      );
      if (c) {
        h = c;
        changed = true;
      }
    }

    if (l && !w && !h) {
      if (A_lw && A_lh) {
        w = A_lw / l;
        h = A_lh / l;
        changed = true;
      } else if (A_lw && V) {
        w = A_lw / l;
        h = V / A_lw;
        changed = true;
      } else if (A_lh && V) {
        w = V / A_lh;
        h = A_lh / l;
        changed = true;
      } else if (d_lw && d_lh) {
        const ww = sqrtPos(d_lw ** 2 - l ** 2),
          hh = sqrtPos(d_lh ** 2 - l ** 2);
        if (ww && hh) {
          w = ww;
          h = hh;
          changed = true;
        }
      } else if (p_lw && p_lh) {
        const ww = p_lw / 2 - l,
          hh = p_lh / 2 - l;
        if (ww > 0 && hh > 0) {
          w = ww;
          h = hh;
          changed = true;
        }
      }
    }
    if (w && !l && !h) {
      if (A_lw && A_wh) {
        l = A_lw / w;
        h = A_wh / w;
        changed = true;
      } else if (A_lw && V) {
        l = A_lw / w;
        h = V / A_lw;
        changed = true;
      } else if (A_wh && V) {
        l = V / A_wh;
        h = A_wh / w;
        changed = true;
      } else if (d_lw && d_wh) {
        const ll = sqrtPos(d_lw ** 2 - w ** 2),
          hh = sqrtPos(d_wh ** 2 - w ** 2);
        if (ll && hh) {
          l = ll;
          h = hh;
          changed = true;
        }
      } else if (p_lw && p_wh) {
        const ll = p_lw / 2 - w,
          hh = p_wh / 2 - w;
        if (ll > 0 && hh > 0) {
          l = ll;
          h = hh;
          changed = true;
        }
      }
    }
    if (h && !l && !w) {
      if (A_lh && A_wh) {
        l = A_lh / h;
        w = A_wh / h;
        changed = true;
      } else if (A_lh && V) {
        l = A_lh / h;
        w = V / A_lh;
        changed = true;
      } else if (A_wh && V) {
        l = V / A_wh;
        w = A_wh / h;
        changed = true;
      } else if (d_lh && d_wh) {
        const ll = sqrtPos(d_lh ** 2 - h ** 2),
          ww = sqrtPos(d_wh ** 2 - h ** 2);
        if (ll && ww) {
          l = ll;
          w = ww;
          changed = true;
        }
      } else if (p_lh && p_wh) {
        const ll = p_lh / 2 - h,
          ww = p_wh / 2 - h;
        if (ll > 0 && ww > 0) {
          l = ll;
          w = ww;
          changed = true;
        }
      }
    }
    if (!l && !w && !h) {
      if (A_lw && A_lh && A_wh) {
        const ll = sqrtPos((A_lw * A_lh) / A_wh),
          ww = sqrtPos((A_lw * A_wh) / A_lh),
          hh = sqrtPos((A_lh * A_wh) / A_lw);
        if (ll && ww && hh) {
          l = ll;
          w = ww;
          h = hh;
          changed = true;
        }
      } else if (V && A_lw && A_lh) {
        l = (A_lw * A_lh) / V;
        w = V / A_lh;
        h = V / A_lw;
        changed = true;
      } else if (V && A_lw && A_wh) {
        l = V / A_wh;
        w = (A_lw * A_wh) / V;
        h = V / A_lw;
        changed = true;
      } else if (V && A_lh && A_wh) {
        l = V / A_wh;
        w = V / A_lh;
        h = (A_lh * A_wh) / V;
        changed = true;
      }
    }

    if (!changed) break;
  }

  return { l, w, h };
}

function buildPartialResult(
  l: NullableNumber,
  w: NullableNumber,
  h: NullableNumber,
): PartialResult {
  const givenV = asPositive(given.volume);
  const givenS = asPositive(given.surfaceArea);
  const givenD = asPositive(given.spaceDiagonal);
  const givenR = asPositive(given.radiusOfCircumscribedSphere);
  const givenAlw = asPositive(given.faceAreas.lw);
  const givenAlh = asPositive(given.faceAreas.lh);
  const givenAwh = asPositive(given.faceAreas.wh);
  const givenDlw = asPositive(given.faceDiagonals.lw);
  const givenDlh = asPositive(given.faceDiagonals.lh);
  const givenDwh = asPositive(given.faceDiagonals.wh);
  const givenPlw = asPositive(given.facePerimeters.lw);
  const givenPlh = asPositive(given.facePerimeters.lh);
  const givenPwh = asPositive(given.facePerimeters.wh);

  const fa_lw: NullableNumber = l && w ? l * w : givenAlw;
  const fa_lh: NullableNumber = l && h ? l * h : givenAlh;
  const fa_wh: NullableNumber = w && h ? w * h : givenAwh;

  const volume: NullableNumber =
    l && w && h
      ? l * w * h
      : fa_lw && h
        ? fa_lw * h
        : fa_lh && w
          ? fa_lh * w
          : fa_wh && l
            ? fa_wh * l
            : givenV;
  const surfaceArea: NullableNumber =
    fa_lw !== null && fa_lh !== null && fa_wh !== null
      ? 2 * (fa_lw + fa_lh + fa_wh)
      : givenS;
  const lateralSurface: NullableNumber = h && l && w ? 2 * h * (l + w) : null;
  const edgeSum: NullableNumber = l && w && h ? 4 * (l + w + h) : null;
  const spaceDiagonal: NullableNumber =
    l && w && h
      ? Math.sqrt(l ** 2 + w ** 2 + h ** 2)
      : (givenD ?? (givenR ? givenR * 2 : null));
  const radiusOfCircumscribedSphere: NullableNumber = spaceDiagonal
    ? spaceDiagonal / 2
    : givenR;
  const fd_lw: NullableNumber = l && w ? Math.sqrt(l ** 2 + w ** 2) : givenDlw;
  const fd_lh: NullableNumber = l && h ? Math.sqrt(l ** 2 + h ** 2) : givenDlh;
  const fd_wh: NullableNumber = w && h ? Math.sqrt(w ** 2 + h ** 2) : givenDwh;
  const fp_lw: NullableNumber = l && w ? 2 * (l + w) : givenPlw;
  const fp_lh: NullableNumber = l && h ? 2 * (l + h) : givenPlh;
  const fp_wh: NullableNumber = w && h ? 2 * (w + h) : givenPwh;

  const r = (v: NullableNumber) => (v !== null ? round(v) : null);

  return {
    length: r(l),
    width: r(w),
    height: r(h),
    volume: r(volume),
    surfaceArea: r(surfaceArea),
    lateralSurface: r(lateralSurface),
    edgeSum: r(edgeSum),
    spaceDiagonal: r(spaceDiagonal),
    radiusOfCircumscribedSphere: r(radiusOfCircumscribedSphere),
    faceAreas: { lw: r(fa_lw), lh: r(fa_lh), wh: r(fa_wh) },
    faceDiagonals: { lw: r(fd_lw), lh: r(fd_lh), wh: r(fd_wh) },
    facePerimeters: { lw: r(fp_lw), lh: r(fp_lh), wh: r(fp_wh) },
  };
}

const resultHasData = computed(() => {
  if (!result.value) return false;
  const r = result.value;
  return [
    r.length,
    r.width,
    r.height,
    r.volume,
    r.surfaceArea,
    r.lateralSurface,
    r.edgeSum,
    r.spaceDiagonal,
    r.radiusOfCircumscribedSphere,
    r.faceAreas.lw,
    r.faceAreas.lh,
    r.faceAreas.wh,
    r.faceDiagonals.lw,
    r.faceDiagonals.lh,
    r.faceDiagonals.wh,
    r.facePerimeters.lw,
    r.facePerimeters.lh,
    r.facePerimeters.wh,
  ].some((v) => v !== null);
});

function handleCalculate() {
  note.value = "";
  const { l, w, h } = runSolver();
  result.value = buildPartialResult(l, w, h);

  const allSolved = l !== null && w !== null && h !== null;
  if (!allSolved) {
    const missing = (["l", "w", "h"] as const)
      .filter((k) => ({ l, w, h })[k] === null)
      .join(", ");
    note.value = cmsTranslate("Unknown values left: :missing. All computable results are shown.", {
      missing,
    });
  } else {
    const dims = { length: round(l!), width: round(w!), height: round(h!) };
    draftDimensions.length = dims.length;
    draftDimensions.width = dims.width;
    draftDimensions.height = dims.height;
    emit("update:dimensions", dims);
    emit("submit", dims);
  }
}

function clearAll() {
  draftDimensions.length =
    draftDimensions.width =
    draftDimensions.height =
      null;
  result.value = null;
  note.value = "";
  emit("update:dimensions", { length: null, width: null, height: null });
  emit("submit", { length: null, width: null, height: null });
}

watch(
  () => props.dimensions,
  (v) => {
    draftDimensions.length = v.length;
    draftDimensions.width = v.width;
    draftDimensions.height = v.height;
  },
  { deep: true },
);
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-end gap-2">
      <Button variant="outline" @click="clearAll">{{ __("Clear all") }}</Button>
      <Button @click="handleCalculate">{{ __("Calculate") }}</Button>
    </div>

    <Alert
      v-if="note"
      class="border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200"
    >
      <AlertDescription>⚠️ {{ note }}</AlertDescription>
    </Alert>

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
      <Card>
        <CardHeader class="pb-3"
          ><CardTitle class="text-sm">{{ __("Dimensions (l, w, h)") }}</CardTitle></CardHeader
        >
        <CardContent class="space-y-3">
          <FormInput
            :model-value="draftDimensions.length"
            type="number"
            step="any"
            :label="__('Length (l)')"
            @update:model-value="
              (v: any) => (draftDimensions.length = asPositive(v))
            "
          />
          <FormInput
            :model-value="draftDimensions.width"
            type="number"
            step="any"
            :label="__('Width (w)')"
            @update:model-value="
              (v: any) => (draftDimensions.width = asPositive(v))
            "
          />
          <FormInput
            :model-value="draftDimensions.height"
            type="number"
            step="any"
            :label="__('Height (h)')"
            @update:model-value="
              (v: any) => (draftDimensions.height = asPositive(v))
            "
          />
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="pb-3"
          ><CardTitle class="text-sm">{{ __("Given data") }}</CardTitle></CardHeader
        >
        <CardContent class="space-y-3">
          <FormInput
            v-model="given.volume"
            type="number"
            step="any"
            :label="__('Volume (V)')"
          />
          <FormInput
            v-model="given.surfaceArea"
            type="number"
            step="any"
            :label="__('Total surface (S)')"
          />
          <FormInput
            v-model="given.spaceDiagonal"
            type="number"
            step="any"
            :label="__('Space diagonal (d)')"
          />
          <FormInput
            v-model="given.radiusOfCircumscribedSphere"
            type="number"
            step="any"
            :label="__('Circumscribed sphere radius (R)')"
          />
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="pb-3"
          ><CardTitle class="text-sm">{{ __("Face areas") }}</CardTitle></CardHeader
        >
        <CardContent class="space-y-3">
          <FormInput
            v-model="given.faceAreas.lw"
            type="number"
            step="any"
            :label="__('lw face')"
          />
          <FormInput
            v-model="given.faceAreas.lh"
            type="number"
            step="any"
            :label="__('lh face')"
          />
          <FormInput
            v-model="given.faceAreas.wh"
            type="number"
            step="any"
            :label="__('wh face')"
          />
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="pb-3"
          ><CardTitle class="text-sm">{{ __("Diagonals") }}</CardTitle></CardHeader
        >
        <CardContent class="space-y-3">
          <FormInput
            v-model="given.faceDiagonals.lw"
            type="number"
            step="any"
            :label="__('lw face')"
          />
          <FormInput
            v-model="given.faceDiagonals.lh"
            type="number"
            step="any"
            :label="__('lh face')"
          />
          <FormInput
            v-model="given.faceDiagonals.wh"
            type="number"
            step="any"
            :label="__('wh face')"
          />
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="pb-3"
          ><CardTitle class="text-sm">{{ __("Perimeters") }}</CardTitle></CardHeader
        >
        <CardContent class="space-y-3">
          <FormInput
            v-model="given.facePerimeters.lw"
            type="number"
            step="any"
            :label="__('lw face')"
          />
          <FormInput
            v-model="given.facePerimeters.lh"
            type="number"
            step="any"
            :label="__('lh face')"
          />
          <FormInput
            v-model="given.facePerimeters.wh"
            type="number"
            step="any"
            :label="__('wh face')"
          />
        </CardContent>
      </Card>
    </div>

    <Card v-if="resultHasData">
      <CardHeader>
        <CardTitle>📐 {{ __("Result") }}</CardTitle>
      </CardHeader>
      <CardContent class="space-y-6">
        <div>
          <p
            class="mb-3 text-xs font-semibold uppercase tracking-widest text-muted-foreground"
          >
            {{ __("Dimensions") }}
          </p>
          <div class="grid gap-3 sm:grid-cols-3">
            <div
              v-for="item in [
                { label: __('Length (l)'), val: result!.length },
                { label: __('Width (w)'), val: result!.width },
                { label: __('Height (h)'), val: result!.height },
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
            {{ __("Main properties") }}
          </p>
          <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div
              v-for="item in [
                { label: __('Volume (V)'), val: result!.volume },
                { label: __('Total surface (S)'), val: result!.surfaceArea },
                { label: __('Lateral surface (Sl)'), val: result!.lateralSurface },
                { label: __('Edge sum'), val: result!.edgeSum },
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
            {{ __("Diagonals") }}
          </p>
          <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div
              v-for="item in [
                { label: __('Space diagonal (d)'), val: result!.spaceDiagonal },
                { label: __('lw face'), val: result!.faceDiagonals.lw },
                { label: __('lh face'), val: result!.faceDiagonals.lh },
                { label: __('wh face'), val: result!.faceDiagonals.wh },
              ]"
              :key="item.label"
              class="rounded-lg border p-3 transition-opacity"
              :class="item.val !== null ? '' : 'opacity-35'"
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
            {{ __("Circumscribed sphere radius") }}
          </p>
          <div class="grid gap-3 sm:grid-cols-4">
            <div
              class="rounded-lg border p-3 transition-opacity"
              :class="
                result!.radiusOfCircumscribedSphere !== null ? '' : 'opacity-35'
              "
            >
              <p class="text-xs text-muted-foreground">R</p>
              <p class="text-lg font-bold">
                {{ result!.radiusOfCircumscribedSphere ?? "—" }}
              </p>
            </div>
          </div>
        </div>

        <Separator />

        <div>
          <p
            class="mb-3 text-xs font-semibold uppercase tracking-widest text-muted-foreground"
          >
            {{ __("Face areas") }}
          </p>
          <div class="grid gap-3 sm:grid-cols-3">
            <div
              v-for="item in [
                { label: __('lw face'), val: result!.faceAreas.lw },
                { label: __('lh face'), val: result!.faceAreas.lh },
                { label: __('wh face'), val: result!.faceAreas.wh },
              ]"
              :key="item.label"
              class="rounded-lg border p-3 transition-opacity"
              :class="item.val !== null ? '' : 'opacity-35'"
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
            {{ __("Face perimeters") }}
          </p>
          <div class="grid gap-3 sm:grid-cols-3">
            <div
              v-for="item in [
                { label: __('lw face'), val: result!.facePerimeters.lw },
                { label: __('lh face'), val: result!.facePerimeters.lh },
                { label: __('wh face'), val: result!.facePerimeters.wh },
              ]"
              :key="item.label"
              class="rounded-lg border p-3 transition-opacity"
              :class="item.val !== null ? '' : 'opacity-35'"
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
