<template>
  <div class="customer-reminders-page">
    <div class="page-heading mb-5">
      <div>
        <p class="eyebrow mb-2">CHĂM SÓC CÔNG NỢ</p>
        <h2 class="font-weight-bolder text-dark mb-2">Nhắc nợ khách hàng</h2>
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

    <div class="notice-box mb-4">
      Hệ thống đang ở chế độ kiểm tra nội bộ. Nút “Kiểm tra hàng đợi” không gửi SMS, Zalo hoặc email cho khách.
    </div>

    <!-- Mốc thời gian nhắc nợ / Aging Bucket Tabs -->
    <div class="card card-custom gutter-b aging-tabs-card">
      <div class="card-body py-3 px-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
          <div class="d-flex flex-wrap align-items-center mb-2 mb-md-0">
            <span class="font-weight-bold mr-3 text-dark font-size-sm">Mốc nhắc nợ:</span>
            <button
              type="button"
              class="btn btn-sm mr-2 mb-1"
              :class="filters.debt_group === '' ? 'btn-primary' : 'btn-light'"
              @click="setDebtGroup('')"
            >
              Tất cả ({{ stats.total || pagination.total }})
            </button>
            <button
              type="button"
              class="btn btn-sm mr-2 mb-1 font-weight-bold"
              :class="filters.debt_group === 'due_today' ? 'btn-info' : 'btn-light-info'"
              @click="setDebtGroup('due_today')"
            >
              Đến hạn hôm nay ({{ stats.due_today || 0 }})
            </button>
            <button
              type="button"
              class="btn btn-sm mr-2 mb-1 font-weight-bold"
              :class="filters.debt_group === 'overdue_1_5' ? 'btn-warning' : 'btn-light-warning'"
              @click="setDebtGroup('overdue_1_5')"
            >
              Nợ sớm 1-5 ngày ({{ stats.overdue_1_5 || 0 }})
            </button>
            <button
              type="button"
              class="btn btn-sm mr-2 mb-1 font-weight-bold"
              :class="filters.debt_group === 'overdue_6_30' ? 'btn-danger' : 'btn-light-danger'"
              @click="setDebtGroup('overdue_6_30')"
            >
              Nợ muộn 6-30 ngày ({{ stats.overdue_6_30 || 0 }})
            </button>
            <button
              type="button"
              class="btn btn-sm mr-2 mb-1 font-weight-bold"
              :class="filters.debt_group === 'overdue_30_plus' ? 'btn-dark' : 'btn-light-dark'"
              @click="setDebtGroup('overdue_30_plus')"
            >
              Cần thu hồi >30 ngày ({{ stats.overdue_30_plus || 0 }})
            </button>
          </div>
          <div class="text-muted font-size-xs">
            Dữ liệu dạng chữ có thể kéo chuột để trỏ vào <strong>COPY</strong>
          </div>
        </div>
      </div>
    </div>

    <!-- Thanh tìm kiếm & bộ lọc -->
    <div class="card card-custom gutter-b filter-card">
      <div class="card-body p-4">
        <div class="row align-items-end">
          <div class="col-lg-4 col-md-12 mb-3 mb-lg-0">
            <label class="filter-label">Tìm khách hàng / Xe</label>
            <search-suggest
              endpoint="/api/auth/customer-reminders/action-list"
              :params="filters"
              query-key="search"
              fields="recipient_name,recipient_phone,message_content"
              v-model.trim="filters.search"
              clearable
              placeholder="Tên khách, SĐT, biển số hoặc ghi chú"
              @select="applyFilters"
              @submit="applyFilters"
              @clear="applyFilters"
            />
          </div>
          <div class="col-lg-2 col-md-4 mb-3 mb-lg-0">
            <label class="filter-label">Loại hợp đồng</label>
            <el-select v-model="filters.contract_type" class="w-100" @change="applyFilters">
              <el-option label="Tất cả loại HĐ" value="" />
              <el-option label="Thuê sở hữu" value="lease" />
              <el-option label="Thuê xe truyền thống" value="rental_order" />
            </el-select>
          </div>
          <div class="col-lg-3 col-md-4 mb-3 mb-lg-0">
            <label class="filter-label">Phòng giao dịch</label>
            <el-select v-model="filters.store_id" class="w-100" clearable placeholder="Tất cả phòng GD" @change="applyFilters">
              <el-option label="Tất cả phòng GD" value="" />
              <el-option v-for="store in stores" :key="store.id" :label="store.store_name" :value="store.id" />
            </el-select>
          </div>
          <div class="col-lg-3 col-md-4 d-flex filter-actions">
            <button type="button" class="btn btn-primary font-weight-bold mr-2" @click="applyFilters">
              Lọc danh sách
            </button>
            <button type="button" class="btn btn-light font-weight-bold" @click="resetFilters">
              Xóa lọc
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Bảng danh sách nhắc nợ / Quản lý công nợ -->
    <div class="card card-custom result-card" v-loading="loading">
      <div class="card-header border-0 result-header">
        <div>
          <h3 class="card-label font-weight-bolder text-dark mb-1">Danh sách cần liên hệ nhắc nợ</h3>
          <span class="text-muted">Ưu tiên các khoản nợ muộn (6-30 ngày) và xe cần thu hồi (>30 ngày).</span>
        </div>
        <button type="button" class="btn btn-sm btn-light font-weight-bold" :disabled="loading" @click="fetchReminders">
          Làm mới
        </button>
      </div>

      <div v-if="!loading && reminders.length === 0" class="empty-state">
        <strong>Chưa có việc nhắc nợ phù hợp</strong>
        <span>Hãy đổi bộ lọc hoặc bấm “Quét khoản đến hạn” để hệ thống tự động cập nhật danh sách.</span>
      </div>

      <div v-else class="table-responsive">
        <table class="table reminder-table mb-0 table-hover table-striped">
          <thead>
            <tr>
              <th style="min-width: 110px;">Ngày thuê</th>
              <th style="min-width: 170px;">Tên KH</th>
              <th style="min-width: 110px;">Gói thuê</th>
              <th style="min-width: 140px;">Loại xe</th>
              <th style="min-width: 120px;">Biển số</th>
              <th style="min-width: 110px;">Ngày đến hạn</th>
              <th style="min-width: 110px;" class="text-center">Ngày chậm</th>
              <th style="min-width: 130px;" class="text-right">Số tiền nợ</th>
              <th style="min-width: 130px;" class="text-center">Nhóm nợ tự động</th>
              <th style="min-width: 170px;" class="text-center">Hành Động</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in reminders" :key="item.id">
              <!-- Ngày thuê -->
              <td>
                <span class="copyable-text text-dark font-weight-bold">
                  {{ item.rental_start_date ? formatDateOnly(item.rental_start_date) : formatDateOnly(item.created_at) }}
                </span>
              </td>

              <!-- Tên KH -->
              <td>
                <span class="copyable-text font-weight-bolder text-dark d-block">
                  {{ item.customer_name || item.recipient_name || "Chưa có tên" }}
                </span>
                <div class="d-flex align-items-center mt-1">
                  <a :href="'tel:' + (item.customer_phone || item.recipient_phone)" class="text-primary font-weight-bold copyable-text font-size-xs mr-2">
                    {{ item.customer_phone || item.recipient_phone }}
                  </a>
                  <button type="button" class="btn btn-xs btn-icon btn-light-primary" title="Copy số điện thoại" @click="copyText(item.customer_phone || item.recipient_phone)">
                    <i class="flaticon2-copy font-size-xs"></i>
                  </button>
                </div>
                <button type="button" class="btn btn-link btn-xs p-0 text-muted d-block mt-1 font-weight-bold" @click="openContract(item)">
                  {{ item.contract_code || ('Đơn #' + item.contract_id) }} ↗
                </button>
              </td>

              <!-- Gói thuê -->
              <td>
                <span class="badge badge-light-primary font-weight-bold copyable-text">
                  {{ item.package_label || (item.contract_type === 'lease' ? 'Thuê sở hữu' : 'Thuê xe') }}
                </span>
              </td>

              <!-- Loại xe -->
              <td>
                <span class="copyable-text text-dark font-weight-bold">
                  {{ item.vehicle_type || 'Xe máy' }}
                </span>
              </td>

              <!-- Biển số -->
              <td>
                <span class="badge badge-light-dark font-weight-bolder copyable-text font-size-sm">
                  {{ item.plate_number || 'Chưa gán' }}
                </span>
              </td>

              <!-- Ngày đến hạn -->
              <td>
                <span class="copyable-text font-weight-bold text-dark">
                  {{ item.due_date ? formatDateOnly(item.due_date) : formatDateOnly(item.scheduled_at) }}
                </span>
              </td>

              <!-- Ngày chậm -->
              <td class="text-center">
                <span v-if="item.overdue_days > 0" class="text-danger font-weight-bolder font-size-h6 copyable-text">
                  {{ item.overdue_days }} ngày
                </span>
                <span v-else-if="item.overdue_days === 0" class="text-warning font-weight-bold copyable-text">
                  Hôm nay
                </span>
                <span v-else class="text-success font-weight-bold copyable-text">
                  Chưa chậm
                </span>
              </td>

              <!-- Số tiền nợ -->
              <td class="text-right">
                <span class="text-danger font-weight-bolder font-size-h6 copyable-text">
                  {{ formatMoney(item.debt_amount) }}
                </span>
              </td>

              <!-- Nhóm nợ tự động (1-5: Nợ sớm, 6-30: Nợ muộn, >30: Cần thu hồi) -->
              <td class="text-center">
                <span v-if="item.auto_debt_group === 'Cần thu hồi'" class="badge badge-dark font-weight-bolder px-2 py-1">
                  Cần thu hồi
                </span>
                <span v-else-if="item.auto_debt_group === 'Nợ muộn'" class="badge badge-danger font-weight-bolder px-2 py-1">
                  Nợ muộn
                </span>
                <span v-else-if="item.auto_debt_group === 'Nợ sớm'" class="badge badge-warning font-weight-bold px-2 py-1">
                  Nợ sớm
                </span>
                <span v-else class="badge badge-light-warning font-weight-bold px-2 py-1">
                  Đến hạn
                </span>
              </td>

              <!-- Hành Động: Nút Chi tiết & Dropdown with 9 action items -->
              <td class="text-center">
                <div class="d-flex align-items-center justify-content-center" style="gap: 6px;">
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-primary font-weight-bolder py-1 px-2"
                    title="Click xem chi tiết, người thân & lịch sử nhắc nợ"
                    @click="openDetailModal(item)"
                  >
                    Chi tiết
                  </button>

                  <el-dropdown trigger="click" @command="handleQuickAction(item, $event)">
                    <button
                      type="button"
                      class="btn btn-sm font-weight-bold dropdown-toggle d-inline-flex align-items-center py-1 px-2"
                      :class="item.contacted_today ? 'btn-light-success' : 'btn-danger'"
                    >
                      <span>{{ getActionBtnLabel(item) }}</span>
                    </button>
                    <el-dropdown-menu slot="dropdown" class="action-dropdown-menu">
                      <el-dropdown-item command="contacted">
                        <span class="text-success font-weight-bold">Đã liên hệ</span>
                      </el-dropdown-item>
                      <el-dropdown-item command="promise">
                        <span class="text-primary font-weight-bold">Hứa thanh toán</span>
                      </el-dropdown-item>
                      <el-dropdown-item command="no_answer">
                        <span class="text-warning font-weight-bold">Ko nghe máy</span>
                      </el-dropdown-item>
                      <el-dropdown-item command="lost_contact">
                        <span class="text-danger font-weight-bold">Mất liên lạc</span>
                      </el-dropdown-item>
                      <el-dropdown-item command="uncooperative">
                        <span class="text-danger font-weight-bold">Không hợp tác</span>
                      </el-dropdown-item>
                      <el-dropdown-item command="paid">
                        <span class="text-success font-weight-bolder">Đã thanh toán</span>
                      </el-dropdown-item>
                      <el-dropdown-item command="recall_vehicle">
                        <span class="text-danger font-weight-bolder">Cần thu hồi xe</span>
                      </el-dropdown-item>
                      <el-dropdown-item command="check_vehicle">
                        <span class="text-info font-weight-bold">Cần check xe</span>
                      </el-dropdown-item>
                      <el-dropdown-item command="collect_money">
                        <span class="text-dark font-weight-bold">Đi thu tiền</span>
                      </el-dropdown-item>
                      <el-dropdown-item divided command="open_modal">
                        <span class="text-primary font-weight-bolder">Chi tiết & Người thân</span>
                      </el-dropdown-item>
                    </el-dropdown-menu>
                  </el-dropdown>
                </div>

                <div v-if="item.last_contact_note" class="font-size-xs text-muted mt-1 text-truncate" style="max-width: 220px;" :title="item.last_contact_note">
                  {{ item.last_contact_note }}
                </div>
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

    <!-- Modal Chi tiết người thân & Ghi chú đôn đốc -->
    <modal-debt-note ref="modalDebtNote" @success="fetchReminders" @open-payment="forwardToPayment" />
  </div>
</template>

<script>
import ApiService from "@/core/services/api.service";
import ModalDebtNote from "@/view/pages/lease-to-own/components/ModalDebtNote.vue";

export default {
  name: "CustomerReminderIndex",
  components: {
    ModalDebtNote,
  },
  data() {
    return {
      loading: false,
      scanning: false,
      dispatching: false,
      reminders: [],
      filters: {
        search: "",
        contract_type: "",
        debt_group: "",
        status: "",
        store_id: "",
      },
      stats: {
        total: 0,
        overdue_1_5: 0,
        overdue_6_30: 0,
        overdue_30_plus: 0,
        due_today: 0,
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
  created() {
    if (this.$route.query.search) {
      this.filters.search = this.$route.query.search;
    }
    if (this.$route.query.store_id) {
      this.filters.store_id = Number(this.$route.query.store_id);
    }
    if (this.$route.query.debt_group) {
      this.filters.debt_group = this.$route.query.debt_group;
    }
    this.fetchStores();
    this.fetchReminders();
  },
  methods: {
    setDebtGroup(group) {
      this.filters.debt_group = group;
      this.applyFilters();
    },
    getActionBtnLabel(item) {
      if (item.last_action) {
        return this.getActionLabel(item.last_action);
      }
      if (item.contacted_today) {
        return "Đã liên hệ";
      }
      return "Hành động ▾";
    },
    getActionLabel(action) {
      const map = {
        contacted: "Đã liên hệ",
        promise: "Hứa thanh toán",
        no_answer: "Ko nghe máy",
        lost_contact: "Mất liên lạc",
        uncooperative: "Không hợp tác",
        paid: "Đã thanh toán",
        recall_vehicle: "Cần thu hồi xe",
        check_vehicle: "Cần check xe",
        collect_money: "Đi thu tiền",
      };
      return map[action] || action || "";
    },
    handleQuickAction(item, action) {
      this.openDetailModal(item, action === "open_modal" ? null : action);
    },
    openDetailModal(item, defaultAction = null) {
      const contractPayload = {
        id: item.contract_details?.id || item.contract_id,
        reminder_id: item.id,
        is_rental: item.contract_type === "rental_order",
        contract_code: item.contract_code || ("#" + item.contract_id),
        overdue_days: item.overdue_days || 0,
        outstanding_balance: item.debt_amount || 0,
        customer_name: item.customer_name || item.recipient_name,
        customer_phone: item.customer_phone || item.recipient_phone,
        plate_number: item.plate_number,
        vehicle_type: item.vehicle_type,
        customer_relatives: item.customer_relatives || (item.contract_details && item.contract_details.customer ? item.contract_details.customer.relatives : []),
        customer: item.contract_details?.customer || {
          name: item.customer_name || item.recipient_name,
          phone: item.customer_phone || item.recipient_phone,
          relatives: item.customer_relatives || [],
        },
        vehicle: item.contract_details?.vehicle || {
          license: item.plate_number,
          name: item.vehicle_type,
        },
        debt_notes: item.contract_details?.debt_notes || [],
        contact_logs: item.contact_logs || [],
      };
      this.$refs.modalDebtNote.open(contractPayload, defaultAction);
    },
    copyText(text) {
      if (!text) return;
      if (navigator && navigator.clipboard) {
        navigator.clipboard.writeText(text);
        this.$message.success(`Đã copy: ${text}`);
      } else {
        const input = document.createElement("input");
        input.value = text;
        document.body.appendChild(input);
        input.select();
        document.execCommand("copy");
        document.body.removeChild(input);
        this.$message.success(`Đã copy: ${text}`);
      }
    },
    openContract(item) {
      if (item.contract_type === "rental_order") {
        this.$router.push({ name: "car-rental", query: { open_order: item.contract_id } });
      } else {
        this.$router.push({ name: "lease-to-own", query: { open_contract: item.contract_id } });
      }
    },
    forwardToPayment({ contract, amount }) {
      const contractId = Number(contract && contract.id);
      if (!contractId) return;
      const query = {
        open_payment: contractId,
        payment_amount: amount ? Number(amount) : undefined,
      };
      this.$router.push({
        name: contract.is_rental ? "car-rental" : "lease-to-own",
        query,
      });
    },
    async fetchReminders(page = this.pagination.current_page) {
      this.loading = true;
      try {
        const response = await ApiService.query("/api/auth/customer-reminders/action-list", {
          page,
          per_page: this.pagination.per_page,
          search: this.filters.search || undefined,
          contract_type: this.filters.contract_type || undefined,
          debt_group: this.filters.debt_group || undefined,
          status: this.filters.status || undefined,
          store_id: this.filters.store_id || undefined,
        });
        const payload = response.data.data || response.data || {};
        this.reminders = Array.isArray(payload.data) ? payload.data : [];
        if (payload.stats) {
          this.stats = payload.stats;
        }
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
      this.filters = { search: "", contract_type: "", debt_group: "", status: "", store_id: "" };
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
    formatMoney(val) {
      if (!val && val !== 0) return "0đ";
      return Number(val).toLocaleString("vi-VN") + "đ";
    },
    formatDateOnly(val) {
      if (!val) return "Chưa xác định";
      const str = String(val).split("T")[0].split(" ")[0];
      const parts = str.split("-");
      if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
      }
      return val;
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
  padding: 11px 16px;
  border: 1px solid #efc3c6;
  border-radius: 8px;
  background: #fff7f7;
  color: #7d2026;
  font-weight: 500;
  font-size: 13px;
}

.aging-tabs-card,
.filter-card,
.result-card {
  border: 1px solid #e5e9ef;
  box-shadow: 0 4px 18px rgba(31, 41, 55, 0.04);
}

.filter-actions {
  justify-content: flex-end;
}

.result-header {
  min-height: 70px;
  padding: 16px 24px;
}

/* User-select: text and cursor: text across all table cells for COPY (Row 20) */
.reminder-table th,
.reminder-table td,
.copyable-text {
  user-select: text !important;
  -webkit-user-select: text !important;
  cursor: text;
}

.reminder-table th {
  padding: 12px 14px;
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
  padding: 12px 14px;
  border-color: #edf0f3;
  vertical-align: middle;
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
  padding: 16px 24px;
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
}
</style>
