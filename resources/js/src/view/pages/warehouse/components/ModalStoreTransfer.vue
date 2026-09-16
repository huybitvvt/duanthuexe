<template>
  <b-modal
    id="modal-store-transfer"
    v-model="visible"
    title="Điều chuyển xe giữa 2 kho (Kho A -> Kho B)"
    size="lg"
    no-close-on-backdrop
    @hidden="resetForm"
  >
    <div v-loading="loading">
      <div class="row">
        <!-- Kho xuất phát -->
        <div class="col-md-6 form-group">
          <label class="font-weight-bold">Kho xuất phát (Kho A) <span class="text-danger">*</span></label>
          <el-select
            v-model="form.from_store_id"
            placeholder="Chọn kho xuất"
            class="w-100"
            :disabled="!isAdmin"
            @change="onFromStoreChange"
          >
            <el-option
              v-for="s in stores"
              :key="s.id"
              :label="s.store_name"
              :value="s.id"
            />
          </el-select>
        </div>

        <!-- Kho nhận -->
        <div class="col-md-6 form-group">
          <label class="font-weight-bold">Kho nhận (Kho B) <span class="text-danger">*</span></label>
          <el-select
            v-model="form.to_store_id"
            placeholder="Chọn kho nhận"
            class="w-100"
          >
            <el-option
              v-for="s in filteredToStores"
              :key="s.id"
              :label="s.store_name"
              :value="s.id"
            />
          </el-select>
        </div>

        <!-- Chọn xe điều chuyển -->
        <div class="col-md-12 form-group">
          <label class="font-weight-bold">Danh sách xe điều chuyển (chỉ xe Sẵn sàng) <span class="text-danger">*</span></label>
          <el-select
            v-model="form.vehicle_ids"
            multiple
            filterable
            placeholder="Chọn 1 hoặc nhiều xe"
            class="w-100"
            :loading="loadingVehicles"
          >
            <el-option
              v-for="v in availableVehicles"
              :key="v.id"
              :label="`${v.license} - ${v.name} (${v.type || 'N/A'})`"
              :value="v.id"
            />
          </el-select>
          <small class="form-text text-muted" v-if="availableVehicles.length === 0">
            Không có xe sẵn sàng nào tại kho xuất phát đã chọn.
          </small>
        </div>

        <!-- Lý do -->
        <div class="col-md-12 form-group">
          <label class="font-weight-bold">Lý do điều chuyển</label>
          <el-input
            v-model="form.reason"
            placeholder="VD: Cân đối số lượng xe, điều phối theo nhu cầu thuê..."
          />
        </div>

        <!-- Ghi chú bổ sung -->
        <div class="col-md-12 form-group">
          <label class="font-weight-bold">Ghi chú bổ sung</label>
          <el-input
            type="textarea"
            :rows="2"
            v-model="form.notes"
            placeholder="Ghi chú về phụ kiện kèm theo, biên bản bàn giao, người vận chuyển..."
          />
        </div>
      </div>
    </div>

    <template #modal-footer="{ cancel }">
      <b-button variant="secondary" @click="cancel">Đóng</b-button>
      <b-button variant="primary" :disabled="loading" @click="handleSubmit">
        <i class="fas fa-truck-moving mr-1"></i> Xuất kho điều chuyển
      </b-button>
    </template>
  </b-modal>
</template>

<script>
import { mapGetters } from "vuex";
import {
  WAREHOUSE_DISPATCH_TRANSFER,
  WAREHOUSE_GET_VEHICLES,
} from "@/core/services/store/warehouse.module";
import Swal from "sweetalert2";

export default {
  name: "ModalStoreTransfer",
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
      loadingVehicles: false,
      availableVehicles: [],
      form: {
        from_store_id: null,
        to_store_id: null,
        vehicle_ids: [],
        reason: "Điều chuyển nội bộ",
        notes: "",
      },
    };
  },
  computed: {
    ...mapGetters(["currentUser"]),
    isAdmin() {
      return this.currentUser?.role_id === 1 || this.currentUser?.role_rel?.slug === "quan-tri-vien";
    },
    filteredToStores() {
      return this.stores.filter((s) => s.id !== this.form.from_store_id);
    },
  },
  methods: {
    open(initialVehicle = null, fromStoreId = null) {
      this.visible = true;
      const targetFrom = fromStoreId || this.defaultStoreId || (this.isAdmin ? (this.stores[0]?.id || null) : this.currentUser?.store_id);
      this.form.from_store_id = targetFrom ? Number(targetFrom) : null;
      this.form.to_store_id = null;
      this.form.vehicle_ids = initialVehicle ? [initialVehicle.id] : [];
      this.fetchReadyVehicles();
    },
    onFromStoreChange() {
      this.form.vehicle_ids = [];
      this.fetchReadyVehicles();
    },
    fetchReadyVehicles() {
      if (!this.form.from_store_id) return;
      this.loadingVehicles = true;
      this.$store
        .dispatch(WAREHOUSE_GET_VEHICLES, {
          storeId: this.form.from_store_id,
          params: { status: "ready", location_mode: "present", limit: 100 },
        })
        .then((res) => {
          const list = res?.data?.vehicles?.data || res?.data?.vehicles || [];
          this.availableVehicles = list;
        })
        .catch(() => {
          this.availableVehicles = [];
        })
        .finally(() => {
          this.loadingVehicles = false;
        });
    },
    handleSubmit() {
      if (!this.form.from_store_id) {
        Swal.fire("Lỗi", "Vui lòng chọn kho xuất phát.", "warning");
        return;
      }
      if (!this.form.to_store_id) {
        Swal.fire("Lỗi", "Vui lòng chọn kho nhận.", "warning");
        return;
      }
      if (!this.form.vehicle_ids || this.form.vehicle_ids.length === 0) {
        Swal.fire("Lỗi", "Vui lòng chọn ít nhất 1 xe cần chuyển.", "warning");
        return;
      }

      this.loading = true;
      this.$store
        .dispatch(WAREHOUSE_DISPATCH_TRANSFER, {
          ...this.form,
          idempotency_key: `dispatch_${this.form.from_store_id}_${this.form.to_store_id}_${Date.now()}`,
        })
        .then((res) => {
          Swal.fire("Thành công", res?.message || "Đã xuất kho điều chuyển thành công. Xe đã chuyển sang trạng thái Đang vận chuyển.", "success");
          this.visible = false;
          this.$emit("success");
        })
        .catch((err) => {
          const msg = err?.data?.message || err?.message || "Đã xảy ra lỗi khi tạo phiếu chuyển.";
          Swal.fire("Lỗi", msg, "error");
        })
        .finally(() => {
          this.loading = false;
        });
    },
    resetForm() {
      this.form = {
        from_store_id: null,
        to_store_id: null,
        vehicle_ids: [],
        reason: "Điều chuyển nội bộ",
        notes: "",
      };
      this.availableVehicles = [];
    },
  },
};
</script>
