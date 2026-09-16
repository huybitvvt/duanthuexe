<template>
  <b-modal
    id="modal-lease-create"
    v-model="visible"
    title="Tạo hợp đồng Thuê sở hữu mới"
    size="lg"
    no-close-on-backdrop
    @hidden="resetForm"
  >
    <div v-loading="loading">
      <div class="row">
        <!-- Khách hàng -->
        <div class="col-md-6 form-group">
          <label class="font-weight-bold">Tên khách hàng <span class="text-danger">*</span></label>
          <el-input v-model="form.customer.name" placeholder="Nguyễn Văn A" />
        </div>
        <div class="col-md-6 form-group">
          <label class="font-weight-bold">Số điện thoại <span class="text-danger">*</span></label>
          <el-input v-model="form.customer.phone" placeholder="09xxxxxxxx" />
        </div>
        <div class="col-md-6 form-group">
          <label class="font-weight-bold">Số CCCD / CMND <span class="text-danger">*</span></label>
          <el-input v-model="form.customer.id_card" placeholder="12 chữ số CCCD" />
        </div>
        <div class="col-md-6 form-group">
          <label class="font-weight-bold">Địa chỉ thường trú</label>
          <el-input v-model="form.customer.address" placeholder="Địa chỉ nơi cư trú" />
        </div>

        <div class="col-12"><hr class="my-3" /></div>

        <!-- Xe & Ngày bắt đầu -->
        <div class="col-md-6 form-group">
          <label class="font-weight-bold">Chọn xe bàn giao (kho Thuê sở hữu)</label>
          <el-select
            v-model="form.vehicle_id"
            filterable
            placeholder="Tìm theo biển số hoặc tên xe"
            class="w-100"
          >
            <el-option
              v-for="v in vehicles"
              :key="v.id"
              :label="`${v.license} - ${v.name}`"
              :value="v.id"
            />
          </el-select>
        </div>
        <div class="col-md-6 form-group">
          <label class="font-weight-bold">Ngày bắt đầu hiệu lực <span class="text-danger">*</span></label>
          <el-date-picker
            v-model="form.start_date"
            type="date"
            placeholder="Chọn ngày"
            format="dd/MM/yyyy"
            value-format="yyyy-MM-dd"
            class="w-100"
          />
        </div>

        <!-- Điều khoản tài chính -->
        <div class="col-md-4 form-group">
          <label class="font-weight-bold">Tổng giá trị hợp đồng (VNĐ) <span class="text-danger">*</span></label>
          <el-input
            v-model="form.total_amount"
            type="number"
            placeholder="VD: 24000000"
            @input="recalculatePeriodAmount"
          />
        </div>
        <div class="col-md-4 form-group">
          <label class="font-weight-bold">Tiền trả trước / Đặt cọc (VNĐ)</label>
          <el-input
            v-model="form.deposit_amount"
            type="number"
            placeholder="0"
            @input="recalculatePeriodAmount"
          />
          <small class="form-text text-muted">Khoản phải thu ban đầu (kỳ 0). Sau khi lưu, dùng Thu tiền để ghi nhận số thực nhận.</small>
        </div>
        <div class="col-md-4 form-group">
          <label class="font-weight-bold">Số kỳ trả góp (tháng) <span class="text-danger">*</span></label>
          <el-select
            v-model="form.installment_count"
            class="w-100"
            @change="recalculatePeriodAmount"
          >
            <el-option label="3 tháng (3 kỳ)" :value="3" />
            <el-option label="6 tháng (6 kỳ)" :value="6" />
            <el-option label="9 tháng (9 kỳ)" :value="9" />
            <el-option label="12 tháng (12 kỳ)" :value="12" />
            <el-option label="18 tháng (18 kỳ)" :value="18" />
            <el-option label="24 tháng (24 kỳ)" :value="24" />
          </el-select>
        </div>

        <!-- Số tiền mỗi kỳ -->
        <div class="col-md-6 form-group">
          <label class="font-weight-bold">Số tiền mỗi kỳ (VNĐ/tháng) <span class="text-danger">*</span></label>
          <el-input
            v-model="form.period_amount"
            type="number"
            placeholder="Tự động tính theo số kỳ"
          />
          <small class="form-text text-muted">
            (Tổng giá trị − Tiền cọc) ÷ Số kỳ
          </small>
        </div>

        <!-- Ghi chú hợp đồng -->
        <div class="col-md-6 form-group">
          <label class="font-weight-bold">Ghi chú điều khoản</label>
          <el-input
            type="textarea"
            :rows="2"
            v-model="form.notes"
            placeholder="Ghi chú về giấy tờ bàn giao, thỏa thuận sang tên..."
          />
        </div>
      </div>
    </div>

    <template #modal-footer="{ cancel }">
      <b-button variant="secondary" @click="cancel">Đóng</b-button>
      <b-button variant="primary" :disabled="loading" @click="handleSubmit">
        Tạo hợp đồng & Sinh lịch trả góp
      </b-button>
    </template>
  </b-modal>
</template>

<script>
import { LEASE_CREATE_CONTRACT } from "@/core/services/store/lease.module";
import { VEHICLE_GET_ALL } from "@/core/services/store/vehicle.module";
import { WAREHOUSE_GET_SUMMARY } from "@/core/services/store/warehouse.module";
import Swal from "sweetalert2";

export default {
  name: "ModalLeaseCreate",
  data() {
    return {
      visible: false,
      loading: false,
      vehicles: [],
      form: {
        customer: {
          name: "",
          phone: "",
          id_card: "",
          address: "",
        },
        vehicle_id: null,
        start_date: new Date().toISOString().substring(0, 10),
        total_amount: 24000000,
        deposit_amount: 0,
        installment_count: 12,
        period_amount: 2000000,
        notes: "",
      },
    };
  },
  methods: {
    open() {
      this.visible = true;
      this.recalculatePeriodAmount();
      this.fetchVehicles();
    },
    async fetchVehicles() {
      this.vehicles = [];
      try {
        const summary = await this.$store.dispatch(WAREHOUSE_GET_SUMMARY);
        const store = (summary.data || []).find(s => s.kind === 'lease_to_own');
        if (!store) return;
        this.$set(this.form, 'store_id', store.id);
        const res = await this.$store.dispatch(VEHICLE_GET_ALL, { limit: 100, store_id: store.id, status: 'ready' });
        const rows = res?.data?.data || res?.data?.items || res?.data || [];
        this.vehicles = Array.isArray(rows) ? rows.filter(v => v.status === 'ready') : [];
      } catch (err) {
        Swal.fire('Lỗi', err?.data?.message || 'Không tải được xe sẵn sàng trong kho thuê sở hữu.', 'error');
      }
    },
    recalculatePeriodAmount() {
      const total = Number(this.form.total_amount) || 0;
      const deposit = Number(this.form.deposit_amount) || 0;
      const count = Number(this.form.installment_count) || 12;
      const remaining = Math.max(0, total - deposit);
      this.form.period_amount = Math.round(remaining / count);
    },
    handleSubmit() {
      if (!this.form.customer.name || !this.form.customer.phone) {
        Swal.fire("Lỗi", "Vui lòng nhập tên và số điện thoại khách hàng.", "warning");
        return;
      }
      if (!this.form.total_amount || this.form.total_amount <= 0) {
        Swal.fire("Lỗi", "Vui lòng nhập tổng giá trị hợp đồng.", "warning");
        return;
      }
      if (!this.form.vehicle_id) {
        Swal.fire('Chưa chọn xe', 'Vui lòng chọn xe sẵn sàng tại kho Thuê sở hữu.', 'warning');
        return;
      }

      this.loading = true;
      this.$store
        .dispatch(LEASE_CREATE_CONTRACT, {
          ...this.form,
          total_amount: Number(this.form.total_amount),
          deposit_amount: Number(this.form.deposit_amount || 0),
          installment_count: Number(this.form.installment_count),
          period_amount: Number(this.form.period_amount),
        })
        .then((res) => {
          Swal.fire(
            "Thành công",
            res?.message || "Tạo hợp đồng thuê sở hữu và lịch trả góp thành công.",
            "success"
          );
          this.visible = false;
          this.$emit("success");
        })
        .catch((err) => {
          const msg = err?.data?.message || err?.message || "Đã xảy ra lỗi khi tạo hợp đồng.";
          Swal.fire("Lỗi", msg, "error");
        })
        .finally(() => {
          this.loading = false;
        });
    },
    resetForm() {
      this.form = {
        customer: {
          name: "",
          phone: "",
          id_card: "",
          address: "",
        },
        vehicle_id: null,
        start_date: new Date().toISOString().substring(0, 10),
        total_amount: 24000000,
        deposit_amount: 0,
        installment_count: 12,
        period_amount: 2000000,
        notes: "",
      };
    },
  },
};
</script>
