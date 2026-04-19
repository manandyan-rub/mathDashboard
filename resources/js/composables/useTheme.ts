import { computed, ref } from "vue";

export type Theme = "light" | "dark";

const STORAGE_KEY = "mathdash-theme";
const DEFAULT_THEME: Theme = "dark";

const isBrowser = typeof window !== "undefined";
const theme = ref<Theme>(DEFAULT_THEME);
let initialized = false;

const applyTheme = (value: Theme) => {
  if (!isBrowser) return;

  const root = document.documentElement;
  root.classList.toggle("dark", value === "dark");
  root.style.colorScheme = value;
};

const readStoredTheme = (): Theme | null => {
  if (!isBrowser) return null;

  const stored = window.localStorage.getItem(STORAGE_KEY);
  return stored === "light" || stored === "dark" ? stored : null;
};

export const initializeTheme = () => {
  if (initialized) return;

  const stored = readStoredTheme();
  theme.value = stored ?? DEFAULT_THEME;
  applyTheme(theme.value);
  initialized = true;
};

export const useTheme = () => {
  if (!initialized) initializeTheme();

  const setTheme = (value: Theme) => {
    theme.value = value;
    applyTheme(value);

    if (isBrowser) {
      window.localStorage.setItem(STORAGE_KEY, value);
    }
  };

  const toggleTheme = () => {
    setTheme(theme.value === "dark" ? "light" : "dark");
  };

  return {
    theme,
    isDark: computed(() => theme.value === "dark"),
    setTheme,
    toggleTheme,
  };
};
