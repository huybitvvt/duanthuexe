<template>
  <div class="himoto-cards-skeleton" aria-busy="true" aria-label="Đang tải thẻ dữ liệu...">
    <div v-for="i in count" :key="'card-' + i" class="skeleton-card">
      <div class="skeleton-card-media skeleton-shimmer"></div>
      <div class="skeleton-card-body">
        <div class="skeleton-shimmer skeleton-line skeleton-line-title"></div>
        <div class="skeleton-shimmer skeleton-line skeleton-line-sub"></div>
        <div class="skeleton-card-meta">
          <div class="skeleton-shimmer skeleton-badge"></div>
          <div class="skeleton-shimmer skeleton-price"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "HimotoCardSkeleton",
  props: {
    count: {
      type: Number,
      default: 4
    }
  }
};
</script>

<style scoped>
.himoto-cards-skeleton {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 16px;
  width: 100%;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.skeleton-shimmer {
  background: linear-gradient(90deg, #f0f3f7 25%, #e4e8ee 50%, #f0f3f7 75%);
  background-size: 200% 100%;
  animation: shimmer 1.6s infinite ease-in-out;
}

.skeleton-card {
  background: #ffffff;
  border: 1px solid #eaedf1;
  border-radius: 12px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.skeleton-card-media {
  width: 100%;
  height: 160px;
}

.skeleton-card-body {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.skeleton-line {
  height: 14px;
  border-radius: 4px;
}

.skeleton-line-title {
  width: 75%;
  height: 18px;
}

.skeleton-line-sub {
  width: 50%;
}

.skeleton-card-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 8px;
}

.skeleton-badge {
  width: 65px;
  height: 22px;
  border-radius: 9999px;
}

.skeleton-price {
  width: 90px;
  height: 18px;
  border-radius: 4px;
}
</style>
