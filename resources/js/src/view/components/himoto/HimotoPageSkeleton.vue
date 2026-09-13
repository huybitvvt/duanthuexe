<template>
  <div class="himoto-page-skeleton" aria-busy="true" aria-label="Đang tải dữ liệu...">
    <!-- Fake Header -->
    <div class="skeleton-header">
      <div class="skeleton-title-box">
        <div class="skeleton-shimmer skeleton-title"></div>
        <div class="skeleton-shimmer skeleton-subtitle"></div>
      </div>
      <div class="skeleton-actions">
        <div class="skeleton-shimmer skeleton-btn"></div>
      </div>
    </div>

    <!-- Fake KPI Cards -->
    <div v-if="showKpi" class="skeleton-kpi-grid">
      <div v-for="i in kpiCount" :key="'kpi-' + i" class="skeleton-kpi-card">
        <div class="skeleton-shimmer skeleton-kpi-label"></div>
        <div class="skeleton-shimmer skeleton-kpi-val"></div>
        <div class="skeleton-shimmer skeleton-kpi-footer"></div>
      </div>
    </div>

    <!-- Fake Filter Bar -->
    <div v-if="showFilter" class="skeleton-filter-bar">
      <div class="skeleton-shimmer skeleton-search-input"></div>
      <div class="skeleton-shimmer skeleton-select"></div>
      <div class="skeleton-shimmer skeleton-select"></div>
    </div>

    <!-- Fake Table / Content Rows -->
    <div class="skeleton-table-card">
      <div class="skeleton-table-header">
        <div v-for="c in colCount" :key="'th-' + c" class="skeleton-shimmer skeleton-th"></div>
      </div>
      <div v-for="r in rowCount" :key="'tr-' + r" class="skeleton-table-row">
        <div v-for="c in colCount" :key="'td-' + r + '-' + c" class="skeleton-shimmer skeleton-td"></div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "HimotoPageSkeleton",
  props: {
    showKpi: {
      type: Boolean,
      default: true
    },
    kpiCount: {
      type: Number,
      default: 4
    },
    showFilter: {
      type: Boolean,
      default: true
    },
    rowCount: {
      type: Number,
      default: 5
    },
    colCount: {
      type: Number,
      default: 5
    }
  }
};
</script>

<style scoped>
.himoto-page-skeleton {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 20px;
  animation: fadeIn 200ms ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.skeleton-shimmer {
  background: linear-gradient(90deg, #f0f3f7 25%, #e4e8ee 50%, #f0f3f7 75%);
  background-size: 200% 100%;
  animation: shimmer 1.6s infinite ease-in-out;
  border-radius: 6px;
}

/* Header */
.skeleton-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 4px;
}

.skeleton-title-box {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.skeleton-title {
  width: 220px;
  height: 28px;
  border-radius: 8px;
}

.skeleton-subtitle {
  width: 140px;
  height: 16px;
}

.skeleton-btn {
  width: 140px;
  height: 38px;
  border-radius: 10px;
}

/* KPI Grid */
.skeleton-kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
}

.skeleton-kpi-card {
  background: #ffffff;
  border: 1px solid #eaedf1;
  border-radius: 12px;
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.skeleton-kpi-label {
  width: 60%;
  height: 14px;
}

.skeleton-kpi-val {
  width: 80%;
  height: 28px;
}

.skeleton-kpi-footer {
  width: 45%;
  height: 12px;
}

/* Filter Bar */
.skeleton-filter-bar {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.skeleton-search-input {
  flex: 1;
  min-width: 200px;
  height: 40px;
  border-radius: 10px;
}

.skeleton-select {
  width: 160px;
  height: 40px;
  border-radius: 10px;
}

/* Table Card */
.skeleton-table-card {
  background: #ffffff;
  border: 1px solid #eaedf1;
  border-radius: 12px;
  overflow: hidden;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.skeleton-table-header {
  display: flex;
  gap: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid #eaedf1;
}

.skeleton-th {
  flex: 1;
  height: 18px;
}

.skeleton-table-row {
  display: flex;
  gap: 16px;
  padding: 10px 0;
  border-bottom: 1px dashed #f1f3f6;
}

.skeleton-td {
  flex: 1;
  height: 22px;
}
</style>
