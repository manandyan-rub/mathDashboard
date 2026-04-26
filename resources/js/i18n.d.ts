import "@vue/runtime-core";
import "vue";

declare module "@vue/runtime-core" {
  interface ComponentCustomProperties {
    __: (key: string, replacements?: Record<string, string>) => string;
  }
}

declare module "vue" {
  interface ComponentCustomProperties {
    __: (key: string, replacements?: Record<string, string>) => string;
  }
}

export {};
