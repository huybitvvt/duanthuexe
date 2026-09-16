<template>
  <div class="lease-management">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h3 class="font-weight-bold mb-1 text-dark">Quản lý Thuê sở hữu & Công nợ</h3>
        <span class="text-muted font-weight-bold">
          Theo dõi hợp đồng thuê sở hữu, lịch trả góp định kỳ, nhắc nợ và thu tiền tập trung
        </span>
      </div>
      <div class="d-flex align-items-center">
        <button class="btn btn-outline-success mr-2 font-weight-bold" :disabled="exporting" @click="handleExportExcel">
          {{ exporting ? 'Đang xuất...' : 'Xuất Excel công nợ' }}
        </button>

        <button class="btn btn-primary font-weight-bold mr-2" @click="openCreateModal">
          Tạo HĐ Thuê sở hữu
        </button>

        <button class="btn btn-light-primary font-weight-bold" @click="refreshData">
          {{ loading ? 'Đang tải...' : 'Làm mới' }}
        </button>
      </div>
    </div>

    <!-- Hàng thẻ thống kê KPI -->
    <div class="row mb-4" v-loading="loadingStats">
      <div class="col-xl-3 col-md-6 mb-4">
        <div class="card card-custom bg-light-primary p-4 border-0">
          <div>
            <span class="text-muted font-weight-bold font-size-sm d-block">TỔNG HỢP ĐỒNG</span>
            <span class="font-size-h3 font-weight-bolder text-primary">
              {{ stats ? stats.total_contracts : 0 }}
            </span>
            <span class="text-muted font-size-xs d-block mt-1">
              Đang thực hiện: <strong>{{ stats ? stats.active_contracts : 0 }}</strong> HĐ
            </span>
          </div>
        </div>
      </div>

      <div class="col-xl-3 col-md-6 mb-4">
        <div class="card card-custom bg-light-danger p-4 border-0">
          <div>
            <span class="text-muted font-weight-bold font-size-sm d-block">TỔNG DƯ NỢ CÒN LẠI</span>
            <span class="font-size-h3 font-weight-bolder text-danger">
              {{ (stats ? stats.total_remaining_debt : 0) | formatPrice }}
            </span>
            <span class="text-muted font-size-xs d-block mt-1">
              Tổng thu dự kiến từ các kỳ
            </span>
          </div>
        </div>
      </div>

      <div class="col-xl-3 col-md-6 mb-4">
        <div class="card card-custom bg-light-warning p-4 border-0">
          <div>
            <span class="text-muted font-weight-bold font-size-sm d-block">HỢP ĐỒNG QUÁ HẠN</span>
            <span class="font-size-h3 font-weight-bolder text-warning">
              {{ stats ? stats.overdue_contracts : 0 }}
            </span>
            <span class="text-muted font-size-xs d-block mt-1">
              Chiếm <strong>{{ overduePercentage }}%</strong> tổng hợp đồng
            </span>
          </div>
        </div>
      </div>

      <div class="col-xl-3 col-md-6 mb-4">
        <div class="card card-custom bg-light-success p-4 border-0">
          <div>
            <span class="text-muted font-weight-bold font-size-sm d-block">ĐÃ THU LŨY KẾ</span>
            <span class="font-size-h3 font-weight-bolder text-success">
              {{ (stats ? stats.total_collected : 0) | formatPrice }}
            </span>
            <span class="text-muted font-size-xs d-block mt-1">
              Tiền cọc & các kỳ đã đóng
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Nhóm nút lọc theo độ trễ công nợ (Aging Buckets) -->
    <div class="card card-custom gutter-b">
      <div class="card-body py-3 px-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2 mb-md-0">
            <span class="font-weight-bold text-muted mr-3 font-size-sm">PHÂN LOẠI CÔNG NỢ:</span>
            <button
              type="button"
              class="btn btn-sm mr-2"
              :class="query.aging_bucket === '' ? 'btn-primary' : 'btn-light'"
              @click="setAgingBucket('')"
            >
              Tất cả ({{ stats ? stats.total_contracts : 0 }})
            </button>
            <button
              type="button"
              class="btn btn-sm mr-2 font-weight-bold"
              :class="query.aging_bucket === 'current' ? 'btn-success' : 'btn-light-success'"
              @click="setAgingBucket('current')"
            >
              Đúng hạn ({{ stats?.aging_buckets?.current || 0 }})
            </button>
            <button
              type="button"
              class="btn btn-sm mr-2 font-weight-bold"
              :class="query.aging_bucket === 'overdue_1_7' ? 'btn-warning' : 'btn-light-warning'"
              @click="setAgingBucket('overdue_1_7')"
            >
              Quá hạn 1-7 ngày ({{ stats?.aging_buckets?.overdue_1_7 || 0 }})
            </button>
            <button
              type="button"
              class="btn btn-sm mr-2 font-weight-bold"
              :class="query.aging_bucket === 'overdue_8_30' ? 'btn-danger' : 'btn-light-danger'"
              @click="setAgingBucket('overdue_8_30')"
            >
              Quá hạn 8-30 ngày ({{ stats?.aging_buckets?.overdue_8_30 || 0 }})
            </button>
            <button
              type="button"
              class="btn btn-sm mr-2 font-weight-bold"
              :class="query.aging_bucket === 'overdue_30_plus' ? 'btn-dark' : 'btn-light-dark'"
              @click="setAgingBucket('overdue_30_plus')"
            >
              Quá hạn >30 ngày ({{ stats?.aging_buckets?.overdue_30_plus || 0 }})
            </button>
          </div>

          <div class="text-muted font-size-sm">
            Hiển thị <strong>{{ contracts.length }}</strong> hợp đồng
          </div>
        </div>
      </div>
    </div>

    <!-- Thanh tìm kiếm & bộ lọc -->
    <div class="card card-custom gutter-b">
      <div class="card-body p-4">
        <div class="row align-items-center">
          <div class="col-md-5 mb-2 mb-md-0">
            <el-input
              v-model="query.search"
              placeholder="Tìm theo Mã HĐ, Tên KH, Số điện thoại, Biển số xe..."
              clearable
              @keyup.enter.native="handleSearch"
            />
          </div>
          <div class="col-md-3 mb-2 mb-md-0">
            <el-select
              v-model="query.status"
              placeholder="Trạng thái hợp đồng"
              clearable
              class="w-100"
              @change="handleSearch"
            >
              <el-option label="Tất cả trạng thái" value="" />
              <el-option label="Đang thực hiện (active)" value="active" />
              <el-option label="Đã tất toán (completed)" value="completed" />
              <el-option label="Đã hủy" value="cancelled" />
              <el-option label="Vi phạm thanh toán" value="defaulted" />
            </el-select>
          </div>
          <div class="col-md-4 d-flex justify-content-md-end">
            <button class="btn btn-primary font-weight-bold mr-2" @click="handleSearch">
              Tìm kiếm
            </button>
            <button class="btn btn-light font-weight-bold" @click="resetQuery">
              Xóa lọc
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Bảng danh sách hợp đồng Thuê sở hữu -->
    <div class="card card-custom" v-loading="loading">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-head-custom table-vertical-center table-hover mb-0">
            <thead class="thead-light">
              <tr>
                <th style="width: 120px;">Mã HĐ</th>
                <th style="min-width: 180px;">Khách hàng</th>
                <th style="min-width: 150px;">Xe bàn giao</th>
                <th style="min-width: 140px;">Kỳ hạn & Kỳ thu</th>
                <th class="text-right" style="min-width: 130px;">Tổng giá trị</th>
                <th class="text-right" style="min-width: 120px;">Đã đóng</th>
                <th class="text-right" style="min-width: 130px;">Dư nợ còn lại</th>
                <th class="text-center" style="min-width: 140px;">Tình trạng nợ</th>
                <th style="min-width: 160px;">Đôn đốc gần nhất</th>
                <th class="text-center" style="min-width: 180px;">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="contracts.length === 0">
                <td colspan="10" class="text-center py-5 text-muted">
                  <div class="font-weight-bold mb-1">Chưa có hợp đồng nào</div>
                  Không tìm thấy hợp đồng thuê sở hữu nào phù hợp với điều kiện tìm kiếm.
                </td>
              </tr>
              <tr
                v-for="item in contracts"
                :key="item.id"
                :class="{'bg-light-danger-soft': item.aging_bucket && item.aging_bucket.startsWith('overdue')}"
              >
                <!-- Mã HĐ -->
                <td>
                  <span class="font-weight-bolder text-primary d-block">{{ item.contract_code }}</span>
                  <span class="text-muted font-size-xs">{{ item.start_date | formatDate }}</span>
                </td>

                <!-- Khách hàng -->
                <td>
                  <div class="font-weight-bold text-dark">{{ item.customer?.name }}</div>
                  <div class="font-size-xs text-muted">
                    <a :href="'tel:' + item.customer?.phone" class="text-primary font-weight-bold">
                      {{ item.customer?.phone }}
                    </a>
                  </div>
                  <div v-if="item.customer?.id_card" class="font-size-xs text-muted">
                    CCCD: {{ item.customer?.id_card }}
                  </div>
                </td>

                <!-- Xe bàn giao -->
                <td>
                  <span class="badge badge-light-success font-weight-bold">{{ item.vehicle?.license }}</span>
                  <div class="font-size-xs text-dark mt-1">{{ item.vehicle?.name }}</div>
                  <div class="font-size-xs text-muted">{{ item.store?.name || 'Kho Thuê sở hữu' }}</div>
                </td>

                <!-- Kỳ hạn -->
                <td>
                  <div class="font-weight-bold">{{ item.installment_count }} tháng</div>
                  <div class="font-size-xs text-muted">
                    {{ item.period_amount | formatPrice }} / kỳ
                  </div>
                  <div class="progress progress-xs mt-1" style="height: 4px;">
                    <div
                      class="progress-bar bg-success"
                      role="progressbar"
                      :style="{ width: calculateProgress(item) + '%' }"
                    ></div>
                  </div>
                </td>

                <!-- Tổng giá trị -->
                <td class="text-right font-weight-bold">
                  {{ item.total_amount | formatPrice }}
                </td>

                <!-- Đã đóng -->
                <td class="text-right text-success font-weight-bold">
                  {{ item.total_paid | formatPrice }}
                </td>

                <!-- Dư nợ còn lại -->
                <td class="text-right font-weight-bolder" :class="item.remaining_debt > 0 ? 'text-danger' : 'text-muted'">
                  {{ item.remaining_debt | formatPrice }}
                </td>

                <!-- Tình trạng nợ (Aging badge) -->
                <td class="text-center">
                  <span :class="getAgingBadgeClass(item.aging_bucket)">
                    {{ getAgingLabel(item.aging_bucket) }}
                  </span>
                  <div v-if="item.overdue_days > 0" class="text-danger font-size-xs mt-1 font-weight-bold">
                    Trễ {{ item.overdue_days }} ngày
                  </div>
                  <div v-else class="text-muted font-size-xs mt-1">
                    {{ item.status_label || item.status }}
                  </div>
                </td>

                <!-- Đôn đốc gần nhất -->
                <td>
                  <div v-if="item.latest_debt_note">
                    <span :class="getStatusBadgeClass(item.latest_debt_note.call_status)">
                      {{ item.latest_debt_note.notes }}
                    </span>
                    <div v-if="item.latest_debt_note.promised_date" class="text-primary font-size-xs mt-1 font-weight-bold">
                      Hẹn: {{ item.latest_debt_note.promised_date | formatDate }}
                    </div>
                  </div>
                  <div v-else class="text-muted font-size-xs">
                    Chưa có lượt nhắc
                  </div>
                </td>

                <!-- Thao tác -->
                <td class="text-center">
                  <div class="btn-group" role="group">
                    <!-- Lịch trả góp -->
                    <button
                      type="button"
                      class="btn btn-sm btn-light-primary font-weight-bold mr-1"
                      title="Xem lịch trả góp"
                      @click="openScheduleModal(item.id)"
                    >
                      Lịch trả
                    </button>

                    <!-- Thu tiền kỳ -->
                    <button
                      v-if="item.remaining_debt > 0"
                      type="button"
                      class="btn btn-sm btn-light-success font-weight-bold mr-1"
                      title="Thu tiền kỳ / Trả góp"
                      @click="openPaymentModal(item)"
                    >
                      Thu tiền
                    </button>

                    <!-- Nhắc nợ / Ghi chú đôn đốc -->
                    <button
                      type="button"
                      class="btn btn-sm btn-light-warning font-weight-bold"
                      title="Đôn đốc & ghi chú nhắc nợ"
                      @click="openDebtNoteModal(item)"
                    >
                      Nhắc nợ
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Phân trang -->
        <div v-if="pagination.total > pagination.per_page" class="d-flex justify-content-between align-items-center p-4 border-top">
          <span class="text-muted font-size-sm">
            Hiển thị {{ pagination.from }} - {{ pagination.to }} trên tổng số {{ pagination.total }} hợp đồng
          </span>
          <b-pagination
            v-model="query.page"
            :total-rows="pagination.total"
            :per-page="pagination.per_page"
            size="sm"
            @change="handlePageChange"
          />
        </div>
      </div>
    </div>

    <!-- Modals -->
    <ModalLeaseCreate ref="modalCreate" @success="handleModalSuccess" />
    <ModalLeasePayment ref="modalPayment" @success="handleModalSuccess" />
    <ModalDebtNote ref="modalDebtNote" @success="handleModalSuccess" />
    <ModalInstallmentSchedule
      ref="modalSchedule"
      @pay-installment="handlePayFromSchedule"
      @open-debt-note="openDebtNoteModal"
    />
  </div>
</template>

<script>
import {
  LEASE_GET_CONTRACTS,
  LEASE_GET_STATS,
  LEASE_EXPORT,
} from "@/core/services/store/lease.module";
import ModalLeaseCreate from "./components/ModalLeaseCreate.vue";
import ModalLeasePayment from "./components/ModalLeasePayment.vue";
import ModalDebtNote from "./components/ModalDebtNote.vue";
import ModalInstallmentSchedule from "./components/ModalInstallmentSchedule.vue";
import Swal from "sweetalert2";

export default {
  name: "LeaseIndex",
  components: {
    ModalLeaseCreate,
    ModalLeasePayment,
    ModalDebtNote,
    ModalInstallmentSchedule,
  },
  data() {
    return {
      loading: false,
      loadingStats: false,
      exporting: false,
      contracts: [],
      stats: null,
      pagination: {
        total: 0,
        per_page: 15,
        current_page: 1,
        from: 0,
        to: 0,
      },
      query: {
        search: "",
        status: "",
        aging_bucket: "",
        page: 1,
        per_page: 15,
      },
    };
  },
  computed: {
    overduePercentage() {
      if (!this.stats || !this.stats.total_contracts) return 0;
      return Math.round((this.stats.overdue_contracts / this.stats.total_contracts) * 100);
    },
  },
  created() {
    this.fetchStats();
    this.fetchContracts();
  },
  methods: {
    fetchStats() {
      this.loadingStats = true;
      this.$store
        .dispatch(LEASE_GET_STATS)
        .then((res) => {
          this.stats = res?.data || null;
        })
        .catch(() => {})
        .finally(() => {
          this.loadingStats = false;
        });
    },
    fetchContracts() {
      this.loading = true;
      this.$store
        .dispatch(LEASE_GET_CONTRACTS, this.query)
        .then((res) => {
          const paginated = res?.data;
          if (paginated && paginated.data) {
            this.contracts = paginated.data;
            this.pagination = {
              total: paginated.total || 0,
              per_page: paginated.per_page || 15,
              current_page: paginated.current_page || 1,
              from: paginated.from || 0,
              to: paginated.to || 0,
            };
          } else {
            this.contracts = paginated || [];
          }
        })
        .catch((err) => {
          console.error("Error fetching lease contracts:", err);
        })
        .finally(() => {
          this.loading = false;
        });
    },
    refreshData() {
      this.fetchStats();
      this.fetchContracts();
    },
    handleSearch() {
      this.query.page = 1;
      this.fetchContracts();
    },
    resetQuery() {
      this.query = {
        search: "",
        status: "",
        aging_bucket: "",
        page: 1,
        per_page: 15,
      };
      this.fetchContracts();
    },
    setAgingBucket(bucket) {
      this.query.aging_bucket = bucket;
      this.query.page = 1;
      this.fetchContracts();
    },
    handlePageChange(page) {
      this.query.page = page;
      this.fetchContracts();
    },
    calculateProgress(item) {
      const total = Number(item.total_amount) || 1;
      const paid = Number(item.total_paid) || 0;
      return Math.min(100, Math.round((paid / total) * 100));
    },
    getAgingLabel(bucket) {
      const map = {
        current: "Đúng hạn",
        overdue_1_7: "Quá hạn 1-7 ngày",
        overdue_8_30: "Quá hạn 8-30 ngày",
        overdue_30_plus: "Quá hạn >30 ngày",
      };
      return map[bucket] || "Bình thường";
    },
    getAgingBadgeClass(bucket) {
      const map = {
        current: "badge badge-success",
        overdue_1_7: "badge badge-warning",
        overdue_8_30: "badge badge-danger",
        overdue_30_plus: "badge badge-dark font-weight-bolder",
      };
      return map[bucket] || "badge badge-secondary";
    },
    getStatusLabel(status) {
      const map = {
        connected: "Nghe máy",
        promise: "Hẹn ngày",
        no_answer: "Không nghe máy",
        busy: "Máy bận",
        dispute: "Khiếu nại",
        other: "Khác",
      };
      return map[status] || status;
    },
    getStatusBadgeClass(status) {
      const map = {
        connected: "badge badge-light-success font-size-xs",
        promise: "badge badge-light-primary font-size-xs",
        no_answer: "badge badge-light-warning font-size-xs",
        busy: "badge badge-light-secondary font-size-xs",
        dispute: "badge badge-light-danger font-size-xs",
        other: "badge badge-light-info font-size-xs",
      };
      return map[status] || "badge badge-light font-size-xs";
    },
    openCreateModal() {
      this.$refs.modalCreate.open();
    },
    openScheduleModal(contractId) {
      this.$refs.modalSchedule.open(contractId);
    },
    openPaymentModal(contract, suggestedAmount = null) {
      this.$refs.modalPayment.open(contract, suggestedAmount);
    },
    openDebtNoteModal(contract) {
      this.$refs.modalDebtNote.open(contract);
    },
    handlePayFromSchedule({ contract, amount }) {
      this.openPaymentModal(contract, amount);
    },
    handleModalSuccess() {
      this.refreshData();
    },
    handleExportExcel() {
      this.exporting = true;
      this.$store
        .dispatch(LEASE_EXPORT, this.query)
        .then(() => {
          Swal.fire("Thành công", "Đã xuất file Excel công nợ thuê sở hữu thành công.", "success");
        })
        .catch((err) => {
          const msg = err?.data?.message || err?.message || "Không thể tải file Excel.";
          Swal.fire("Lỗi", msg, "error");
        })
        .finally(() => {
          this.exporting = false;
        });
    },
  },
};
</script>

<style scoped>
.bg-light-danger-soft {
  background-color: #fff9f9;
}
.gap-2 {
  gap: 0.5rem;
}
</style>
