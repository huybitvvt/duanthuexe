<template>
  <b-modal
    id="modal-return-different-store"
    v-model="visible"
    title="Nhận xe khách trả tại cơ sở khác"
    size="lg"
    no-close-on-backdrop
    @hidden="resetForm"
  >
    <div v-loading="loading">
      <p class="alert alert-info">Hoàn tất trả xe và đối soát tiền trong đơn thuê trước. Màn hình này chỉ ghi nhận nhập kho khác cơ sở cho đơn đã hoàn tất.</p>
      <div class="alert alert-custom alert-light-primary mb-4 p-3" role="alert">
        <div class="alert-text small">
          Thao tác này ghi nhận vị trí thực tế của xe về cơ sở hiện tại (nơi tiếp nhận xe). Cơ sở ghi nhận doanh thu gốc của hợp đồng vẫn được bảo toàn nguyên vẹn.
        </div>
      </div>

      <div class="row">
        <!-- ID Đơn hàng / Hợp đồng -->
        <div class="col-md-6 form-group">
          <label class="font-weight-bold">ID Đơn hàng / Hợp đồng đã hoàn tất <span class="text-danger">*</span></label>
          <el-input
            v-model="form.order_id"
            placeholder="Nhập ID đơn thuê (VD: 125)"
            type="number"
            @keyup.enter.native="fetchOrderInfo"
          />
          <button type="button" class="btn btn-sm btn-outline-primary mt-2" :disabled="lookupLoading || !form.order_id" @click="fetchOrderInfo">
            {{ lookupLoading ? 'Đang tra cứu...' : 'Tra cứu đơn và xe' }}
          </button>
          <small class="form-text text-muted" v-if="orderInfo">
            Khách: <strong>{{ orderInfo.customer_name }}</strong> | HĐ: <strong>{{ orderInfo.contract_number }}</strong> | Trạng thái: <strong>{{ orderInfo.status_label }}</strong>
          </small>
        </div>

        <!-- ID Xe cụ thể (khi đơn nhiều xe) -->
        <div class="col-md-6 form-group">
          <label class="font-weight-bold">Xe cần nhập kho <span class="text-danger">*</span></label>
          <el-select
            v-model="form.vehicle_id"
            placeholder="Tra cứu đơn rồi chọn xe theo biển số"
            class="w-100"
            :disabled="vehicleOptions.length === 0"
          >
            <el-option
              v-for="vehicle in vehicleOptions"
              :key="vehicle.id"
              :label="vehicleLabel(vehicle)"
              :value="vehicle.id"
            />
          </el-select>
          <small class="form-text text-muted">Danh sách chỉ gồm xe thuộc đơn vừa tra cứu.</small>
        </div>

        <!-- Cơ sở nhận xe -->
        <div class="col-md-6 form-group">
          <label class="font-weight-bold">Cơ sở tiếp nhận xe trả <span class="text-danger">*</span></label>
          <el-select
            v-model="form.return_store_id"
            placeholder="Chọn cơ sở tiếp nhận"
            class="w-100"
          >
            <el-option
              v-for="s in stores"
              :key="s.id"
              :label="s.store_name"
              :value="s.id"
            />
          </el-select>
        </div>

        <!-- Số km (Odometer) -->
        <div class="col-md-6 form-group">
          <label class="font-weight-bold">Chỉ số Odometer khi trả (km)</label>
          <el-input
            v-model="form.odometer"
            placeholder="VD: 15420"
            type="number"
          />
        </div>

        <!-- Giờ trả thực tế -->
        <div class="col-md-6 form-group">
          <label class="font-weight-bold">Thời gian trả thực tế</label>
          <el-date-picker
            v-model="form.returned_at"
            type="datetime"
            placeholder="Chọn ngày giờ trả xe"
            format="dd/MM/yyyy HH:mm"
            value-format="yyyy-MM-dd HH:mm:ss"
            class="w-100"
          />
        </div>

        <!-- Tình trạng xe khi nhận -->
        <div class="col-md-12 form-group">
          <label class="font-weight-bold">Tình trạng xe & Biên bản nhận xe</label>
          <el-input
            type="textarea"
            :rows="3"
            v-model="form.condition_notes"
            placeholder="Ghi nhận tình trạng vỏ xe, xăng, đồ dùng phụ kiện bàn giao lại tại chi nhánh tiếp nhận..."
          />
        </div>
      </div>
    </div>

    <template #modal-footer="{ cancel }">
      <b-button variant="secondary" @click="cancel">Đóng</b-button>
      <b-button variant="success" :disabled="loading" @click="handleSubmit">
        Xác nhận nhận xe về cơ sở này
      </b-button>
    </template>
  </b-modal>
</template>

<script>
import { mapGetters } from "vuex";
import { WAREHOUSE_RETURN_DIFFERENT_STORE } from "@/core/services/store/warehouse.module";
import ApiService from "@/core/services/api.service";
import Swal from "sweetalert2";

export default {
  name: "ModalReturnDifferentStore",
  props: {
    stores: {
      type: Array,
      default: () => [],
    },
    defaultStoreId: {
      type: [Number, String],
      default: null,
    },
  },
  data() {
    return {
      visible: false,
      loading: false,
      lookupLoading: false,
      orderInfo: null,
      vehicleOptions: [],
      form: {
        order_id: null,
        vehicle_id: null,
        return_store_id: null,
        odometer: null,
        returned_at: null,
        condition_notes: "Khách trả xe tại cơ sở khác theo thỏa thuận",
      },
    };
  },
  computed: {
    ...mapGetters(["currentUser"]),
  },
  methods: {
    open(initialOrder = null, storeId = null) {
      this.visible = true;
      const targetStore = storeId || this.defaultStoreId || this.currentUser?.store_id;
      this.form.return_store_id = targetStore ? Number(targetStore) : null;
      if (initialOrder) {
        this.form.order_id = initialOrder.id;
        this.applyOrderInfo(initialOrder);
        this.fetchOrderInfo();
      }
    },
    async fetchOrderInfo() {
      if (!this.form.order_id) return;
      this.lookupLoading = true;
      this.orderInfo = null;
      this.vehicleOptions = [];
      this.form.vehicle_id = null;
      try {
        const res = await ApiService.get("/api/auth/order/car-rental", Number(this.form.order_id));
        const order = res.data?.data || res.data;
        this.applyOrderInfo(order);
      } catch (err) {
        const message = err.response?.data?.message || "Không tìm thấy đơn thuê hoặc bạn không có quyền xem đơn này.";
        this.$message.error(message);
      } finally {
        this.lookupLoading = false;
      }
    },
    applyOrderInfo(order) {
      if (!order) return;
      const directVehicles = Array.isArray(order.vehicles) ? order.vehicles : [];
      const itemVehicles = (order.order_items || [])
        .map((item) => item.vehicle)
        .filter(Boolean);
      const unique = new Map();
      [...directVehicles, ...itemVehicles].forEach((vehicle) => {
        if (vehicle && vehicle.id) unique.set(Number(vehicle.id), vehicle);
      });
      this.vehicleOptions = Array.from(unique.values());
      if (this.vehicleOptions.length === 1) {
        this.form.vehicle_id = Number(this.vehicleOptions[0].id);
      }
      this.orderInfo = {
        customer_name: order.customer_name || order.customer?.name || order.customer?.full_name || "Chưa cập nhật",
        contract_number: order.contract_number || `#${order.id}`,
        status_label: this.orderStatusLabel(order.order_status),
      };
    },
    vehicleLabel(vehicle) {
      const license = vehicle.license || vehicle.license_plate || "Chưa có biển số";
      const name = vehicle.name || [vehicle.brand, vehicle.model].filter(Boolean).join(" ") || "Xe";
      return `${license} - ${name}`;
    },
    orderStatusLabel(status) {
      const labels = {
        completed: "Đã hoàn tất",
        wait_payment: "Chờ đối soát thanh toán",
        renting: "Đang thuê",
      };
      return labels[status] || status || "Không xác định";
    },
    handleSubmit() {
      if (!this.form.order_id) {
        Swal.fire("Lỗi", "Vui lòng nhập ID đơn hàng cần trả xe.", "warning");
        return;
      }
      if (!this.form.return_store_id) {
        Swal.fire("Lỗi", "Vui lòng chọn cơ sở tiếp nhận xe.", "warning");
        return;
      }
      if (!this.form.vehicle_id) {
        Swal.fire("Lỗi", "Vui lòng tra cứu đơn và chọn đúng xe cần nhập kho.", "warning");
        return;
      }

      this.loading = true;
      const payload = {
        order_id: Number(this.form.order_id),
        return_store_id: Number(this.form.return_store_id),
        odometer: this.form.odometer ? Number(this.form.odometer) : null,
        returned_at: this.form.returned_at,
        condition_notes: this.form.condition_notes,
      };
      payload.vehicle_id = Number(this.form.vehicle_id);

      this.$store
        .dispatch(WAREHOUSE_RETURN_DIFFERENT_STORE, payload)
        .then((res) => {
          Swal.fire("Thành công", res?.message || "Đã tiếp nhận xe trả tại cơ sở thành công. Vị trí xe đã được cập nhật.", "success");
          this.visible = false;
          this.$emit("success");
        })
        .catch((err) => {
          const msg = err?.data?.message || err?.message || "Đã xảy ra lỗi khi ghi nhận nhận xe trả.";
          Swal.fire("Lỗi", msg, "error");
        })
        .finally(() => {
          this.loading = false;
        });
    },
    resetForm() {
      this.form = {
        order_id: null,
        vehicle_id: null,
        return_store_id: null,
        odometer: null,
        returned_at: null,
        condition_notes: "Khách trả xe tại cơ sở khác theo thỏa thuận",
      };
      this.orderInfo = null;
      this.vehicleOptions = [];
    },
  },
};
</script>
