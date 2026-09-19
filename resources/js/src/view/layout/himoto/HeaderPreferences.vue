<template>
  <div class="header-preferences">
    <time class="header-clock" :datetime="now.toISOString()" title="Giờ Việt Nam (UTC+7)">
      <strong>{{ timeText }}</strong>
      <span>{{ dateText }}</span>
    </time>
    <button class="theme-toggle" type="button" :aria-pressed="String(theme === 'dark')"
      :aria-label="toggleLabel" :title="toggleLabel" @click="toggleTheme">
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <g v-if="theme === 'dark'">
          <circle cx="12" cy="12" r="4" />
          <path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5" />
        </g>
        <path v-else d="M20.5 14A9 9 0 0 1 10 3.5 9 9 0 1 0 20.5 14Z" />
      </svg>
    </button>
  </div>
</template>

<script>
import { initTheme, applyTheme } from "@/core/services/theme";

export default {
  name: "HeaderPreferences",
  data() { return { theme: initTheme(), now: new Date(), clockTimer: null }; },
  computed: {
    toggleLabel() { return this.theme === "dark" ? "Chuyển sang chế độ sáng" : "Chuyển sang chế độ tối"; },
    timeText() { return this.now.toLocaleTimeString("vi-VN", { timeZone: "Asia/Ho_Chi_Minh", hour12: false }); },
    dateText() { return this.now.toLocaleDateString("vi-VN", { timeZone: "Asia/Ho_Chi_Minh", day: "2-digit", month: "2-digit", year: "numeric" }); }
  },
  mounted() {
    this.clockTimer = setInterval(() => { this.now = new Date(); }, 1000);
    window.addEventListener("storage", this.syncTheme);
  },
  beforeDestroy() {
    clearInterval(this.clockTimer);
    window.removeEventListener("storage", this.syncTheme);
  },
  methods: {
    toggleTheme() { this.theme = applyTheme(this.theme === "dark" ? "light" : "dark"); },
    syncTheme(event) { if (event.key === "himoto-theme" || event.key === null) this.theme = initTheme(); }
  }
};
</script>

<style scoped>
.header-preferences { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.header-clock { display: flex; flex-direction: column; text-align: right; color: var(--text-primary); font-size: 13px; line-height: 1.4; font-variant-numeric: tabular-nums; white-space: nowrap; }
.header-clock strong { font-size: 16px; }
.theme-toggle { display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 8px; border: 1px solid var(--border); background: var(--surface); color: var(--text-primary); cursor: pointer; }
.theme-toggle:hover { background: var(--surface-alt); }
.theme-toggle:focus-visible { outline: 2px solid var(--info); outline-offset: 2px; }
.theme-toggle svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
</style>
