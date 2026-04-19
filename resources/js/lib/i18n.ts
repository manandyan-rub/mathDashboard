import en from "@/data/i18n/en.json";
import hy from "@/data/i18n/hy.json";
import fr from "@/data/i18n/fr.json";
import ru from "@/data/i18n/ru.json";
import type { App } from "vue";
import { ref } from "vue";

export type LocaleCode = "en" | "hy" | "fr" | "ru";

type TranslationMap = Record<string, string>;

const localeMap: Record<LocaleCode, TranslationMap> = {
  en,
  hy,
  fr,
  ru,
};

const STORAGE_KEY = "lang";
const DEFAULT_LANG: LocaleCode = "en";

declare global {
  interface Window {
    _translations?: TranslationMap;
  }
}

function normalizeKey(value: string): string {
  return value.replace(/\s+/g, " ").trim();
}

function isSupportedLocale(value: string): value is LocaleCode {
  return value === "en" || value === "hy" || value === "fr" || value === "ru";
}

function getSavedLanguage(): LocaleCode | null {
  if (typeof window === "undefined") return null;
  const value = localStorage.getItem(STORAGE_KEY) ?? "";
  return isSupportedLocale(value) ? value : null;
}

let currentLang: LocaleCode = getSavedLanguage() ?? DEFAULT_LANG;
let translations: TranslationMap = localeMap[currentLang] ?? {};
const localeTick = ref(0);

function resolveTranslation(key: string): string {
  const normalized = normalizeKey(key);
  if (!normalized) return key;

  const fromCurrent = translations[normalized];
  if (typeof fromCurrent === "string" && fromCurrent.length > 0) {
    return fromCurrent;
  }

  const fromEnglish = localeMap.en[normalized];
  if (typeof fromEnglish === "string" && fromEnglish.length > 0) {
    return fromEnglish;
  }

  return key;
}

function applyLanguage(lang: LocaleCode): void {
  currentLang = lang;
  translations = localeMap[lang] ?? {};
  localeTick.value += 1;

  if (typeof window !== "undefined") {
    localStorage.setItem(STORAGE_KEY, lang);
    document.documentElement.setAttribute("lang", lang);
    document.cookie = `lang=${lang}; path=/; max-age=31536000`;
    window._translations = translations;
  }
}

export function setLanguage(lang: LocaleCode): void {
  if (!localeMap[lang]) return;
  applyLanguage(lang);
}

export function getLanguage(): LocaleCode {
  return currentLang;
}

export function useLanguageVersion() {
  return localeTick;
}

export function cmsTranslate(
  key: string,
  replacements: Record<string, string> = {},
): string {
  localeTick.value;
  let translation = resolveTranslation(key);

  Object.keys(replacements).forEach((token) => {
    const pattern = new RegExp(`:${token}`, "g");
    translation = translation.replace(pattern, replacements[token]);
  });

  return translation;
}

export const TranslateVue = {
  install: (app: App) => {
    if (typeof window !== "undefined" && !window._translations) {
      applyLanguage(currentLang);
    }

    app.config.globalProperties.__ = cmsTranslate;
    app.provide("__", cmsTranslate);
  },
};

if (typeof window !== "undefined") {
  applyLanguage(currentLang);
}

