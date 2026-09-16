<template>
  <div class="warehouse-management">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h3 class="font-weight-bold mb-1 text-dark">Quản lý Kho xe & Điều chuyển</h3>
        <span class="text-muted font-weight-bold">
          Hệ thống theo dõi tồn kho tập trung, phân quyền chi nhánh và sổ cái điều chuyển
        </span>
      </div>
      <div class="d-flex align-items-center">
        <!-- Nút Điều chuyển kho với 3 lựa chọn -->
        <b-dropdown
          variant="primary"
          right
          text="Điều chuyển kho"
          class="mr-2"
        >
          <template #button-content>
            <i class="fas fa-random mr-1"></i> Điều chuyển kho
          </template>
          <b-dropdown-item @click="openStoreTransferModal">
            <i class="fas fa-truck-moving text-primary mr-2"></i> 1. Chuyển xe giữa 2 kho
          </b-dropdown-item>
          <b-dropdown-item @click="openReturnDifferentStoreModal">
            <i class="fas fa-undo-alt text-success mr-2"></i> 2. Khách trả xe tại cơ sở khác
          </b-dropdown-item>
          <b-dropdown-item @click="openVehicleExchangeModal">
            <i class="fas fa-exchange-alt text-warning mr-2"></i> 3. Đổi xe sự cố cho hợp đồng
          </b-dropdown-item>
        </b-dropdown>

        <button class="btn btn-light-primary" @click="refreshData">
          <i class="fas fa-sync-alt" :class="{ 'fa-spin': loadingSummary }"></i> Làm mới
        </button>
      </div>
    </div>

    <!-- Hàng Thẻ Tổng hợp Kho xe (Mỗi cơ sở & Kho Thuê sở hữu) -->
    <div class="row mb-5" v-loading="loadingSummary">
      <div
        v-for="store in summaryList"
        :key="store.id"
        class="col-xl-4 col-lg-6 col-md-6 mb-4"
      >
        <div
          class="card card-custom wave wave-animate-slower cursor-pointer store-card"
          :class="{
            'border-primary active-card shadow': selectedStoreId === store.id,
            'bg-light-primary': store.kind === 'lease_to_own' && selectedStoreId === store.id,
          }"
          @click="selectStore(store.id)"
        >
          <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
              <div class="d-flex align-items-center">
                <div
                  class="symbol symbol-40 mr-3"
                  :class="store.kind === 'lease_to_own' ? 'symbol-light-warning' : 'symbol-light-primary'"
                >
                  <span class="symbol-label font-size-h5 font-weight-bold">
                    <i
                      :class="store.kind === 'lease_to_own' ? 'fas fa-hand-holding-usd text-warning' : 'fas fa-warehouse text-primary'"
                    ></i>
                  </span>
                </div>
                <div>
                  <h5 class="font-weight-bolder text-dark mb-0 font-size-lg">
                    {{ store.store_name }}
                  </h5>
                  <span class="badge badge-light-info small" v-if="store.kind === 'lease_to_own'">
                    Kho Thuê sở hữu
                  </span>
                  <span class="badge badge-light-secondary small" v-else>
                    Cơ sở vật lý
                  </span>
                </div>
              </div>

              <!-- Permission indicator -->
              <span
                v-if="!store.can_view_details"
                class="badge badge-light-warning"
                title="Bạn chỉ có quyền xem số lượng tổng hợp của cơ sở này"
              >
                <i class="fas fa-lock mr-1"></i> Tổng hợp
              </span>
              <span v-else class="badge badge-light-success">
                <i class="fas fa-check-circle mr-1"></i> Quản lý
              </span>
            </div>

            <!-- Số lượng tổng & Trạng thái tồn -->
            <div class="d-flex align-items-baseline justify-content-between mb-3">
              <div>
                <span class="text-muted font-weight-bold d-block small">Tổng xe có mặt:</span>
                <span class="font-size-h2 font-weight-bolder text-primary">
                  {{ store.total_present }}
                </span>
                <span class="text-muted small ml-2">/ {{ store.total_managed }} quản lý</span>
              </div>
              <div class="text-right">
                <span class="badge badge-success mr-1">{{ store.ready }} sẵn sàng</span>
                <span class="badge badge-primary mr-1">{{ store.using }} đang thuê</span>
                <span class="badge badge-warning mr-1" v-if="store.in_transit > 0">
                  {{ store.in_transit }} đang chuyển
                </span>
                <span class="badge badge-danger" v-if="store.repairing > 0">
                  {{ store.repairing }} sửa chữa
                </span>
              </div>
            </div>

            <!-- Phân bổ chủng loại xe -->
            <div class="border-top pt-2 d-flex justify-content-between text-muted small">
              <span>Ga: <strong>{{ store.types.xega }}</strong></span>
              <span>Số: <strong>{{ store.types.xeso }}</strong></span>
              <span>Côn: <strong>{{ store.types.xecon }}</strong></span>
              <span>SH: <strong>{{ store.types.xesh }}</strong></span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Khu vực Bảng danh sách xe của Kho được chọn -->
    <div class="card card-custom gutter-b">
      <div class="card-header border-0 py-5">
        <div class="card-title">
          <h3 class="card-label font-weight-bolder text-dark">
            Danh sách xe tại {{ currentStoreName }}
          </h3>
        </div>
        <div class="card-toolbar">
          <div class="btn-group btn-group-sm" role="group">
            <button
              type="button"
              class="btn"
              :class="filters.location_mode === 'all' ? 'btn-primary' : 'btn-light-primary'"
              @click="switchLocationMode('all')"
            >
              Tất cả liên quan
            </button>
            <button
              type="button"
              class="btn"
              :class="filters.location_mode === 'present' ? 'btn-primary' : 'btn-light-primary'"
              @click="switchLocationMode('present')"
            >
              Đang có mặt tại kho
            </button>
            <button
              type="button"
              class="btn"
              :class="filters.location_mode === 'managed' ? 'btn-primary' : 'btn-light-primary'"
              @click="switchLocationMode('managed')"
            >
              Kho quản trị tài sản
            </button>
          </div>
        </div>
      </div>

      <div class="card-body pt-0">
        <!-- Bộ lọc -->
        <div class="row mb-4">
          <div class="col-md-4 mb-2">
            <el-input
              v-model="filters.keyword"
              placeholder="Tìm theo biển số, tên xe, số khung..."
              clearable
              @clear="fetchVehicles"
              @keyup.enter.native="fetchVehicles"
            >
              <i slot="prefix" class="el-input__icon el-icon-search"></i>
            </el-input>
          </div>
          <div class="col-md-3 mb-2">
            <el-select
              v-model="filters.status"
              placeholder="Tất cả trạng thái"
              clearable
              class="w-100"
              @change="fetchVehicles"
            >
              <el-option label="Tất cả trạng thái" value="" />
              <el-option label="Sẵn sàng" value="ready" />
              <el-option label="Đang thuê" value="using" />
              <el-option label="Đang vận chuyển" value="in_transit" />
              <el-option label="Bảo dưỡng / Sửa chữa" value="repairing" />
              <el-option label="Hỏng / Sự cố" value="broken" />
            </el-select>
          </div>
          <div class="col-md-3 mb-2">
            <el-select
              v-model="filters.type"
              placeholder="Tất cả loại xe"
              clearable
              class="w-100"
              @change="fetchVehicles"
            >
              <el-option label="Tất cả loại xe" value="" />
              <el-option label="Xe ga" value="xega" />
              <el-option label="Xe số" value="xeso" />
              <el-option label="Xe côn" value="xecon" />
              <el-option label="Xe SH" value="xesh" />
            </el-select>
          </div>
          <div class="col-md-2 mb-2 text-right">
            <button class="btn btn-primary btn-block" @click="fetchVehicles">
              <i class="fas fa-filter mr-1"></i> Lọc xe
            </button>
          </div>
        </div>

        <!-- Trạng thái Cấm truy cập chi tiết (Nhân viên cơ sở khác) -->
        <div v-if="permissionDenied" class="alert alert-custom alert-light-warning py-5 text-center">
          <div class="alert-icon mb-2">
            <i class="flaticon-lock text-warning display-4"></i>
          </div>
          <div class="alert-text">
            <h5 class="font-weight-bold text-dark mb-2">Phân quyền chi nhánh bảo mật</h5>
            <p class="text-muted mb-0">
              Bạn không có quyền truy cập danh sách chi tiết xe của cơ sở này.
              Số lượng tổng hợp đã được hiển thị trên thẻ ở trên. Vui lòng liên hệ Quản trị viên nếu cần điều phối xe.
            </p>
          </div>
        </div>

        <!-- Bảng danh sách xe -->
        <div v-else v-loading="loadingVehicles">
          <div class="table-responsive">
            <table class="table table-head-custom table-vertical-center table-hover">
              <thead>
                <tr>
                  <th>Biển số & Tên xe</th>
                  <th>Loại xe</th>
                  <th>Kho quản lý gốc</th>
                  <th>Nơi đang giữ xe</th>
                  <th>Trạng thái</th>
                  <th>Hợp đồng đang thuê</th>
                  <th class="text-right">Hành động</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="vehicle in vehicleList" :key="vehicle.id">
                  <td>
                    <span class="font-weight-bolder text-primary d-block font-size-lg">
                      {{ vehicle.license }}
                    </span>
                    <span class="text-muted small">{{ vehicle.name }} ({{ vehicle.color || 'N/A' }})</span>
                  </td>
                  <td>
                    <span class="badge badge-light-info font-weight-bold">
                      {{ formatType(vehicle.type) }}
                    </span>
                  </td>
                  <td>
                    <span class="text-dark-75 font-weight-bold">{{ vehicle.managed_store_name || 'N/A' }}</span>
                  </td>
                  <td>
                    <span class="font-weight-bolder" :class="vehicle.is_in_transit ? 'text-warning' : 'text-success'">
                      <i :class="vehicle.is_in_transit ? 'fas fa-shipping-fast mr-1' : 'fas fa-map-marker-alt mr-1'"></i>
                      {{ vehicle.is_in_transit ? 'Đang trên đường chuyển' : vehicle.current_store_name }}
                    </span>
                  </td>
                  <td>
                    <span :class="getStatusBadgeClass(vehicle.status)" class="badge font-weight-bold">
                      {{ formatStatus(vehicle.status) }}
                    </span>
                  </td>
                  <td>
                    <div v-if="vehicle.active_order">
                      <span class="font-weight-bold text-primary d-block">
                        HĐ: {{ vehicle.active_order.contract_number || `#${vehicle.active_order.id}` }}
                      </span>
                      <span class="text-muted small">
                        {{ vehicle.active_order.customer_name }} ({{ vehicle.active_order.customer_phone }})
                      </span>
                    </div>
                    <span v-else class="text-muted font-italic small">Không có hợp đồng</span>
                  </td>
                  <td class="text-right">
                    <!-- Nút xem lịch sử di chuyển -->
                    <button
                      class="btn btn-sm btn-icon btn-light-info mr-1"
                      title="Xem sổ cái lịch sử di chuyển xe"
                      @click="viewMovementHistory(vehicle.id)"
                    >
                      <i class="fas fa-history"></i>
                    </button>

                    <!-- Nút chuyển kho nhanh nếu xe ready -->
                    <button
                      v-if="vehicle.status === 'ready'"
                      class="btn btn-sm btn-icon btn-light-primary"
                      title="Điều chuyển xe này sang kho khác"
                      @click="quickTransferVehicle(vehicle)"
                    >
                      <i class="fas fa-truck-moving"></i>
                    </button>
                  </td>
                </tr>

                <tr v-if="vehicleList.length === 0 && !loadingVehicles">
                  <td colspan="7" class="text-center py-5 text-muted">
                    Không tìm thấy xe nào phù hợp với bộ lọc hiện tại.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Phân trang -->
          <div class="d-flex justify-content-between align-items-center mt-3" v-if="pagination.total > 0">
            <span class="text-muted small">
              Hiển thị {{ vehicleList.length }} trên tổng số {{ pagination.total }} xe
            </span>
            <b-pagination
              v-model="pagination.page"
              :total-rows="pagination.total"
              :per-page="pagination.per_page"
              @change="onPageChange"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Modals cho 3 luồng điều chuyển và sổ cái -->
    <ModalStoreTransfer
      ref="modalStoreTransfer"
      :stores="summaryList"
      :default-store-id="selectedStoreId"
      @success="refreshData"
    />

    <ModalReturnDifferentStore
      ref="modalReturnDifferentStore"
      :stores="summaryList"
      :default-store-id="selectedStoreId"
      @success="refreshData"
    />

    <ModalVehicleExchange
      ref="modalVehicleExchange"
      :default-store-id="selectedStoreId"
      @success="refreshData"
    />

    <ModalVehicleMovementHistory
      ref="modalMovementHistory"
    />
  </div>
</template>

<script>
import { mapGetters } from "vuex";
import {
  WAREHOUSE_GET_SUMMARY,
  WAREHOUSE_GET_VEHICLES,
} from "@/core/services/store/warehouse.module";
import ModalStoreTransfer from "./components/ModalStoreTransfer.vue";
import ModalReturnDifferentStore from "./components/ModalReturnDifferentStore.vue";
import ModalVehicleExchange from "./components/ModalVehicleExchange.vue";
import ModalVehicleMovementHistory from "./components/ModalVehicleMovementHistory.vue";

export default {
  name: "WarehouseIndex",
  components: {
    ModalStoreTransfer,
    ModalReturnDifferentStore,
    ModalVehicleExchange,
    ModalVehicleMovementHistory,
  },
  data() {
    return {
      loadingSummary: false,
      loadingVehicles: false,
      summaryList: [],
      selectedStoreId: null,
      permissionDenied: false,
      vehicleList: [],
      pagination: {
        page: 1,
        per_page: 15,
        total: 0,
      },
      filters: {
        keyword: "",
        status: "",
        type: "",
        location_mode: "all",
      },
    };
  },
  computed: {
    ...mapGetters(["currentUser"]),
    currentStoreName() {
      const found = this.summaryList.find((s) => s.id === this.selectedStoreId);
      return found ? found.store_name : "Kho xe";
    },
  },
  mounted() {
    this.initData();
  },
  methods: {
    initData() {
      const qStoreId = this.$route.query.store_id;
      this.fetchSummary().then(() => {
        if (qStoreId && this.summaryList.some((s) => s.id === Number(qStoreId))) {
          this.selectStore(Number(qStoreId));
        } else if (this.summaryList.length > 0) {
          // Default select user store or first store
          const userStore = this.summaryList.find((s) => s.id === this.currentUser?.store_id);
          this.selectStore(userStore ? userStore.id : this.summaryList[0].id);
        }
      });
    },
    fetchSummary() {
      this.loadingSummary = true;
      return this.$store
        .dispatch(WAREHOUSE_GET_SUMMARY)
        .then((res) => {
          this.summaryList = res?.data || [];
        })
        .catch(() => {
          this.summaryList = [];
        })
        .finally(() => {
          this.loadingSummary = false;
        });
    },
    selectStore(storeId) {
      this.selectedStoreId = storeId;
      this.pagination.page = 1;
      this.fetchVehicles();
    },
    fetchVehicles() {
      if (!this.selectedStoreId) return;
      this.loadingVehicles = true;
      this.permissionDenied = false;

      this.$store
        .dispatch(WAREHOUSE_GET_VEHICLES, {
          storeId: this.selectedStoreId,
          params: {
            page: this.pagination.page,
            limit: this.pagination.per_page,
            keyword: this.filters.keyword,
            status: this.filters.status,
            type: this.filters.type,
            location_mode: this.filters.location_mode,
          },
        })
        .then((res) => {
          const vData = res?.data?.vehicles;
          if (vData) {
            this.vehicleList = vData.data || vData;
            this.pagination.total = vData.total || this.vehicleList.length;
          } else {
            this.vehicleList = [];
            this.pagination.total = 0;
          }
        })
        .catch((err) => {
          if (err?.status === 403) {
            this.permissionDenied = true;
          }
          this.vehicleList = [];
          this.pagination.total = 0;
        })
        .finally(() => {
          this.loadingVehicles = false;
        });
    },
    switchLocationMode(mode) {
      this.filters.location_mode = mode;
      this.pagination.page = 1;
      this.fetchVehicles();
    },
    onPageChange(page) {
      this.pagination.page = page;
      this.fetchVehicles();
    },
    refreshData() {
      this.fetchSummary().then(() => {
        this.fetchVehicles();
      });
    },
    openStoreTransferModal() {
      this.$refs.modalStoreTransfer.open(null, this.selectedStoreId);
    },
    openReturnDifferentStoreModal() {
      this.$refs.modalReturnDifferentStore.open(null, this.selectedStoreId);
    },
    openVehicleExchangeModal() {
      this.$refs.modalVehicleExchange.open(null, null, this.selectedStoreId);
    },
    quickTransferVehicle(vehicle) {
      this.$refs.modalStoreTransfer.open(vehicle, this.selectedStoreId);
    },
    viewMovementHistory(vehicleId) {
      this.$refs.modalMovementHistory.open(vehicleId);
    },
    formatType(type) {
      const map = {
        xega: "Xe ga",
        xeso: "Xe số",
        xecon: "Xe côn",
        xesh: "Xe SH",
      };
      return map[type] || type || "N/A";
    },
    formatStatus(status) {
      const map = {
        ready: "Sẵn sàng",
        using: "Đang thuê",
        in_transit: "Đang chuyển kho",
        repairing: "Sửa chữa",
        broken: "Hỏng / Sự cố",
        sold: "Đã bán",
      };
      return map[status] || status;
    },
    getStatusBadgeClass(status) {
      const map = {
        ready: "badge-light-success text-success",
        using: "badge-light-primary text-primary",
        in_transit: "badge-light-warning text-warning",
        repairing: "badge-light-danger text-danger",
        broken: "badge-danger",
        sold: "badge-secondary",
      };
      return map[status] || "badge-light-secondary";
    },
  },
};
</script>

<style scoped>
.store-card {
  transition: all 0.2s ease-in-out;
  border: 2px solid transparent;
}
.store-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}
.active-card {
  border-color: #3699ff !important;
}
</style>
