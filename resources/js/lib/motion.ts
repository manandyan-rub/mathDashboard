import type { App, Directive } from "vue";

/**
 * Small Svelte-style motion toolkit:
 *  - v-reveal   fly/fade elements in when they scroll into view (with stagger)
 *  - v-tilt     3D tilt + cursor-following spotlight on cards
 *  - v-magnetic buttons that lean towards the cursor
 *  - tween()    rAF number tween used by <AnimatedNumber>
 *
 * Everything degrades to "no motion" when the user prefers reduced motion.
 */

export function prefersReducedMotion(): boolean {
  return (
    typeof window !== "undefined" &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches
  );
}

export const easings = {
  outCubic: (t: number) => 1 - Math.pow(1 - t, 3),
  outExpo: (t: number) => (t === 1 ? 1 : 1 - Math.pow(2, -10 * t)),
  outBack: (t: number) => {
    const c1 = 1.70158;
    const c3 = c1 + 1;
    return 1 + c3 * Math.pow(t - 1, 3) + c1 * Math.pow(t - 1, 2);
  },
};

export function tween(
  from: number,
  to: number,
  duration: number,
  onUpdate: (value: number) => void,
  easing: (t: number) => number = easings.outExpo,
): () => void {
  if (prefersReducedMotion() || duration <= 0 || from === to) {
    onUpdate(to);
    return () => {};
  }

  const start = performance.now();
  let frame = 0;

  const step = (now: number) => {
    const progress = Math.min(1, (now - start) / duration);
    onUpdate(from + (to - from) * easing(progress));
    if (progress < 1) frame = requestAnimationFrame(step);
  };

  frame = requestAnimationFrame(step);
  return () => cancelAnimationFrame(frame);
}

/* ------------------------------------------------------------------ */
/* v-reveal                                                            */
/* ------------------------------------------------------------------ */

type RevealVariant = "up" | "down" | "left" | "right" | "scale" | "blur";
type RevealValue =
  | number
  | RevealVariant
  | { delay?: number; variant?: RevealVariant; once?: boolean }
  | undefined;

let revealObserver: IntersectionObserver | null = null;

function getRevealObserver(): IntersectionObserver {
  if (revealObserver) return revealObserver;

  revealObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        const el = entry.target as HTMLElement;
        if (entry.isIntersecting) {
          el.classList.add("is-revealed");
          if (el.dataset.revealOnce !== "false") {
            revealObserver?.unobserve(el);
            scheduleRevealCleanup(el);
          }
        } else if (el.dataset.revealOnce === "false") {
          el.classList.remove("is-revealed");
        }
      });
    },
    { threshold: 0.12, rootMargin: "0px 0px -6% 0px" },
  );

  return revealObserver;
}

/**
 * Once a one-shot reveal has played, drop the reveal classes so the element
 * returns to its normal styles (and other transforms such as v-tilt work).
 */
function scheduleRevealCleanup(el: HTMLElement) {
  const delay = parseFloat(el.style.getPropertyValue("--reveal-delay")) || 0;
  el.dataset.revealDone = "true";
  window.setTimeout(() => {
    el.classList.remove("reveal", "is-revealed");
    delete el.dataset.reveal;
  }, delay + 1000);
}

function applyReveal(el: HTMLElement, value: RevealValue) {
  if (el.dataset.revealDone) return;

  const options =
    typeof value === "number"
      ? { delay: value }
      : typeof value === "string"
        ? { variant: value }
        : (value ?? {});

  el.classList.add("reveal");
  el.dataset.reveal = options.variant ?? "up";
  el.dataset.revealOnce = String(options.once ?? true);
  el.style.setProperty("--reveal-delay", `${options.delay ?? 0}ms`);
}

export const vReveal: Directive<HTMLElement, RevealValue> = {
  mounted(el, binding) {
    applyReveal(el, binding.value);
    if (prefersReducedMotion() || typeof IntersectionObserver === "undefined") {
      el.classList.remove("reveal");
      el.dataset.revealDone = "true";
      return;
    }
    getRevealObserver().observe(el);
  },
  updated(el, binding) {
    if (binding.value !== binding.oldValue) applyReveal(el, binding.value);
  },
  unmounted(el) {
    revealObserver?.unobserve(el);
  },
};

/* ------------------------------------------------------------------ */
/* v-tilt                                                              */
/* ------------------------------------------------------------------ */

type TiltValue = number | { max?: number; scale?: number } | undefined;

interface TiltState {
  frame: number;
  move: (event: PointerEvent) => void;
  leave: () => void;
}

const tiltStates = new WeakMap<HTMLElement, TiltState>();

export const vTilt: Directive<HTMLElement, TiltValue> = {
  mounted(el, binding) {
    el.classList.add("motion-card");
    if (prefersReducedMotion()) return;

    const options =
      typeof binding.value === "number"
        ? { max: binding.value }
        : (binding.value ?? {});
    const max = options.max ?? 7;
    const scale = options.scale ?? 1.015;

    const state: TiltState = {
      frame: 0,
      move: (event) => {
        if (event.pointerType === "touch") return;
        cancelAnimationFrame(state.frame);
        state.frame = requestAnimationFrame(() => {
          const rect = el.getBoundingClientRect();
          const x = (event.clientX - rect.left) / rect.width;
          const y = (event.clientY - rect.top) / rect.height;
          el.style.setProperty("--mx", `${x * 100}%`);
          el.style.setProperty("--my", `${y * 100}%`);
          el.style.setProperty("--rx", `${(0.5 - y) * max}deg`);
          el.style.setProperty("--ry", `${(x - 0.5) * max}deg`);
          el.style.setProperty("--tilt-scale", String(scale));
          el.classList.add("is-tilting");
        });
      },
      leave: () => {
        cancelAnimationFrame(state.frame);
        el.style.setProperty("--rx", "0deg");
        el.style.setProperty("--ry", "0deg");
        el.style.setProperty("--tilt-scale", "1");
        el.classList.remove("is-tilting");
      },
    };

    el.addEventListener("pointermove", state.move);
    el.addEventListener("pointerleave", state.leave);
    tiltStates.set(el, state);
  },
  unmounted(el) {
    const state = tiltStates.get(el);
    if (!state) return;
    cancelAnimationFrame(state.frame);
    el.removeEventListener("pointermove", state.move);
    el.removeEventListener("pointerleave", state.leave);
    tiltStates.delete(el);
  },
};

/* ------------------------------------------------------------------ */
/* v-magnetic                                                          */
/* ------------------------------------------------------------------ */

const magneticStates = new WeakMap<HTMLElement, TiltState>();

export const vMagnetic: Directive<HTMLElement, number | undefined> = {
  mounted(el, binding) {
    el.classList.add("motion-magnetic");
    if (prefersReducedMotion()) return;

    const strength = binding.value ?? 0.25;
    const state: TiltState = {
      frame: 0,
      move: (event) => {
        if (event.pointerType === "touch") return;
        cancelAnimationFrame(state.frame);
        state.frame = requestAnimationFrame(() => {
          const rect = el.getBoundingClientRect();
          const dx = event.clientX - (rect.left + rect.width / 2);
          const dy = event.clientY - (rect.top + rect.height / 2);
          el.style.transform = `translate(${dx * strength}px, ${dy * strength}px)`;
        });
      },
      leave: () => {
        cancelAnimationFrame(state.frame);
        el.style.transform = "";
      },
    };

    el.addEventListener("pointermove", state.move);
    el.addEventListener("pointerleave", state.leave);
    magneticStates.set(el, state);
  },
  unmounted(el) {
    const state = magneticStates.get(el);
    if (!state) return;
    cancelAnimationFrame(state.frame);
    el.removeEventListener("pointermove", state.move);
    el.removeEventListener("pointerleave", state.leave);
    magneticStates.delete(el);
  },
};

/* ------------------------------------------------------------------ */
/* v-spotlight: soft light that follows the cursor across a surface    */
/* ------------------------------------------------------------------ */

const spotlightStates = new WeakMap<HTMLElement, TiltState>();

export const vSpotlight: Directive<HTMLElement> = {
  mounted(el) {
    el.classList.add("spotlight-surface");
    if (prefersReducedMotion()) return;

    const state: TiltState = {
      frame: 0,
      move: (event) => {
        cancelAnimationFrame(state.frame);
        state.frame = requestAnimationFrame(() => {
          const rect = el.getBoundingClientRect();
          el.style.setProperty("--sx", `${event.clientX - rect.left}px`);
          el.style.setProperty("--sy", `${event.clientY - rect.top}px`);
        });
      },
      leave: () => {},
    };

    el.addEventListener("pointermove", state.move);
    spotlightStates.set(el, state);
  },
  unmounted(el) {
    const state = spotlightStates.get(el);
    if (!state) return;
    cancelAnimationFrame(state.frame);
    el.removeEventListener("pointermove", state.move);
    spotlightStates.delete(el);
  },
};

export const MotionPlugin = {
  install(app: App) {
    app.directive("reveal", vReveal);
    app.directive("tilt", vTilt);
    app.directive("magnetic", vMagnetic);
    app.directive("spotlight", vSpotlight);
  },
};
