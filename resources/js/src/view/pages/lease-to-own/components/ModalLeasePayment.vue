<template>
  <b-modal
    id="modal-lease-payment"
    v-model="visible"
    title="Thu tiền kỳ / Trả góp Thuê sở hữu"
    size="md"
    no-close-on-backdrop
    @hidden="resetForm"
  >
    <div v-loading="loading">
      <!-- Tóm tắt hợp đồng -->
      <div v-if="contract" class="alert alert-custom alert-light-primary p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <span class="font-weight-bold font-size-h6 text-primary">HĐ: {{ contract.contract_code }}</span>
          <span class="badge badge-primary">{{ contract.status_label || contract.status }}</span>
        </div>
        <div class="text-dark font-size-sm">
          <div><strong>Khách hàng:</strong> {{ contract.customer?.name }} - {{ contract.customer?.phone }}</div>
          <div><strong>Xe bàn giao:</strong> {{ contract.vehicle?.license }} - {{ contract.vehicle?.name }}</div>
          <div><strong>Tổng dư nợ còn lại:</strong> <span class="text-danger font-weight-bold">{{ contract.remaining_debt | formatPrice }}</span></div>
        </div>
      </div>

      <div class="form-group">
        <label class="font-weight-bold">Số tiền thanh toán (VNĐ) <span class="text-danger">*</span></label>
        <el-input
          v-model="form.amount"
          type="number"
          placeholder="Nhập số tiền cần thu"
          class="w-100"
        />
        <div class="mt-2 d-flex flex-wrap gap-1">
          <button
            v-if="contract && contract.period_amount"
            type="button"
            class="btn btn-xs btn-outline-secondary mr-2"
            @click="form.amount = contract.period_amount"
          >
            1 kỳ ({{ contract.period_amount | formatPrice }})
          </button>
          <button
            v-if="contract && contract.remaining_debt"
            type="button"
            class="btn btn-xs btn-outline-success"
            @click="form.amount = contract.remaining_debt"
          >
            Tất toán hết ({{ contract.remaining_debt | formatPrice }})
          </button>
        </div>
      </div>

      <!-- Kênh thanh toán -->
      <div class="form-group">
        <label class="font-weight-bold">Kênh thanh toán <span class="text-danger">*</span></label>
        <div class="d-flex align-items-center">
          <label class="radio radio-outline radio-primary mr-4">
            <input type="radio" v-model="form.payment_method" value="cash" />
            <span></span>
            <i class="fas fa-money-bill-wave text-success mr-1"></i> Tiền mặt
          </label>
          <label class="radio radio-outline radio-primary">
            <input type="radio" v-model="form.payment_method" value="bank_transfer" />
            <span></span>
            <i class="fas fa-university text-primary mr-1"></i> Chuyển khoản
          </label>
        </div>
      </div>

      <!-- Chọn ngân hàng nếu chuyển khoản -->
      <div v-if="form.payment_method === 'bank_transfer'" class="form-group">
        <label class="font-weight-bold">Tài khoản ngân hàng thụ hưởng <span class="text-danger">*</span></label>
        <el-select
          v-model="form.bank_id"
          filterable
          placeholder="Chọn tài khoản nhận tiền"
          class="w-100"
        >
          <el-option
            v-for="b in banks"
            :key="b.id"
            :label="`${b.bank_name} - ${b.owner_name} - ${b.account_number} [${b.owner_type === 'company' ? 'CK Công ty' : 'CK Cá nhân'}]`"
            :value="b.id"
          >
            <div class="d-flex justify-content-between align-items-center">
              <span>{{ b.bank_name }} - {{ b.owner_name }} ({{ b.account_number }})</span>
              <span
                :class="b.owner_type === 'company' ? 'badge badge-primary' : 'badge badge-info'"
                style="font-size: 11px;"
              >
                {{ b.owner_type === 'company' ? 'Công ty' : 'Cá nhân' }}
              </span>
            </div>
          </el-option>
        </el-select>
        <small class="form-text text-muted">
          Chọn đúng tài khoản để hệ thống ghi nhận đúng kênh CK Cá nhân hoặc CK Công ty.
        </small>
      </div>

      <!-- Ngày thu -->
      <div class="form-group">
        <label class="font-weight-bold">Ngày thu tiền</label>
        <el-date-picker
          v-model="form.paid_at"
          type="date"
          placeholder="Chọn ngày thu"
          format="dd/MM/yyyy"
          value-format="yyyy-MM-dd"
          class="w-100"
        />
      </div>

      <!-- Ghi chú -->
      <div class="form-group">
        <label class="font-weight-bold">Ghi chú giao dịch</label>
        <el-input
          type="textarea"
          :rows="2"
          v-model="form.notes"
          placeholder="Ghi chú thêm về giao dịch thu tiền này..."
        />
      </div>

      <div class="alert alert-custom alert-light-warning p-2 font-size-xs text-muted mb-0">
        <i class="fas fa-info-circle text-warning mr-1"></i>
        Khoản thu sẽ được tự động phân bổ theo thứ tự lũy kế các kỳ trả góp chưa thanh toán (từ cũ nhất đến mới nhất) và tạo phiếu thu trong sổ quỹ tài chính.
      </div>
    </div>

    <template #modal-footer="{ cancel }">
      <b-button variant="secondary" @click="cancel">Hủy</b-button>
      <b-button variant="success" :disabled="loading" @click="handleSubmit">
        <i class="fas fa-hand-holding-usd mr-1"></i> Xác nhận thu tiền
      </b-button>
    </template>
  </b-modal>
</template>

<script>
import { LEASE_ALLOCATE_PAYMENT } from "@/core/services/store/lease.module";
import { BANK_GET_ALL } from "@/core/services/store/banks.module";
import Swal from "sweetalert2";

export default {
  name: "ModalLeasePayment",
  data() {
    return {
      visible: false,
      loading: false,
      contract: null,
      banks: [],
      form: {
        amount: null,
        payment_method: "cash",
        bank_id: null,
        paid_at: new Date().toISOString().substring(0, 10),
        notes: "",
      },
    };
  },
  methods: {
    open(contract, suggestedAmount = null) {
      this.contract = contract;
      this.form.amount = suggestedAmount !== null ? suggestedAmount : (contract?.period_amount || contract?.remaining_debt || 0);
      this.form.paid_at = new Date().toISOString().substring(0, 10);
      this.form.payment_method = "cash";
      this.form.bank_id = null;
      this.form.notes = "";
      this.visible = true;
      this.fetchBanks();
    },
    fetchBanks() {
      this.$store
        .dispatch(BANK_GET_ALL)
        .then((res) => {
          this.banks = res?.data || res || [];
        })
        .catch(() => {});
    },
    handleSubmit() {
      const amount = Number(this.form.amount);
      if (!amount || amount <= 0) {
        Swal.fire("Lỗi", "Vui lòng nhập số tiền thanh toán hợp lệ.", "warning");
        return;
      }
      if (this.form.payment_method === "bank_transfer" && !this.form.bank_id) {
        Swal.fire("Lỗi", "Vui lòng chọn tài khoản ngân hàng thụ hưởng.", "warning");
        return;
      }

      this.loading = true;
      this.$store
        .dispatch(LEASE_ALLOCATE_PAYMENT, {
          contractId: this.contract.id,
          payload: {
            amount: amount,
            payment_method: this.form.payment_method,
            bank_id: this.form.payment_method === "bank_transfer" ? this.form.bank_id : null,
            paid_at: this.form.paid_at,
            notes: this.form.notes,
          },
        })
        .then((res) => {
          Swal.fire(
            "Thành công",
            res?.message || "Đã thu tiền và phân bổ thành công.",
            "success"
          );
          this.visible = false;
          this.$emit("success");
        })
        .catch((err) => {
          const msg = err?.data?.message || err?.message || "Đã có lỗi xảy ra khi thanh toán.";
          Swal.fire("Lỗi", msg, "error");
        })
        .finally(() => {
          this.loading = false;
        });
    },
    resetForm() {
      this.contract = null;
      this.form = {
        amount: null,
        payment_method: "cash",
        bank_id: null,
        paid_at: new Date().toISOString().substring(0, 10),
        notes: "",
      };
    },
  },
};
</script>
