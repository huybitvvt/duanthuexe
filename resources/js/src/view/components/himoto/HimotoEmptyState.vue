<template>
  <div class="himoto-empty-state">
    <div class="empty-icon-box">
      <slot name="icon">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
          <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
          <line x1="9" y1="14" x2="15" y2="14"></line>
        </svg>
      </slot>
    </div>
    <h3 class="empty-title">{{ title }}</h3>
    <p class="empty-description">{{ description }}</p>
    <div v-if="showAction || $slots.action" class="empty-actions">
      <slot name="action">
        <button type="button" class="btn btn-primary" @click="$emit('action')">
          {{ actionText }}
        </button>
      </slot>
    </div>
  </div>
</template>

<script>
export default {
  name: "HimotoEmptyState",
  props: {
    title: {
      type: String,
      default: "Không có dữ liệu"
    },
    description: {
      type: String,
      default: "Chưa có bản ghi nào được tìm thấy trong hệ thống."
    },
    showAction: {
      type: Boolean,
      default: false
    },
    actionText: {
      type: String,
      default: "Thêm mới"
    }
  }
};
</script>

<style scoped>
.himoto-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 48px 24px;
  background: #ffffff;
  border: 1px dashed #d1d7df;
  border-radius: 12px;
  margin: 16px 0;
  width: 100%;
}

.empty-icon-box {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: #f5f7fa;
  color: #9aa4b2;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 16px;
}

.empty-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: #17202a;
  margin: 0 0 6px 0;
}

.empty-description {
  font-size: 0.92rem;
  color: #687386;
  max-width: 440px;
  margin: 0 0 18px 0;
  line-height: 1.5;
}

.empty-actions {
  display: flex;
  gap: 12px;
}
</style>
