<template>
  <div class="header-preferences">
    <time class="header-clock" :datetime="now.toISOString()" title="Giờ Việt Nam (UTC+7)">
      <strong>{{ timeText }}</strong>
      <span>{{ dateText }}</span>
    </time>
  </div>
</template>

<script>
export default {
  name: "HeaderPreferences",
  data() { return { now: new Date(), clockTimer: null }; },
  computed: {
    timeText() { return this.now.toLocaleTimeString("vi-VN", { timeZone: "Asia/Ho_Chi_Minh", hour12: false }); },
    dateText() { return this.now.toLocaleDateString("vi-VN", { timeZone: "Asia/Ho_Chi_Minh", day: "2-digit", month: "2-digit", year: "numeric" }); }
  },
  mounted() {
    this.clockTimer = setInterval(() => { this.now = new Date(); }, 1000);
  },
  beforeDestroy() {
    clearInterval(this.clockTimer);
  },
  methods: {
  }
};
</script>

<style scoped>
.header-preferences { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.header-clock { display: flex; flex-direction: column; text-align: right; color: var(--text-primary); font-size: 13px; line-height: 1.4; font-variant-numeric: tabular-nums; white-space: nowrap; }
.header-clock strong { font-size: 16px; }
</style>
