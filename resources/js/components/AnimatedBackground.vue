<script setup lang="ts">
import { useTheme } from "@/composables/useTheme";
import { themeColor } from "@/lib/themeColors";
import { onBeforeUnmount, onMounted, ref, watch } from "vue";

const canvas = ref<HTMLCanvasElement | null>(null);
const { theme } = useTheme();

let THREE: typeof import("three") | null = null;
let renderer: import("three").WebGLRenderer | null = null;
let scene: import("three").Scene | null = null;
let camera: import("three").PerspectiveCamera | null = null;
let group: import("three").Group | null = null;
let animationFrame = 0;
let reducedMotion = false;

const geometries: import("three").BufferGeometry[] = [];
const materials: import("three").Material[] = [];
const waveLines: Array<{
  line: import("three").Line;
  geometry: import("three").BufferGeometry;
  baseY: number;
  amplitude: number;
  frequency: number;
  speed: number;
}> = [];

onMounted(() => {
  reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  void createScene();
  window.addEventListener("resize", resize);
});

onBeforeUnmount(() => {
  window.removeEventListener("resize", resize);
  cancelAnimationFrame(animationFrame);
  geometries.forEach((geometry) => geometry.dispose());
  materials.forEach((material) => material.dispose());
  renderer?.dispose();
});

watch(theme, () => {
  syncColors();
  renderFrame();
});

async function createScene() {
  if (!canvas.value) return;

  const three = await import("three");
  THREE = three;

  try {
    renderer = new three.WebGLRenderer({
      alpha: true,
      antialias: true,
      canvas: canvas.value,
      powerPreference: "low-power",
    });
  } catch {
    return;
  }

  scene = new three.Scene();
  camera = new three.PerspectiveCamera(52, 1, 1, 220);
  camera.position.z = 82;

  group = new three.Group();
  scene.add(group);

  addParticleField();
  addWaveRibbons();
  addLineField();
  addContours();
  syncColors();
  resize();
  animate();
}

function addLineField() {
  const three = THREE;
  if (!group || !three) return;

  const segmentCount = 140;
  const positions = new Float32Array(segmentCount * 6);

  for (let index = 0; index < segmentCount; index += 1) {
    const offset = index * 6;
    const x = (Math.random() - 0.5) * 150;
    const y = (Math.random() - 0.5) * 88;
    const z = (Math.random() - 0.5) * 115;
    const length = 7 + Math.random() * 18;
    const angle = Math.random() * Math.PI * 2;

    positions[offset] = x;
    positions[offset + 1] = y;
    positions[offset + 2] = z;
    positions[offset + 3] = x + Math.cos(angle) * length;
    positions[offset + 4] = y + Math.sin(angle) * length;
    positions[offset + 5] = z + (Math.random() - 0.5) * 8;
  }

  const geometry = new three.BufferGeometry();
  geometry.setAttribute("position", new three.BufferAttribute(positions, 3));

  const material = new three.LineBasicMaterial({
    transparent: true,
    opacity: 0.26,
  });

  geometries.push(geometry);
  materials.push(material);
  group.add(new three.LineSegments(geometry, material));
}

function addParticleField() {
  const three = THREE;
  if (!group || !three) return;

  const particleCount = 520;
  const positions = new Float32Array(particleCount * 3);
  const colors = new Float32Array(particleCount * 3);

  for (let index = 0; index < particleCount; index += 1) {
    const offset = index * 3;
    positions[offset] = (Math.random() - 0.5) * 180;
    positions[offset + 1] = (Math.random() - 0.5) * 105;
    positions[offset + 2] = (Math.random() - 0.5) * 120;

    colors[offset] = 1;
    colors[offset + 1] = 1;
    colors[offset + 2] = 1;
  }

  const geometry = new three.BufferGeometry();
  geometry.setAttribute("position", new three.BufferAttribute(positions, 3));
  geometry.setAttribute("color", new three.BufferAttribute(colors, 3));

  const material = new three.PointsMaterial({
    size: 1.25,
    sizeAttenuation: true,
    transparent: true,
    opacity: 0.58,
    vertexColors: true,
    blending: three.AdditiveBlending,
    depthWrite: false,
  });

  geometries.push(geometry);
  materials.push(material);
  group.add(new three.Points(geometry, material));
}

function addWaveRibbons() {
  const three = THREE;
  if (!group || !three) return;

  [
    { baseY: -31, amplitude: 7, frequency: 0.05, speed: 0.8 },
    { baseY: -13, amplitude: 5, frequency: 0.065, speed: 1.05 },
    { baseY: 8, amplitude: 6, frequency: 0.045, speed: 0.68 },
    { baseY: 29, amplitude: 4, frequency: 0.08, speed: 0.92 },
  ].forEach((wave, index) => {
    const geometry = new three.BufferGeometry();
    const positions = new Float32Array(128 * 3);

    for (let point = 0; point < 128; point += 1) {
      const offset = point * 3;
      const progress = point / 127;
      positions[offset] = -95 + progress * 190;
      positions[offset + 1] = wave.baseY;
      positions[offset + 2] = -54 + index * 18 + Math.sin(progress * 8) * 6;
    }

    geometry.setAttribute("position", new three.BufferAttribute(positions, 3));

    const material = new three.LineBasicMaterial({
      transparent: true,
      opacity: index === 0 ? 0.6 : 0.42,
      blending: three.AdditiveBlending,
      depthWrite: false,
    });
    const line = new three.Line(geometry, material);

    geometries.push(geometry);
    materials.push(material);
    waveLines.push({ line, geometry, ...wave });
    group?.add(line);
  });
}

function addContours() {
  const three = THREE;
  if (!group || !three) return;

  [
    { radius: 32, x: -54, y: 22, z: -16, scaleY: 0.42 },
    { radius: 42, x: 58, y: -25, z: -28, scaleY: 0.34 },
    { radius: 22, x: 22, y: 32, z: -42, scaleY: 0.52 },
  ].forEach((shape, index) => {
    const geometry = contourGeometry(shape.radius);
    if (!geometry) return;

    const material = new three.LineBasicMaterial({
      transparent: true,
      opacity: index === 0 ? 0.34 : 0.24,
    });
    const line = new three.Line(geometry, material);

    line.position.set(shape.x, shape.y, shape.z);
    line.scale.y = shape.scaleY;
    line.rotation.z = index * 0.7;

    geometries.push(geometry);
    materials.push(material);
    group?.add(line);
  });
}

function contourGeometry(radius: number) {
  const three = THREE;
  if (!three) return null;

  const points: import("three").Vector3[] = [];

  for (let index = 0; index <= 96; index += 1) {
    const angle = (index / 96) * Math.PI * 2;
    points.push(
      new three.Vector3(Math.cos(angle) * radius, Math.sin(angle) * radius, 0),
    );
  }

  return new three.BufferGeometry().setFromPoints(points);
}

function syncColors() {
  const three = THREE;
  if (!three) return;

  const primary = themeColor("--primary");
  const green = themeColor("--chart-2");
  const violet = themeColor("--chart-4");
  const accent = themeColor("--chart-5");

  materials.forEach((material, index) => {
    if (material instanceof three.LineBasicMaterial) {
      const colors = [primary, accent, violet, green];
      material.color.set(colors[index % colors.length]);
    }

    if (material instanceof three.PointsMaterial) {
      material.color.set(theme.value === "dark" ? accent : primary);
    }
  });
}

function resize() {
  if (!canvas.value || !renderer || !camera) return;

  const width = canvas.value.clientWidth || window.innerWidth;
  const height = canvas.value.clientHeight || window.innerHeight;

  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.5));
  renderer.setSize(width, height, false);
  camera.aspect = width / Math.max(1, height);
  camera.updateProjectionMatrix();
  renderFrame();
}

function animate() {
  renderFrame();

  if (!reducedMotion) {
    animationFrame = requestAnimationFrame(animate);
  }
}

function renderFrame() {
  if (!renderer || !scene || !camera || !group) return;

  const time = performance.now() * 0.001;
  group.rotation.y = time * 0.035;
  group.rotation.x = Math.sin(time * 0.18) * 0.06;
  group.rotation.z = Math.cos(time * 0.12) * 0.04;

  waveLines.forEach((wave, index) => {
    const position = wave.geometry.getAttribute("position");

    for (let point = 0; point < position.count; point += 1) {
      const x = position.getX(point);
      const progress = point / Math.max(1, position.count - 1);
      const phase = time * wave.speed + index * 1.2;
      const y =
        wave.baseY +
        Math.sin(x * wave.frequency + phase) * wave.amplitude +
        Math.sin(progress * Math.PI * 6 + phase * 0.7) * 1.8;
      const z =
        -54 +
        index * 18 +
        Math.cos(x * wave.frequency * 0.8 + phase) * 9;

      position.setY(point, y);
      position.setZ(point, z);
    }

    position.needsUpdate = true;
    wave.line.rotation.z = Math.sin(time * 0.2 + index) * 0.08;
  });

  renderer.render(scene, camera);
}
</script>

<template>
  <canvas
    ref="canvas"
    aria-hidden="true"
    class="pointer-events-none absolute inset-0 h-full w-full opacity-100"
  />
</template>
