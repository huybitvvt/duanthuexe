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
      <el-tabs v-model="activeTab" type="card">
        <el-tab-pane label="1. Khách hàng & giám hộ" name="customer">
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
        <div class="col-md-3 form-group"><label class="font-weight-bold">Ngày cấp CCCD</label><el-date-picker v-model="form.customer.id_card_issued_on" type="date" format="dd/MM/yyyy" value-format="yyyy-MM-dd" class="w-100" /></div>
        <div class="col-md-3 form-group"><label class="font-weight-bold">Nơi cấp CCCD</label><el-input v-model="form.customer.id_card_issued_by" /></div>
        <div class="col-md-6 form-group"><label class="font-weight-bold">Người thân</label><el-input v-model="form.customer.relatives[0].name" placeholder="Họ tên" /></div>
        <div class="col-md-3 form-group"><label class="font-weight-bold">Quan hệ</label><el-input v-model="form.customer.relatives[0].relationship" placeholder="Bố, mẹ, vợ, chồng..." /></div>
        <div class="col-md-3 form-group"><label class="font-weight-bold">SĐT người thân</label><el-input v-model="form.customer.relatives[0].phone" /></div>
        <div class="col-md-6 form-group"><label class="font-weight-bold">Người giám hộ / đại diện hợp pháp</label><el-input v-model="form.guardian_name" placeholder="Họ tên người giám hộ" /></div>
        <div class="col-md-3 form-group"><label class="font-weight-bold">SĐT giám hộ</label><el-input v-model="form.guardian_phone" /></div>
        <div class="col-md-3 form-group"><label class="font-weight-bold">CCCD giám hộ</label><el-input v-model="form.guardian_id_card" /></div>
        <div class="col-md-4 form-group"><label class="font-weight-bold">Tên lái xe</label><el-input v-model="form.driver_name" placeholder="Mặc định là khách hàng" /></div>
        <div class="col-md-4 form-group"><label class="font-weight-bold">Số GPLX</label><el-input v-model="form.driver_license_number" /></div>
        <div class="col-md-4 form-group"><label class="font-weight-bold">Ngày cấp GPLX</label><el-date-picker v-model="form.driver_license_issued_on" type="date" format="dd/MM/yyyy" value-format="yyyy-MM-dd" class="w-100" /></div>
      </div>
        </el-tab-pane>
        <el-tab-pane label="2. Xe & điều khoản" name="terms">
      <div class="row">

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
          <label class="font-weight-bold">Tiền trả trước (VNĐ)</label>
          <el-input v-model="form.prepaid_amount" type="number" placeholder="0" @input="recalculatePeriodAmount" />
          <small class="form-text text-muted">Thu riêng vào ngày bắt đầu, không gộp với tiền cọc.</small>
        </div>
        <div class="col-md-4 form-group">
          <label class="font-weight-bold">Tiền đặt cọc (VNĐ)</label>
          <el-input v-model="form.deposit_amount" type="number" placeholder="0" @input="recalculatePeriodAmount" />
          <small class="form-text text-muted">Khoản cọc riêng (kỳ 0). Không dùng trường này để ghi tiền trả trước.</small>
        </div>
        <div class="col-md-4 form-group">
          <label class="font-weight-bold">Số kỳ trả góp (tháng) <span class="text-danger">*</span></label>
          <el-select v-model="form.installment_count" class="w-100" @change="onInstallmentChange">
            <el-option label="6 tháng" :value="6" />
            <el-option label="12 tháng" :value="12" />
            <el-option label="24 tháng" :value="24" />
          </el-select>
        </div>
        <div class="col-md-4 form-group">
          <label class="font-weight-bold">Kỳ thanh toán <span class="text-danger">*</span></label>
          <el-select v-model="form.billing_cycle" class="w-100" @change="recalculatePeriodAmount">
            <el-option v-for="option in cycleOptions" :key="option.value" :label="option.label" :value="option.value" />
          </el-select>
          <small class="form-text text-muted">{{ cycleHint }}</small>
        </div>

        <div class="col-md-6 form-group">
          <label class="font-weight-bold">Số tiền mỗi kỳ (VNĐ/{{ cycleUnit }}) <span class="text-danger">*</span></label>
          <el-input v-model="form.period_amount" type="number" placeholder="Tự động tính theo số kỳ" />
          <small class="form-text text-muted">(Tổng giá trị − Tiền trả trước − Tiền cọc) ÷ {{ paymentCount }} kỳ</small>
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
        </el-tab-pane>
        <el-tab-pane label="3. Bộ 3 giấy tờ" name="documents">
          <p class="text-muted mb-2">Một lần nhập điền cùng lúc cả 3 giấy tờ. Kỳ hạn ở tab “Xe & điều khoản” chọn 1 trong 3 phụ lục.</p>
          <ol class="pl-3 mb-0">
            <li>Hợp đồng thuê xe</li>
            <li>Biên bản bàn giao xe</li>
            <li>Phụ lục {{ annexLabel }}</li>
          </ol>
        </el-tab-pane>
      </el-tabs>
    </div>

    <template #modal-footer="{ cancel }">
      <b-button variant="secondary" @click="cancel">Đóng</b-button>
      <b-button variant="primary" :disabled="loading" @click="handleSubmit">
        Tạo hợp đồng & điền 3 giấy tờ
      </b-button>
    </template>
  </b-modal>
</template>

<script>
import { LEASE_CREATE_CONTRACT } from "@/core/services/store/lease.module";
import { VEHICLE_GET_ALL } from "@/core/services/store/vehicle.module";
import { WAREHOUSE_GET_SUMMARY } from "@/core/services/store/warehouse.module";
import ApiService from "@/core/services/api.service";
import Swal from "sweetalert2";

export default {
  name: "ModalLeaseCreate",
  data() {
    return {
      visible: false,
      loading: false,
      activeTab: "customer",
      vehicles: [],
      form: {
        customer: {
          name: "",
          phone: "",
          id_card: "",
          address: "",
          id_card_issued_on: "",
          id_card_issued_by: "",
          relatives: [{ name: "", relationship: "", phone: "" }],
        },
        vehicle_id: null,
        start_date: new Date().toISOString().substring(0, 10),
        total_amount: 24000000,
        deposit_amount: 0,
        installment_count: 12,
        billing_cycle: "month",
        period_amount: 2000000,
        prepaid_amount: 0,
        notes: "",
        guardian_name: "",
        guardian_phone: "",
        guardian_id_card: "",
        driver_name: "",
        driver_license_number: "",
        driver_license_issued_on: "",
      },
    };
  },
  computed: {
    cycleOptions() {
      const options = [
        { value: "week", label: "Theo tuần" },
        { value: "month", label: "Theo tháng" },
      ];
      if (Number(this.form.installment_count) === 6) {
        options.unshift({ value: "day", label: "Theo ngày (tháng 30 ngày)" });
      }
      return options;
    },
    cycleUnit() {
      return { day: "ngày", week: "tuần", month: "tháng" }[this.form.billing_cycle] || "tháng";
    },
    cycleHint() {
      if (Number(this.form.installment_count) === 6) {
        return "6 tháng: ngày, tuần hoặc tháng. Theo ngày thì mỗi tháng tính 30 ngày. Nếu ngày bắt đầu là 30, kỳ tháng giữ ngày 30.";
      }
      return "12 và 24 tháng chỉ thanh toán theo tuần hoặc tháng.";
    },
    paymentCount() {
      const term = Number(this.form.installment_count) || 12;
      if (this.form.billing_cycle === "day") return term * 30;
      if (this.form.billing_cycle === "week") return term * 4;
      return term;
    },
    annexLabel() {
      const term = Number(this.form.installment_count);
      return { 6: "SH06 — 6 tháng", 12: "SH12 — 12 tháng", 24: "SH24 — 24 tháng" }[term] || "theo kỳ hạn đã chọn";
    },
  },
  methods: {
    onInstallmentChange() {
      if (Number(this.form.installment_count) !== 6 && this.form.billing_cycle === "day") {
        this.form.billing_cycle = "month";
      }
      this.recalculatePeriodAmount();
    },
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
        this.vehicles = Array.isArray(rows)
          ? rows.filter(v => v.status === 'ready' && Number(v.current_store_id || v.store_id) === Number(store.id))
          : [];
      } catch (err) {
        Swal.fire('Lỗi', err?.data?.message || 'Không tải được xe sẵn sàng trong kho thuê sở hữu.', 'error');
      }
    },
    recalculatePeriodAmount() {
      const total = Number(this.form.total_amount) || 0;
      const deposit = Number(this.form.deposit_amount) || 0;
      const prepaid = Number(this.form.prepaid_amount) || 0;
      const count = this.paymentCount || 1;
      const remaining = Math.max(0, total - deposit - prepaid);
      this.form.period_amount = Math.round(remaining / count);
    },
    handleSubmit() {
      if (!this.form.customer.name || !this.form.customer.phone) {
        this.activeTab = 'customer';
        Swal.fire("Lỗi", "Vui lòng nhập tên và số điện thoại khách hàng.", "warning");
        return;
      }
      if (!this.form.total_amount || this.form.total_amount <= 0) {
        this.activeTab = 'terms';
        Swal.fire("Lỗi", "Vui lòng nhập tổng giá trị hợp đồng.", "warning");
        return;
      }
      if (!this.form.vehicle_id) {
        this.activeTab = 'terms';
        Swal.fire('Chưa chọn xe', 'Vui lòng chọn xe sẵn sàng tại kho Thuê sở hữu.', 'warning');
        return;
      }
      const prepaid = Number(this.form.prepaid_amount || 0);
      const deposit = Number(this.form.deposit_amount || 0);
      if (prepaid + deposit > Number(this.form.total_amount)) {
        this.activeTab = 'terms';
        Swal.fire('Sai số tiền', 'Tiền trả trước cộng tiền đặt cọc không được lớn hơn tổng giá trị hợp đồng.', 'warning');
        return;
      }

      const tabs = [window.open('', '_blank'), window.open('', '_blank'), window.open('', '_blank')];
      this.loading = true;
      this.$store
        .dispatch(LEASE_CREATE_CONTRACT, {
          ...this.form,
          total_amount: Number(this.form.total_amount),
          deposit_amount: deposit,
          prepaid_amount: prepaid,
          installment_count: Number(this.form.installment_count),
          period_amount: Number(this.form.period_amount),
        })
        .then(async (res) => {
          const contract = res?.data || null;
          if (!contract || contract.status === 'draft') {
            tabs.forEach((tab) => tab && tab.close());
            Swal.fire('Đã lập nháp', res?.message || 'Hợp đồng chờ trưởng phòng duyệt. Sau khi duyệt, chỉ quản lý xem được hợp đồng hợp lệ.', 'success');
          } else {
            const months = Number(this.form.installment_count);
            const paths = ['rental-contract', 'handover', `annex?months=${months}`];
            await Promise.all(paths.map((path, index) => this.fillPaper(tabs[index], contract.id, path)));
            Swal.fire('Thành công', 'Đã điền cùng lúc hợp đồng thuê xe, biên bản bàn giao và phụ lục.', 'success');
          }
          this.visible = false;
          this.$emit('success', contract);
        })
        .catch((err) => {
          tabs.forEach((tab) => tab && tab.close());
          const msg = err?.data?.message || err?.message || "Đã xảy ra lỗi khi tạo hợp đồng.";
          Swal.fire("Lỗi", msg, "error");
        })
        .finally(() => {
          this.loading = false;
        });
    },
    async fillPaper(tab, contractId, path) {
      try {
        const response = await ApiService.download(`/api/auth/lease-contracts/${contractId}/${path}`);
        const url = window.URL.createObjectURL(new Blob([response.data], { type: 'text/html;charset=utf-8' }));
        if (tab) tab.location.href = url;
        setTimeout(() => window.URL.revokeObjectURL(url), 60000);
      } catch (error) {
        if (tab) tab.close();
      }
    },
    resetForm() {
      this.activeTab = 'customer';
      this.form = {
        customer: {
          name: "",
          phone: "",
          id_card: "",
          address: "",
          id_card_issued_on: "",
          id_card_issued_by: "",
          relatives: [{ name: "", relationship: "", phone: "" }],
        },
        vehicle_id: null,
        start_date: new Date().toISOString().substring(0, 10),
        total_amount: 24000000,
        deposit_amount: 0,
        prepaid_amount: 0,
        installment_count: 12,
        billing_cycle: "month",
        period_amount: 2000000,
        notes: "",
        guardian_name: "",
        guardian_phone: "",
        guardian_id_card: "",
        driver_name: "",
        driver_license_number: "",
        driver_license_issued_on: "",
      };
    },
  },
};
</script>
