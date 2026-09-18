/**
 * HIMOTO API Error Handler Utility
 */

export function getApiStatus(error) {
  if (!error) return null;
  return (
    error.response?.status ||
    error.status ||
    error.statusCode ||
    (error.response && error.response.data && error.response.data.status) ||
    null
  );
}

export function getApiMessage(error, fallback = "Có lỗi xảy ra, vui lòng thử lại") {
  if (!error) return fallback;

  const validationErrors = getApiValidationErrors(error);

  // 1. Direct message from server response data
  if (error.response?.data?.message) {
    return error.response.data.message;
  }
  if (error.data?.message) {
    return error.data.message;
  }
  if (validationErrors) {
    const firstKey = Object.keys(validationErrors)[0];
    const firstError = validationErrors[firstKey];
    if (Array.isArray(firstError) && firstError[0]) {
      return firstError[0];
    }
    if (typeof firstError === "string") {
      return firstError;
    }
  }
  if (typeof error.response?.data === "string" && error.response.data.length < 200) {
    return error.response.data;
  }

  // 2. HTTP Status Code friendly messages
  const status = getApiStatus(error);
  if (status === 401) {
    return "Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.";
  }
  if (status === 403) {
    return "Bạn không có quyền truy cập chức năng hoặc dữ liệu này.";
  }
  if (status === 404) {
    return "Không tìm thấy dữ liệu yêu cầu (404).";
  }
  if (status === 422) {
    const errors = error.response?.data?.errors;
    if (errors && typeof errors === "object") {
      const firstKey = Object.keys(errors)[0];
      if (Array.isArray(errors[firstKey]) && errors[firstKey][0]) {
        return errors[firstKey][0];
      }
    }
    return "Dữ liệu nhập không hợp lệ (422).";
  }
  if (status === 429) {
    return "Hệ thống đang bận do quá nhiều yêu cầu. Vui lòng thử lại sau giây lát.";
  }
  if (status >= 500) {
    return "Lỗi máy chủ nội bộ (500). Đội kỹ thuật đã được thông báo.";
  }

  // 3. Fallback error.message
  if (error.message) {
    if (error.message.includes("Network Error") || error.message.includes("Failed to fetch")) {
      return "Lỗi kết nối mạng. Vui lòng kiểm tra lại đường truyền Internet.";
    }
    return error.message;
  }

  return fallback;
}

export function getApiValidationErrors(error) {
  if (!error) return null;

  const payload = error.response?.data || error.data || {};
  return (
    payload.errors ||
    payload.message_validate_form ||
    payload.data?.message_validate_form ||
    null
  );
}

export default {
  getApiStatus,
  getApiMessage,
  getApiValidationErrors
};
