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
      <p class="alert alert-info">Hoàn tất trả xe và đối soát tiền trong đơn thuê trước. Màn hình này ghi nhận nhập kho khác cơ sở cho đơn một xe đã hoàn tất.</p>
      <div class="alert alert-custom alert-light-primary mb-4 p-3" role="alert">
        <div class="alert-icon"><i class="flaticon-information"></i></div>
        <div class="alert-text small">
          Thao tác này ghi nhận vị trí thực tế của xe về cơ sở hiện tại (nơi tiếp nhận xe). Cơ sở ghi nhận doanh thu gốc của hợp đồng vẫn được bảo toàn nguyên vẹn.
        </div>
      </div>

      <div class="row">
        <!-- Đơn hàng / Hợp đồng -->
        <div class="col-md-6 form-group">
          <label class="font-weight-bold">ID Đơn hàng / Hợp đồng đang thuê <span class="text-danger">*</span></label>
          <el-input
            v-model="form.order_id"
            placeholder="Nhập ID đơn thuê (VD: 125)"
            type="number"
          />
          <small class="form-text text-muted" v-if="orderInfo">
            Khách: <strong>{{ orderInfo.customer_name }}</strong> | HĐ: <strong>{{ orderInfo.contract_number }}</strong>
          </small>
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
        <i class="fas fa-check-circle mr-1"></i> Xác nhận nhận xe về cơ sở này
      </b-button>
    </template>
  </b-modal>
</template>

<script>
import { mapGetters } from "vuex";
import { WAREHOUSE_RETURN_DIFFERENT_STORE } from "@/core/services/store/warehouse.module";
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
      orderInfo: null,
      form: {
        order_id: null,
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
        this.orderInfo = {
          customer_name: initialOrder.customer_name,
          contract_number: initialOrder.contract_number,
        };
      }
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

      this.loading = true;
      this.$store
        .dispatch(WAREHOUSE_RETURN_DIFFERENT_STORE, {
          order_id: Number(this.form.order_id),
          return_store_id: Number(this.form.return_store_id),
          odometer: this.form.odometer ? Number(this.form.odometer) : null,
          returned_at: this.form.returned_at,
          condition_notes: this.form.condition_notes,
        })
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
        return_store_id: null,
        odometer: null,
        returned_at: null,
        condition_notes: "Khách trả xe tại cơ sở khác theo thỏa thuận",
      };
      this.orderInfo = null;
    },
  },
};
</script>
