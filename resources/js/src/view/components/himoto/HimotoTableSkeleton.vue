<template>
  <div class="himoto-table-skeleton" aria-busy="true" aria-label="Đang tải bảng...">
    <div class="skeleton-table-header">
      <div v-for="c in cols" :key="'col-' + c" class="skeleton-shimmer skeleton-th"></div>
    </div>
    <div v-for="r in rows" :key="'row-' + r" class="skeleton-table-row">
      <div v-for="c in cols" :key="'cell-' + r + '-' + c" class="skeleton-shimmer skeleton-td"></div>
    </div>
  </div>
</template>

<script>
export default {
  name: "HimotoTableSkeleton",
  props: {
    rows: {
      type: Number,
      default: 5
    },
    cols: {
      type: Number,
      default: 6
    }
  }
};
</script>

<style scoped>
.himoto-table-skeleton {
  width: 100%;
  background: #ffffff;
  border: 1px solid #eaedf1;
  border-radius: 12px;
  overflow: hidden;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 14px;
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

.skeleton-table-header {
  display: flex;
  gap: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid #eaedf1;
}

.skeleton-th {
  flex: 1;
  height: 16px;
}

.skeleton-table-row {
  display: flex;
  gap: 16px;
  padding: 10px 0;
  border-bottom: 1px dashed #f1f3f6;
}

.skeleton-td {
  flex: 1;
  height: 20px;
}
</style>
