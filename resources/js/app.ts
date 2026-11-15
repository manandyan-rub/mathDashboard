import "../css/app.css";
import "./bootstrap";
import { initializeTheme } from "./composables/useTheme";
import { themeColor } from "./lib/themeColors";

import { createInertiaApp } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createApp, h, type DefineComponent } from "vue";
import { ZiggyVue } from "../../vendor/tightenco/ziggy";
import { TranslateVue } from "./lib/i18n";
import { MotionPlugin } from "./lib/motion";

const appName = import.meta.env.VITE_APP_NAME || "Laravel";
const pages = import.meta.glob<{ default: DefineComponent }>(
  "./Pages/**/*.vue",
);

initializeTheme();

createInertiaApp({
  title: (title) => `${title} - ${appName}`,
  resolve: async (name) => {
    const page = await resolvePageComponent(`./Pages/${name}.vue`, pages);

    return page.default;
  },
  setup({ el, App, props, plugin }) {
    return createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(ZiggyVue)
      .use(TranslateVue)
      .use(MotionPlugin)
      .mount(el);
  },
  progress: {
    color: themeColor("--primary"),
  },
});
