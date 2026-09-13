<template>
  <div class="himoto-error-state" role="alert">
    <div class="error-icon-box">
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"></circle>
        <line x1="12" y1="8" x2="12" y2="12"></line>
        <line x1="12" y1="16" x2="12.01" y2="16"></line>
      </svg>
    </div>
    <h3 class="error-title">{{ title }}</h3>
    <p class="error-message">{{ message }}</p>
    <div v-if="showRetry" class="error-actions">
      <button type="button" class="btn btn-primary" @click="$emit('retry')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/>
        </svg>
        {{ retryText }}
      </button>
    </div>
  </div>
</template>

<script>
export default {
  name: "HimotoErrorState",
  props: {
    title: {
      type: String,
      default: "Không thể tải dữ liệu"
    },
    message: {
      type: String,
      default: "Đã xảy ra lỗi trong quá trình kết nối với máy chủ. Vui lòng thử lại sau."
    },
    showRetry: {
      type: Boolean,
      default: true
    },
    retryText: {
      type: String,
      default: "Tải lại trang"
    }
  }
};
</script>

<style scoped>
.himoto-error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 48px 24px;
  background: #ffffff;
  border: 1px solid #fed7d7;
  border-radius: 12px;
  margin: 20px 0;
}

.error-icon-box {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: rgba(237, 28, 36, 0.1);
  color: #ed1c24;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 16px;
}

.error-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: #17202a;
  margin: 0 0 8px 0;
}

.error-message {
  font-size: 0.92rem;
  color: #687386;
  max-width: 480px;
  margin: 0 0 20px 0;
  line-height: 1.5;
}

.error-actions {
  display: flex;
  gap: 12px;
}
</style>
