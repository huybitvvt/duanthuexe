<template>
  <div class="customer-reminders-page">
    <div class="page-heading mb-5">
      <div>
        <p class="eyebrow mb-2">CHĂM SÓC CÔNG NỢ</p>
        <h2 class="font-weight-bolder text-dark mb-2">Nhắc nợ khách</h2>
        <p class="text-muted mb-0">
          Theo dõi các khoản đến hạn, quá hạn và lịch trả xe cần liên hệ khách hàng.
        </p>
      </div>
      <div class="page-actions">
        <button
          type="button"
          class="btn btn-outline-danger font-weight-bold"
          :disabled="scanning"
          @click="scanReminders"
        >
          {{ scanning ? "Đang quét..." : "Quét khoản đến hạn" }}
        </button>
        <button
          type="button"
          class="btn btn-danger font-weight-bold"
          :disabled="dispatching"
          @click="checkQueue"
        >
          {{ dispatching ? "Đang kiểm tra..." : "Kiểm tra hàng đợi" }}
        </button>
      </div>
    </div>

    <div class="notice-box mb-5">
      Hệ thống đang ở chế độ kiểm tra nội bộ. Nút “Kiểm tra hàng đợi” không gửi SMS, Zalo hoặc email cho khách.
    </div>

    <div class="summary-grid mb-5">
      <div class="summary-card">
        <span class="summary-label">TỔNG VIỆC NHẮC</span>
        <strong>{{ pagination.total }}</strong>
        <small>Theo bộ lọc hiện tại</small>
      </div>
      <div class="summary-card summary-card-danger">
        <span class="summary-label">ĐANG CHỜ XỬ LÝ</span>
        <strong>{{ pendingOnPage }}</strong>
        <small>Trong trang đang xem</small>
      </div>
      <div class="summary-card summary-card-muted">
        <span class="summary-label">CẦN KIỂM TRA</span>
        <strong>{{ attentionOnPage }}</strong>
        <small>Lỗi hoặc chưa gửi được</small>
      </div>
    </div>

    <div class="card card-custom gutter-b filter-card">
      <div class="card-body p-4">
        <div class="row align-items-end">
          <div class="col-lg-5 col-md-12 mb-3 mb-lg-0">
            <label class="filter-label">Tìm khách hàng</label>
            <search-suggest endpoint="/api/auth/customer-reminders/action-list" :params="filters" query-key="search" fields="recipient_name,recipient_phone,message_content" @select="applyFilters" @submit="applyFilters"
              v-model.trim="filters.search"
              clearable
              placeholder="Tên khách, số điện thoại hoặc nội dung nhắc"
              @clear="applyFilters"
            />
          </div>
          <div class="col-lg-2 col-md-4 mb-3 mb-lg-0">
            <label class="filter-label">Loại hợp đồng</label>
            <el-select v-model="filters.contract_type" class="w-100" @change="applyFilters">
              <el-option label="Tất cả" value="" />
              <el-option label="Thuê sở hữu" value="lease" />
              <el-option label="Thuê xe" value="rental" />
            </el-select>
          </div>
          <div class="col-lg-2 col-md-4 mb-3 mb-lg-0">
            <label class="filter-label">Phòng giao dịch</label>
            <el-select v-model="filters.store_id" class="w-100" clearable placeholder="Tất cả phòng GD" @change="applyFilters">
              <el-option label="Tất cả phòng GD" value="" />
              <el-option v-for="store in stores" :key="store.id" :label="store.store_name" :value="store.id" />
            </el-select>
          </div>
          <div class="col-lg-2 col-md-4 mb-3 mb-lg-0">
            <label class="filter-label">Trạng thái</label>
            <el-select v-model="filters.status" class="w-100" @change="applyFilters">
              <el-option label="Tất cả" value="" />
              <el-option label="Đang chờ" value="pending" />
              <el-option label="Đã gửi" value="sent" />
              <el-option label="Thất bại" value="failed" />
              <el-option label="Đã bỏ qua" value="skipped" />
              <el-option label="Đã hủy" value="cancelled" />
            </el-select>
          </div>
          <div class="col-lg-3 col-md-4 d-flex filter-actions">
            <button type="button" class="btn btn-danger font-weight-bold mr-2" @click="applyFilters">
              Lọc danh sách
            </button>
            <button type="button" class="btn btn-light font-weight-bold" @click="resetFilters">
              Xóa lọc
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="card card-custom result-card" v-loading="loading">
      <div class="card-header border-0 result-header">
        <div>
          <h3 class="card-label font-weight-bolder text-dark mb-1">Danh sách cần liên hệ</h3>
          <span class="text-muted">Ưu tiên các khoản quá hạn và lịch trả xe đã trễ.</span>
        </div>
        <button type="button" class="btn btn-sm btn-light font-weight-bold" :disabled="loading" @click="fetchReminders">
          Làm mới
        </button>
      </div>

      <div v-if="!loading && reminders.length === 0" class="empty-state">
        <strong>Chưa có việc nhắc phù hợp</strong>
        <span>Hãy đổi bộ lọc hoặc quét các khoản đến hạn để cập nhật danh sách.</span>
      </div>

      <div v-else class="table-responsive">
        <table class="table reminder-table mb-0">
          <thead>
            <tr>
              <th>Khách hàng</th>
              <th>Hợp đồng</th>
              <th>Mức nhắc</th>
              <th>Nội dung liên hệ</th>
              <th>Thời điểm</th>
              <th>Trạng thái</th>
              <th>Liên hệ hôm nay</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in reminders" :key="item.id">
              <td>
                <strong class="d-block text-dark">{{ item.recipient_name || "Chưa có tên" }}</strong>
                <span class="text-muted">{{ item.recipient_phone || "Chưa có số điện thoại" }}</span>
              </td>
              <td>
                <span class="type-label">{{ contractTypeLabel(item.contract_type) }}</span>
                <button type="button" class="btn btn-link btn-sm p-0 d-block mt-1" @click="openContract(item)">
                  Mở đơn #{{ item.contract_id }}
                </button>
              </td>
              <td>
                <span class="stage-label" :class="stageClass(item.stage)">
                  {{ stageLabel(item.stage) }}
                </span>
              </td>
              <td class="message-cell">
                <span :title="item.message_content">{{ item.message_content || "Chưa có nội dung" }}</span>
                <small v-if="item.error_message" class="d-block error-text mt-2">
                  Lỗi: {{ item.error_message }}
                </small>
              </td>
              <td>
                <span class="d-block">{{ formatDate(item.scheduled_at) }}</span>
                <small class="text-muted">Tạo: {{ formatDate(item.created_at) }}</small>
              </td>
              <td>
                <span class="status-label" :class="statusClass(item.status)">
                  {{ statusLabel(item.status) }}
                </span>
              </td>
              <td>
                <span :class="item.contacted_today ? 'text-success' : 'text-warning'" class="d-block font-weight-bold">
                  {{ item.contacted_today ? 'Đã liên hệ' : 'Chưa liên hệ' }}
                </span>
                <small v-if="item.last_contact_note" class="d-block text-muted" :title="item.last_contact_note">
                  {{ item.last_contact_note }}
                </small>
                <button type="button" class="btn btn-sm btn-link p-0" @click="recordContact(item)">Ghi chú</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="pagination.total > 0" class="pagination-row">
        <span class="text-muted">
          Hiển thị {{ reminders.length }} / {{ pagination.total }} mục
        </span>
        <el-pagination
          background
          layout="prev, pager, next"
          :current-page="pagination.current_page"
          :page-size="pagination.per_page"
          :total="pagination.total"
          @current-change="changePage"
        />
      </div>
    </div>
  </div>
</template>

<script>
import ApiService from "@/core/services/api.service";

export default {
  name: "CustomerReminderIndex",
  data() {
    return {
      loading: false,
      scanning: false,
      dispatching: false,
      reminders: [],
      filters: {
        search: "",
        contract_type: "",
        status: "",
        store_id: "",
      },
      stores: [],
      pagination: {
        current_page: 1,
        per_page: 20,
        total: 0,
        last_page: 1,
      },
    };
  },
  computed: {
    pendingOnPage() {
      return this.reminders.filter(item => item.status === "pending").length;
    },
    attentionOnPage() {
      return this.reminders.filter(item => ["failed", "pending"].includes(item.status)).length;
    },
  },
  created() {
    if (this.$route.query.search) {
      this.filters.search = this.$route.query.search;
    }
    if (this.$route.query.store_id) {
      this.filters.store_id = Number(this.$route.query.store_id);
    }
    this.fetchStores();
    this.fetchReminders();
  },
  methods: {
    async recordContact(item) {
      try {
        const { value } = await this.$prompt('Đã trao đổi gì với khách hôm nay?', `Liên hệ đơn #${item.contract_id}`, {
          inputType: 'textarea',
          confirmButtonText: 'Lưu ghi chú',
          cancelButtonText: 'Hủy',
          inputValidator: note => note && note.trim() ? true : 'Vui lòng nhập nội dung liên hệ',
        });
        await ApiService.post(`/api/auth/customer-reminders/${item.id}/contact`, { note: value.trim() });
        await this.fetchReminders();
      } catch (error) {
        if (error !== 'cancel' && error !== 'close') {
          this.$message.error(this.errorMessage(error, 'Không lưu được ghi chú liên hệ'));
        }
      }
    },
    openContract(item) {
      if (item.contract_type === 'rental_order') {
        this.$router.push({ name: 'car-rental', query: { open_order: item.contract_id } });
      } else {
        this.$router.push({ name: 'lease-to-own', query: { open_contract: item.contract_id } });
      }
    },
    async fetchReminders(page = this.pagination.current_page) {
      this.loading = true;
      try {
        const response = await ApiService.query("/api/auth/customer-reminders/action-list", {
          page,
          per_page: this.pagination.per_page,
          search: this.filters.search || undefined,
          contract_type: this.filters.contract_type || undefined,
          status: this.filters.status || undefined,
          store_id: this.filters.store_id || undefined,
        });
        const payload = response.data.data || response.data || {};
        this.reminders = Array.isArray(payload.data) ? payload.data : [];
        this.pagination = {
          current_page: Number(payload.current_page || page || 1),
          per_page: Number(payload.per_page || 20),
          total: Number(payload.total || 0),
          last_page: Number(payload.last_page || 1),
        };
      } catch (error) {
        this.reminders = [];
        this.pagination.total = 0;
        this.$message.error(this.errorMessage(error, "Không thể tải danh sách nhắc nợ"));
      } finally {
        this.loading = false;
      }
    },
    applyFilters() {
      this.pagination.current_page = 1;
      this.fetchReminders(1);
    },
    async fetchStores() {
      try {
        const res = await ApiService.query("/api/auth/store/all");
        this.stores = res.data?.data || res.data || [];
      } catch (e) {
        this.stores = [];
      }
    },
    resetFilters() {
      this.filters = { search: "", contract_type: "", status: "", store_id: "" };
      this.applyFilters();
    },
    changePage(page) {
      this.pagination.current_page = page;
      this.fetchReminders(page);
    },
    async scanReminders() {
      this.scanning = true;
      try {
        const response = await ApiService.post("/api/auth/customer-reminders/scan", {});
        const result = response.data.data || {};
        this.$message.success(
          `Đã cập nhật ${Number(result.created || 0)} việc nhắc mới; bỏ qua ${Number(result.skipped || 0)} việc đã có.`
        );
        this.pagination.current_page = 1;
        await this.fetchReminders(1);
      } catch (error) {
        this.$message.error(this.errorMessage(error, "Không thể quét các khoản đến hạn"));
      } finally {
        this.scanning = false;
      }
    },
    async checkQueue() {
      this.dispatching = true;
      try {
        const response = await ApiService.post("/api/auth/customer-reminders/dispatch", {
          dry_run: true,
        });
        const result = response.data.data || {};
        this.$message.success(
          `Đã kiểm tra ${Number(result.processed || 0)} việc trong hàng đợi. Không có tin nhắn nào được gửi.`
        );
        await this.fetchReminders();
      } catch (error) {
        this.$message.error(this.errorMessage(error, "Không thể kiểm tra hàng đợi"));
      } finally {
        this.dispatching = false;
      }
    },
    errorMessage(error, fallback) {
      if (error && error.response && error.response.data && error.response.data.message) {
        return error.response.data.message;
      }
      return fallback;
    },
    contractTypeLabel(type) {
      return type === "lease" ? "Thuê sở hữu" : "Thuê xe";
    },
    stageLabel(stage) {
      const labels = {
        due_soon_3d: "Còn 3 ngày",
        due_soon_1d: "Còn 1 ngày",
        due_today: "Đến hạn hôm nay",
        overdue_1_7d: "Quá hạn 1–7 ngày",
        overdue_8_30d: "Quá hạn 8–30 ngày",
        overdue_1_5d: "Nợ sớm 1–5 ngày",
        overdue_6_30d: "Nợ muộn 6–30 ngày",
        overdue_30_plus: "Quá hạn trên 30 ngày",
        return_tomorrow: "Trả xe ngày mai",
        return_today: "Trả xe hôm nay",
        overdue_return: "Quá hạn trả xe",
      };
      return labels[stage] || stage || "Chưa phân loại";
    },
    stageClass(stage) {
      if (["overdue_8_30d", "overdue_6_30d", "overdue_30_plus", "overdue_return"].includes(stage)) {
        return "stage-danger";
      }
      if (["overdue_1_7d", "overdue_1_5d", "due_today", "return_today"].includes(stage)) {
        return "stage-warning";
      }
      return "stage-neutral";
    },
    statusLabel(status) {
      const labels = {
        pending: "Đang chờ",
        sent: "Đã gửi",
        failed: "Thất bại",
        skipped: "Đã bỏ qua",
        cancelled: "Đã hủy",
      };
      return labels[status] || status || "Chưa xác định";
    },
    statusClass(status) {
      return `status-${status || "unknown"}`;
    },
    formatDate(value) {
      if (!value) return "Chưa xác định";
      const parsed = new Date(value);
      if (Number.isNaN(parsed.getTime())) return value;
      return parsed.toLocaleString("vi-VN");
    },
  },
};
</script>

<style scoped>
.customer-reminders-page {
  color: #263238;
}

.page-heading,
.result-header,
.pagination-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.eyebrow {
  color: #b30f18;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.08em;
}

.page-actions {
  display: flex;
  gap: 10px;
  flex-shrink: 0;
}

.notice-box {
  padding: 13px 16px;
  border: 1px solid #efc3c6;
  border-radius: 8px;
  background: #fff7f7;
  color: #7d2026;
  font-weight: 500;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

.summary-card {
  min-height: 118px;
  padding: 20px;
  border: 1px solid #e5e9ef;
  border-top: 4px solid #b30f18;
  border-radius: 10px;
  background: #ffffff;
  box-shadow: 0 8px 24px rgba(31, 41, 55, 0.06);
}

.summary-card-danger {
  background: #fff7f7;
}

.summary-card-muted {
  border-top-color: #6b7280;
}

.summary-card strong {
  display: block;
  margin: 5px 0 2px;
  color: #1f2937;
  font-size: 28px;
  line-height: 1.1;
}

.summary-card small,
.summary-label {
  color: #6b7280;
}

.summary-label,
.filter-label {
  display: block;
  margin-bottom: 7px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.03em;
}

.filter-card,
.result-card {
  border: 1px solid #e5e9ef;
  box-shadow: 0 8px 26px rgba(31, 41, 55, 0.05);
}

.filter-actions {
  justify-content: flex-end;
}

.result-header {
  min-height: 78px;
  padding: 16px 24px;
}

.reminder-table th {
  padding: 14px 18px;
  border-top: 0;
  border-bottom: 1px solid #e5e9ef;
  background: #f7f8fa;
  color: #56606d;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.025em;
  white-space: nowrap;
}

.reminder-table td {
  padding: 16px 18px;
  border-color: #edf0f3;
  vertical-align: middle;
}

.message-cell {
  min-width: 280px;
  max-width: 420px;
  line-height: 1.5;
}

.type-label,
.stage-label,
.status-label {
  display: inline-block;
  padding: 5px 9px;
  border-radius: 5px;
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
}

.type-label,
.stage-neutral,
.status-skipped,
.status-cancelled,
.status-unknown {
  background: #f0f2f5;
  color: #4b5563;
}

.stage-warning,
.status-pending {
  background: #fff4dc;
  color: #8b5a00;
}

.stage-danger,
.status-failed {
  background: #fff0f1;
  color: #b30f18;
}

.status-sent {
  background: #e9f8ef;
  color: #22643a;
}

.error-text {
  color: #b30f18;
}

.empty-state {
  display: flex;
  min-height: 220px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 7px;
  padding: 30px;
  color: #6b7280;
  text-align: center;
}

.empty-state strong {
  color: #263238;
  font-size: 18px;
}

.pagination-row {
  padding: 18px 24px;
  border-top: 1px solid #edf0f3;
}

@media (max-width: 767px) {
  .page-heading,
  .result-header,
  .pagination-row {
    align-items: stretch;
    flex-direction: column;
  }

  .page-actions,
  .page-actions .btn,
  .filter-actions,
  .filter-actions .btn {
    width: 100%;
  }

  .page-actions,
  .filter-actions {
    flex-direction: column;
  }

  .page-actions .btn,
  .filter-actions .btn {
    margin-right: 0 !important;
  }

  .summary-grid {
    grid-template-columns: 1fr;
  }

  .pagination-row {
    align-items: center;
  }
}
</style>
