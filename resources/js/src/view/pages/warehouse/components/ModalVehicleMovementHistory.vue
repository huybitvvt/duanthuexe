<template>
  <b-modal
    id="modal-vehicle-movement-history"
    v-model="visible"
    title="Sổ cái lịch sử di chuyển & vị trí xe"
    size="lg"
    no-close-on-backdrop
    hide-footer
  >
    <div v-loading="loading">
      <div v-if="vehicleInfo" class="card card-custom bg-light mb-4 p-3">
        <div class="row">
          <div class="col-md-6">
            <h5 class="font-weight-bold text-dark mb-1">
              {{ vehicleInfo.license }} - {{ vehicleInfo.name }}
            </h5>
            <div class="text-muted small">
              Vị trí thực tế hiện tại: <strong class="text-primary">{{ vehicleInfo.current_store_name }}</strong>
            </div>
          </div>
          <div class="col-md-6 text-right">
            <div>
              Trạng thái:
              <span class="badge badge-primary px-2 py-1">{{ vehicleInfo.status }}</span>
            </div>
            <div class="text-muted small mt-1">
              Odometer hiện tại: <strong>{{ vehicleInfo.odometer || 0 }} km</strong>
            </div>
          </div>
        </div>
      </div>

      <!-- Timeline events -->
      <div v-if="events && events.length > 0" class="timeline timeline-3">
        <div class="timeline-items">
          <div
            v-for="ev in events"
            :key="ev.id"
            class="timeline-item mb-3 p-3 border rounded bg-white shadow-sm"
          >
            <div class="d-flex justify-content-between align-items-center mb-1">
              <div>
                <span :class="getEventBadgeClass(ev.event_type)" class="badge mr-2">
                  {{ getEventLabel(ev.event_type) }}
                </span>
                <span class="font-weight-bold text-dark">
                  {{ ev.from_store_name }} &rarr; {{ ev.to_store_name }}
                </span>
              </div>
              <span class="text-muted small">
                {{ ev.created_at }}
              </span>
            </div>

            <div class="text-secondary small mt-1">
              {{ ev.notes }}
            </div>

            <div v-if="ev.contract" class="exchange-contract-link mt-2 p-2 rounded">
              <div class="small text-muted">
                Khách: <strong class="text-dark">{{ ev.contract.customer_name || 'Chưa cập nhật' }}</strong>
                <span v-if="ev.contract.store_name"> · Hợp đồng tại {{ ev.contract.store_name }}</span>
              </div>
              <button
                type="button"
                class="btn btn-xs btn-light-primary font-weight-bold mt-1"
                @click="openOrder(ev.contract.id)"
              >
                Mở HĐ {{ ev.contract.contract_number || `#${ev.contract.id}` }}
                <span v-if="ev.contract.amendment_code"> · {{ ev.contract.amendment_code }}</span>
              </button>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-2 text-muted small border-top pt-1">
              <span>Người ghi nhận: <strong>{{ ev.created_by_name }}</strong></span>
              <span v-if="ev.odometer !== null">ODO: <strong>{{ ev.odometer }} km</strong></span>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="!loading" class="text-center py-5 text-muted">
        <div class="font-weight-bold mb-1">Chưa có lịch sử di chuyển</div>
        Chưa có lịch sử điều chuyển hoặc ghi nhận vị trí cho xe này.
      </div>
    </div>
  </b-modal>
</template>

<script>
import { WAREHOUSE_GET_MOVEMENT_HISTORY } from "@/core/services/store/warehouse.module";

export default {
  name: "ModalVehicleMovementHistory",
  data() {
    return {
      visible: false,
      loading: false,
      vehicleInfo: null,
      events: [],
    };
  },
  methods: {
    open(vehicleId) {
      this.visible = true;
      this.loading = true;
      this.vehicleInfo = null;
      this.events = [];

      this.$store
        .dispatch(WAREHOUSE_GET_MOVEMENT_HISTORY, vehicleId)
        .then((res) => {
          this.vehicleInfo = res?.data?.vehicle || null;
          this.events = res?.data?.events || [];
        })
        .catch(() => {
          this.vehicleInfo = null;
          this.events = [];
        })
        .finally(() => {
          this.loading = false;
        });
    },
    getEventBadgeClass(type) {
      const map = {
        transfer_dispatch: "badge-warning",
        transfer_receive: "badge-success",
        transfer_cancel: "badge-danger",
        order_rent_out: "badge-primary",
        order_return_same_store: "badge-info",
        order_return_different_store: "badge-success",
        vehicle_exchange_out: "badge-warning",
        vehicle_exchange_in: "badge-primary",
      };
      return map[type] || "badge-secondary";
    },
    getEventLabel(type) {
      const map = {
        transfer_dispatch: "Xuất kho điều chuyển",
        transfer_receive: "Nhập kho điều chuyển",
        transfer_cancel: "Hủy điều chuyển",
        order_rent_out: "Bàn giao khách thuê",
        order_return_same_store: "Khách trả tại cơ sở",
        order_return_different_store: "Khách trả khác cơ sở",
        vehicle_exchange_out: "Thu hồi xe đổi sự cố",
        vehicle_exchange_in: "Bàn giao xe thay thế",
      };
      return map[type] || type;
    },
    openOrder(orderId) {
      this.visible = false;
      this.$router.push({
        name: "car-rental",
        query: { open_order: orderId },
      });
    },
  },
};
</script>

<style scoped>
.timeline-item {
  border-left: 4px solid #3699ff !important;
}

.exchange-contract-link {
  background: #f2f7ff;
  border: 1px solid #d9e8ff;
}
</style>
