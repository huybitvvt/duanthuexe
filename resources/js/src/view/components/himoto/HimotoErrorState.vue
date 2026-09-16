<template>
  <div class="himoto-error-state" role="alert">
    <div class="error-icon-box">
      <span aria-hidden="true">Lỗi</span>
    </div>
    <h3 class="error-title">{{ title }}</h3>
    <p class="error-message">{{ message }}</p>
    <div v-if="showRetry" class="error-actions">
      <button type="button" class="btn btn-primary" @click="$emit('retry')">
        <span aria-hidden="true">Thử lại:</span>
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
