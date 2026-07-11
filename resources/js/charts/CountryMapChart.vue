<script setup>
import { useTheme } from "@/composables/useTheme";
import { themeColor, themeColorAlpha } from "@/lib/themeColors";
import L from "leaflet";
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from "vue";

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    default: "",
  },
  points: {
    type: Array,
    default: () => [],
  },
  usersLabel: {
    type: String,
    default: "Users",
  },
  claimsLabel: {
    type: String,
    default: "Claims",
  },
  xpLabel: {
    type: String,
    default: "XP",
  },
  loading: {
    type: Boolean,
    default: false,
  },
  emptyText: {
    type: String,
    default: "No map data yet",
  },
  cardClass: {
    type: String,
    default: "",
  },
});

const mapElement = ref(null);
const map = shallowRef(null);
const tileLayer = shallowRef(null);
const markerLayer = shallowRef(null);
const { theme } = useTheme();

const showEmpty = computed(() => !props.loading && props.points.length === 0);

onMounted(async () => {
  await nextTick();
  initializeMap();
});

onBeforeUnmount(() => {
  if (map.value) {
    map.value.remove();
    map.value = null;
  }
});

watch(
  () => props.points,
  async () => {
    await nextTick();
    initializeMap();
    renderMarkers();
    invalidateMap();
  },
  { deep: true },
);

watch(
  () => theme.value,
  async () => {
    await nextTick();
    initializeMap();
    refreshTileLayer();
    renderMarkers();
  },
);

watch(
  () => props.loading,
  async (loading) => {
    if (!loading) {
      await nextTick();
      invalidateMap();
    }
  },
);

function initializeMap() {
  if (map.value || !mapElement.value) {
    return;
  }

  map.value = L.map(mapElement.value, {
    center: [20, 0],
    zoom: 2,
    minZoom: 2,
    maxZoom: 6,
    zoomControl: false,
    scrollWheelZoom: false,
    doubleClickZoom: false,
    boxZoom: false,
    keyboard: false,
    tapHold: false,
    worldCopyJump: true,
  });

  L.control
    .zoom({
      position: "bottomright",
    })
    .addTo(map.value);

  markerLayer.value = L.layerGroup().addTo(map.value);
  refreshTileLayer();
  renderMarkers();
  invalidateMap();
}

function refreshTileLayer() {
  if (!map.value) {
    return;
  }

  tileLayer.value?.remove();

  tileLayer.value = L.tileLayer(tileUrl(), {
    subdomains: "abcd",
    maxZoom: 18,
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
  }).addTo(map.value);
}

function renderMarkers() {
  if (!map.value || !markerLayer.value) {
    return;
  }

  markerLayer.value.clearLayers();

  const bounds = [];

  props.points.forEach((point) => {
    const size = pulseSize(point.users);
    const color = themeColor("--chart-1", "#6366f1");
    const glow = themeColorAlpha("--chart-1", 0.28, "rgba(99,102,241,0.28)");
    const ring = themeColorAlpha("--chart-1", 0.16, "rgba(99,102,241,0.16)");

    const marker = L.marker([point.latitude, point.longitude], {
      icon: L.divIcon({
        className: "country-pulse-wrapper",
        html: `
          <span
            class="leaflet-pulse-icon"
            style="--pulse-size:${size}px; --pulse-color:${color}; --pulse-glow:${glow}; --pulse-ring:${ring};"
          >
            <span class="leaflet-pulse-icon__core"></span>
          </span>
        `,
        iconSize: [size, size],
        iconAnchor: [size / 2, size / 2],
      }),
    });

    marker.bindTooltip(
      `
        <div class="country-map-tooltip">
          <div class="country-map-tooltip__title">${escapeHtml(point.name)} (${escapeHtml(point.iso_code)})</div>
          <div>${escapeHtml(props.usersLabel)}: <strong>${formatNumber(point.users)}</strong></div>
          <div>${escapeHtml(props.claimsLabel)}: <strong>${formatNumber(point.claims)}</strong></div>
          <div>${escapeHtml(props.xpLabel)}: <strong>${formatNumber(point.xp)}</strong></div>
        </div>
      `,
      {
        direction: "top",
        offset: [0, -6],
        opacity: 1,
      },
    );

    marker.addTo(markerLayer.value);
    bounds.push([point.latitude, point.longitude]);
  });

  if (bounds.length === 0) {
    map.value.setView([20, 0], 2);
    return;
  }

  if (bounds.length === 1) {
    map.value.setView(bounds[0], 3);
    return;
  }

  map.value.fitBounds(bounds, {
    padding: [32, 32],
    maxZoom: 4,
  });
}

function invalidateMap() {
  if (!map.value) {
    return;
  }

  requestAnimationFrame(() => {
    map.value?.invalidateSize();
  });
}

function tileUrl() {
  return theme.value === "dark"
    ? "https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png"
    : "https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png";
}

function pulseSize(users) {
  return Math.max(20, Math.min(46, 18 + Math.round(Math.sqrt(Math.max(1, Number(users) || 1)) * 8)));
}

function formatNumber(value) {
  return new Intl.NumberFormat().format(Number(value) || 0);
}

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}
</script>

<template>
  <section
    class="rounded-lg border border-border bg-card p-5 text-card-foreground shadow-sm"
    :class="cardClass"
  >
    <div class="mb-4 flex items-center justify-between gap-4">
      <div>
        <h3 class="text-base font-semibold text-card-foreground">
          {{ title }}
        </h3>
        <p v-if="description" class="text-sm text-muted-foreground">
          {{ description }}
        </p>
      </div>
      <slot name="icon" />
    </div>

    <div class="country-map-frame">
      <div ref="mapElement" class="country-map-canvas" />

      <div
        v-if="props.loading"
        class="country-map-overlay animate-pulse rounded-md bg-muted"
      />

      <div
        v-else-if="showEmpty"
        class="country-map-overlay flex items-center justify-center rounded-md border border-dashed border-border px-6 text-center text-sm text-muted-foreground"
      >
        {{ emptyText }}
      </div>
    </div>
  </section>
</template>

<style scoped>
.country-map-frame {
  position: relative;
  height: 24rem;
  min-height: 24rem;
  overflow: hidden;
  border-radius: var(--radius-lg);
  border: 1px solid var(--color-border);
  background: color-mix(in srgb, var(--color-muted) 45%, transparent);
}

.country-map-canvas {
  width: 100%;
  height: 100%;
}

.country-map-overlay {
  position: absolute;
  inset: 0;
  backdrop-filter: blur(1px);
}

:deep(.leaflet-container) {
  width: 100%;
  height: 100%;
  background: transparent;
  font-family: var(--font-sans);
}

:deep(.leaflet-control-zoom) {
  border: 1px solid var(--color-border) !important;
  box-shadow: 0 8px 18px rgba(15, 23, 42, 0.12) !important;
}

:deep(.leaflet-control-zoom a) {
  background: var(--color-card) !important;
  color: var(--color-card-foreground) !important;
  border-bottom-color: var(--color-border) !important;
}

:deep(.leaflet-control-attribution) {
  background: color-mix(in srgb, var(--color-card) 88%, transparent) !important;
  color: var(--color-muted-foreground) !important;
}

:deep(.leaflet-tooltip) {
  border: 1px solid var(--color-border);
  border-radius: 12px;
  background: color-mix(in srgb, var(--color-card) 94%, transparent);
  color: var(--color-card-foreground);
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.16);
}

:deep(.leaflet-tooltip-top:before) {
  border-top-color: var(--color-card);
}

:deep(.country-map-tooltip__title) {
  margin-bottom: 4px;
  font-weight: 700;
}

:deep(.country-pulse-wrapper) {
  background: transparent;
  border: 0;
}

:deep(.leaflet-pulse-icon) {
  position: relative;
  display: inline-flex;
  width: var(--pulse-size);
  height: var(--pulse-size);
  align-items: center;
  justify-content: center;
}

:deep(.leaflet-pulse-icon::before),
:deep(.leaflet-pulse-icon::after) {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: 999px;
}

:deep(.leaflet-pulse-icon::before) {
  background: var(--pulse-ring);
  border: 1px solid var(--pulse-glow);
  animation: pulse-ring 2.4s ease-out infinite;
}

:deep(.leaflet-pulse-icon::after) {
  inset: 22%;
  background: var(--pulse-color);
  box-shadow: 0 0 0 5px var(--pulse-glow);
}

:deep(.leaflet-pulse-icon__core) {
  position: absolute;
  inset: 35%;
  border-radius: 999px;
  background: white;
  opacity: 0.88;
}

@keyframes pulse-ring {
  0% {
    transform: scale(0.75);
    opacity: 0.72;
  }

  70% {
    transform: scale(1.2);
    opacity: 0.14;
  }

  100% {
    transform: scale(1.32);
    opacity: 0;
  }
}
</style>
