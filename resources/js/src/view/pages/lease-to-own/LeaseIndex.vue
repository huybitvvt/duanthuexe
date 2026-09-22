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

        <router-link
          :to="{ name: 'warehouse', query: { store_id: 6 } }"
          class="btn btn-outline-warning font-weight-bold mr-2"
          title="Xem kho xe riêng của phòng Thuê sở hữu"
        >
          <i class="fas fa-warehouse mr-1"></i>Kho xe Thuê sở hữu &rarr;
        </router-link>

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
            <span class="text-dark font-weight-bolder font-size-sm d-block">TỔNG HỢP ĐỒNG</span>
            <span class="font-size-h3 font-weight-bolder text-primary">
              {{ stats ? stats.total_contracts : 0 }}
            </span>
            <span class="text-dark font-size-xs d-block mt-1 font-weight-bold">
              Đang thực hiện: <strong class="text-primary">{{ stats ? stats.active_contracts : 0 }}</strong> HĐ
            </span>
          </div>
        </div>
      </div>

      <div class="col-xl-3 col-md-6 mb-4">
        <div class="card card-custom bg-light-danger p-4 border-0">
          <div>
            <span class="text-dark font-weight-bolder font-size-sm d-block">TỔNG DƯ NỢ CÒN LẠI</span>
            <span class="font-size-h3 font-weight-bolder text-danger">
              {{ (stats ? stats.total_outstanding : 0) | formatPrice }}
            </span>
            <span class="text-dark font-size-xs d-block mt-1 font-weight-bold">
              Tổng thu dự kiến từ các kỳ
            </span>
          </div>
        </div>
      </div>

      <div class="col-xl-3 col-md-6 mb-4">
        <div class="card card-custom bg-light-warning p-4 border-0">
          <div>
            <span class="text-dark font-weight-bolder font-size-sm d-block">HỢP ĐỒNG QUÁ HẠN</span>
            <span class="font-size-h3 font-weight-bolder text-warning">
              {{ overdueContracts }}
            </span>
            <span class="text-dark font-size-xs d-block mt-1 font-weight-bold">
              Chiếm <strong class="text-danger">{{ overduePercentage }}%</strong> tổng hợp đồng
            </span>
          </div>
        </div>
      </div>

      <div class="col-xl-3 col-md-6 mb-4">
        <div class="card card-custom bg-light-success p-4 border-0">
          <div>
            <span class="text-dark font-weight-bolder font-size-sm d-block">ĐÃ THU LŨY KẾ</span>
            <span class="font-size-h3 font-weight-bolder text-success">
              {{ (stats ? stats.total_collected : 0) | formatPrice }}
            </span>
            <span class="text-dark font-size-xs d-block mt-1 font-weight-bold">
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
            <span class="font-weight-bolder text-dark mr-3 font-size-sm">PHÂN LOẠI CÔNG NỢ:</span>
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
              Đúng hạn ({{ stats?.buckets?.counts?.current || 0 }})
            </button>
            <button
              type="button"
              class="btn btn-sm mr-2 font-weight-bold"
              :class="query.aging_bucket === 'overdue_1_5' ? 'btn-warning' : 'btn-light-warning'"
              @click="setAgingBucket('overdue_1_5')"
            >
              Nợ sớm 1-5 ngày ({{ stats?.buckets?.counts?.overdue_1_5 || 0 }})
            </button>
            <button
              type="button"
              class="btn btn-sm mr-2 font-weight-bold"
              :class="query.aging_bucket === 'overdue_6_30' ? 'btn-danger' : 'btn-light-danger'"
              @click="setAgingBucket('overdue_6_30')"
            >
              Nợ muộn 6-30 ngày ({{ stats?.buckets?.counts?.overdue_6_30 || 0 }})
            </button>
            <button
              type="button"
              class="btn btn-sm mr-2 font-weight-bold"
              :class="query.aging_bucket === 'overdue_30_plus' ? 'btn-dark' : 'btn-light-dark'"
              @click="setAgingBucket('overdue_30_plus')"
            >
              Cần thu hồi >30 ngày ({{ stats?.buckets?.counts?.overdue_30_plus || 0 }})
            </button>
          </div>

          <div class="text-dark font-size-sm font-weight-bold">
            Hiển thị <strong class="text-primary">{{ contracts.length }}</strong> hợp đồng
          </div>
        </div>
      </div>
    </div>

    <!-- Thanh tìm kiếm & bộ lọc -->
    <div class="card card-custom gutter-b">
      <div class="card-body p-4">
        <div class="row align-items-center">
          <div class="col-md-5 mb-2 mb-md-0">
            <search-suggest endpoint="/api/auth/lease-contracts" :params="query" query-key="search" fields="contract_code,customer.name,customer.phone,customer.id_card,vehicle.name,vehicle.license" @select="handleSearch" @submit="handleSearch"
              v-model="query.search"
              placeholder="Tìm theo Mã HĐ, Tên KH, Số điện thoại, Biển số xe..."
              clearable
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
                <th class="text-center" style="min-width: 260px;">Thao tác</th>
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
                  <div class="font-size-xs text-muted">{{ item.store?.store_name || 'Kho sở hữu' }}</div>
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
                <td class="text-right font-weight-bolder" :class="item.outstanding_balance > 0 ? 'text-danger' : 'text-muted'">
                  {{ item.outstanding_balance | formatPrice }}
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
                  <div v-if="item.latest_note">
                    <span class="text-dark font-size-xs">
                      {{ item.latest_note.content }}
                    </span>
                    <div v-if="item.latest_note.appointment_date" class="text-primary font-size-xs mt-1 font-weight-bold">
                      Hẹn: {{ item.latest_note.appointment_date }}
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
                      v-if="item.outstanding_balance > 0"
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
                      class="btn btn-sm btn-light-warning font-weight-bold mr-1"
                      title="Đôn đốc & ghi chú nhắc nợ"
                      @click="openDebtNoteModal(item)"
                    >
                      Nhắc nợ
                    </button>

                    <!-- Dropdown Hành động đôn đốc nợ -->
                    <b-dropdown
                      size="sm"
                      variant="outline-warning"
                      class="mr-1"
                      right
                    >
                      <template #button-content>
                        <span class="font-weight-bold">Hành động</span>
                      </template>
                      <b-dropdown-header class="font-size-xs text-uppercase font-weight-bold">
                        Đôn đốc / Nhắc nợ
                      </b-dropdown-header>
                      <b-dropdown-item
                        v-for="act in debtActions"
                        :key="act.value"
                        @click="openDebtNoteModal(item, act.value)"
                      >
                        <span :class="['badge mr-2', act.badgeClass]">&bull;</span>
                        <span class="font-weight-bold">{{ act.label }}</span>
                      </b-dropdown-item>
                    </b-dropdown>
                    <!-- Dropdown In tài liệu: đủ 5 mẫu hợp đồng & biên bản theo yêu cầu -->
                    <b-dropdown
                      size="sm"
                      variant="light-info"
                      class="ml-1"
                      right
                      text="In tài liệu"
                    >
                      <b-dropdown-header class="font-size-xs text-uppercase font-weight-bold">
                        5 Mẫu hợp đồng & Biên bản
                      </b-dropdown-header>
                      <b-dropdown-item :disabled="downloadingDoc === item.id" @click="downloadPdf(item, 'pdf')">
                        1. Hợp đồng thuê xe (mẫu phổ thông)
                      </b-dropdown-item>
                      <b-dropdown-item :disabled="downloadingDoc === item.id" @click="openLegalDocument(item, 'handover')">
                        2. Biên bản bàn giao xe (mẫu 2 liên)
                      </b-dropdown-item>
                      <b-dropdown-item :disabled="downloadingDoc === item.id" @click="openAnnexDocument(item, 6)">
                        3. Phụ lục HĐ thuê 6 tháng (SH06)
                      </b-dropdown-item>
                      <b-dropdown-item :disabled="downloadingDoc === item.id" @click="openAnnexDocument(item, 12)">
                        4. Phụ lục HĐ thuê 12 tháng (SH12)
                      </b-dropdown-item>
                      <b-dropdown-item :disabled="downloadingDoc === item.id" @click="openAnnexDocument(item, 24)">
                        5. Phụ lục HĐ thuê 24 tháng (SH24)
                      </b-dropdown-item>
                      <b-dropdown-divider></b-dropdown-divider>
                      <b-dropdown-item :disabled="downloadingDoc === item.id" @click="downloadPdf(item, 'debt-statement.pdf')">
                        Bảng đối soát công nợ PDF
                      </b-dropdown-item>
                    </b-dropdown>
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
    <ModalDebtNote ref="modalDebtNote" @success="handleModalSuccess" @open-payment="handlePayFromSchedule" />
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
import ApiService from "@/core/services/api.service";

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
      downloadingDoc: null,
      debtActions: [
        { value: "contacted", label: "Đã liên hệ", badgeClass: "badge-primary" },
        { value: "promise", label: "Hứa thanh toán", badgeClass: "badge-info" },
        { value: "no_answer", label: "Ko nghe máy", badgeClass: "badge-warning" },
        { value: "lost_contact", label: "Mất liên lạc", badgeClass: "badge-danger" },
        { value: "uncooperative", label: "Không hợp tác", badgeClass: "badge-danger" },
        { value: "paid", label: "Đã thanh toán", badgeClass: "badge-success" },
        { value: "recall_vehicle", label: "Cần thu hồi xe", badgeClass: "badge-dark" },
        { value: "check_vehicle", label: "Cần check xe", badgeClass: "badge-secondary" },
        { value: "collect_money", label: "Đi thu tiền", badgeClass: "badge-danger" },
      ],
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
    overdueContracts() {
      const counts = this.stats?.buckets?.counts || {};
      return Number(counts.overdue_1_5 || 0) + Number(counts.overdue_6_30 || 0) + Number(counts.overdue_30_plus || 0);
    },
    overduePercentage() {
      if (!this.stats || !this.stats.total_contracts) return 0;
      return Math.round((this.overdueContracts / this.stats.total_contracts) * 100);
    },
  },
  created() {
    this.fetchStats();
    this.fetchContracts();
  },
  mounted() {
    const contractId = Number(this.$route.query.open_contract || 0);
    if (contractId) this.$nextTick(() => this.openScheduleModal(contractId));
  },
  watch: {
    '$route.query.open_contract'(value) {
      const contractId = Number(value || 0);
      if (contractId) this.openScheduleModal(contractId);
    },
  },
  methods: {
    openAnnexDocument(item, months) {
      const query = months ? `?months=${months}` : '';
      this.openLegalDocument(item, `annex${query}`);
    },
    async openLegalDocument(item, path) {
      const tab = window.open("", "_blank");
      this.downloadingDoc = item.id;
      try {
        const response = await ApiService.download(`/api/auth/lease-contracts/${item.id}/${path}`);
        const url = window.URL.createObjectURL(new Blob([response.data], { type: "text/html;charset=utf-8" }));
        if (tab) tab.location.href = url;
        else window.open(url, "_blank");
        setTimeout(() => window.URL.revokeObjectURL(url), 60000);
      } catch (error) {
        if (tab) tab.close();
        Swal.fire("Lỗi", error?.data?.message || "Không mở được tài liệu.", "error");
      } finally {
        this.downloadingDoc = null;
      }
    },
    async downloadPdf(item, path) {
      this.downloadingDoc = item.id;
      try {
        const response = await ApiService.download(`/api/auth/lease-contracts/${item.id}/${path}`);
        const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = `${path === 'pdf' ? 'hop-dong' : 'doi-soat-no'}-${item.contract_code}.pdf`;
        link.click();
        setTimeout(() => window.URL.revokeObjectURL(url), 1000);
      } catch (error) {
        Swal.fire('Lỗi', error?.data?.message || 'Không tải được tài liệu PDF.', 'error');
      } finally {
        this.downloadingDoc = null;
      }
    },
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
        overdue_1_5: "Nợ sớm (1-5 ngày)",
        overdue_6_30: "Nợ muộn (6-30 ngày)",
        overdue_30_plus: "Cần thu hồi (>30 ngày)",
      };
      return map[bucket] || "Bình thường";
    },
    getAgingBadgeClass(bucket) {
      const map = {
        current: "badge badge-success",
        overdue_1_5: "badge badge-warning",
        overdue_6_30: "badge badge-danger",
        overdue_30_plus: "badge badge-dark font-weight-bolder",
      };
      return map[bucket] || "badge badge-secondary";
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
    openDebtNoteModal(contract, defaultAction = null) {
      this.$refs.modalDebtNote.open(contract, defaultAction);
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

.table.table-head-custom thead th,
.table-head-custom thead th,
.table thead th {
  color: #181C32 !important;
  font-weight: 700 !important;
  font-size: 0.85rem !important;
  letter-spacing: 0.5px;
}
</style>
