// @ts-check
import {defineConfig} from "astro/config";

import tailwindcss from "@tailwindcss/vite";

import relativeLinks from "astro-relative-links";

// https://astro.build/config
export default defineConfig({
  output: "static", // 靜態網站輸出模式
  prefetch: {
    defaultStrategy: "viewport", // 當連結進入視窗時預先載入
    prefetchAll: true // 預先載入所有連結，提升導航速度
  },
  vite: {
    plugins: [tailwindcss()],
  },

  integrations: [relativeLinks()],
});
