<template>
  <b-modal
    id="modal-installment-schedule"
    v-model="visible"
    title="Chi tiết Hợp đồng & Lịch trả góp Thuê sở hữu"
    size="xl"
    no-close-on-backdrop
    @hidden="resetData"
  >
    <div v-loading="loading">
      <div v-if="contract">
        <!-- Thông tin tổng quan HĐ -->
        <div class="card card-custom card-bordered mb-4 bg-light">
          <div class="card-body p-4">
            <div class="row">
              <div class="col-md-3 border-right">
                <span class="text-muted font-size-xs d-block">MÃ HỢP ĐỒNG</span>
                <span class="font-size-h5 font-weight-bolder text-primary">{{ contract.contract_code }}</span>
                <div class="mt-2">
                  <span class="badge badge-primary mr-1">{{ contract.status_label || contract.status }}</span>
                  <span :class="getAgingBadgeClass(contract.aging_bucket)">
                    {{ getAgingLabel(contract.aging_bucket) }}
                  </span>
                </div>
              </div>
              <div class="col-md-3 border-right">
                <span class="text-muted font-size-xs d-block">KHÁCH HÀNG</span>
                <span class="font-weight-bold font-size-h6 text-dark">{{ contract.customer?.name }}</span>
                <div class="text-muted font-size-sm mt-1">
                  <div><i class="fas fa-phone mr-1"></i>{{ contract.customer?.phone }}</div>
                  <div><i class="fas fa-id-card mr-1"></i>CCCD: {{ contract.customer?.id_card || 'Chưa cập nhật' }}</div>
                </div>
              </div>
              <div class="col-md-3 border-right">
                <span class="text-muted font-size-xs d-block">XE BÀN GIAO</span>
                <span class="font-weight-bold font-size-h6 text-success">{{ contract.vehicle?.license }}</span>
                <div class="text-muted font-size-sm mt-1">
                  <div>{{ contract.vehicle?.name }}</div>
                  <div>Kho: {{ contract.store?.name || 'Kho Thuê sở hữu' }}</div>
                </div>
              </div>
              <div class="col-md-3">
                <span class="text-muted font-size-xs d-block">TỔNG QUAN TÀI CHÍNH</span>
                <div class="d-flex justify-content-between font-size-sm mt-1">
                  <span>Tổng giá trị:</span>
                  <strong>{{ contract.total_amount | formatPrice }}</strong>
                </div>
                <div class="d-flex justify-content-between font-size-sm">
                  <span>Đã thanh toán:</span>
                  <strong class="text-success">{{ contract.total_paid | formatPrice }}</strong>
                </div>
                <div class="d-flex justify-content-between font-size-sm">
                  <span>Dư nợ còn lại:</span>
                  <strong class="text-danger">{{ contract.remaining_debt | formatPrice }}</strong>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tabs: Lịch trả góp & Lịch sử thanh toán -->
        <b-tabs content-class="mt-3" nav-wrapper-class="nav-custom">
          <b-tab active>
            <template #title>
              <i class="fas fa-calendar-alt mr-2"></i>Lịch trả góp ({{ installments.length }} kỳ)
            </template>

            <div class="table-responsive">
              <table class="table table-head-custom table-vertical-center table-hover border mb-0">
                <thead class="thead-light">
                  <tr>
                    <th class="text-center" style="width: 60px;">Kỳ</th>
                    <th>Ngày đến hạn</th>
                    <th class="text-right">Số tiền kỳ</th>
                    <th class="text-right">Đã thanh toán</th>
                    <th class="text-right">Còn lại</th>
                    <th class="text-center">Trạng thái</th>
                    <th class="text-center">Quá hạn</th>
                    <th class="text-center" style="width: 120px;">Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="item in installments"
                    :key="item.id"
                    :class="{'table-danger-light': isInstallmentOverdue(item)}"
                  >
                    <td class="text-center font-weight-bold">#{{ item.period_number }}</td>
                    <td class="font-weight-bolder">{{ item.due_date | formatDate }}</td>
                    <td class="text-right font-weight-bold">{{ item.expected_amount | formatPrice }}</td>
                    <td class="text-right text-success font-weight-bold">{{ item.paid_amount | formatPrice }}</td>
                    <td class="text-right font-weight-bolder" :class="item.remaining_amount > 0 ? 'text-danger' : 'text-muted'">
                      {{ item.remaining_amount | formatPrice }}
                    </td>
                    <td class="text-center">
                      <span :class="getInstallmentStatusBadge(item)">
                        {{ getInstallmentStatusLabel(item) }}
                      </span>
                    </td>
                    <td class="text-center">
                      <span v-if="isInstallmentOverdue(item)" class="badge badge-danger font-weight-bold">
                        {{ getOverdueDays(item) }} ngày
                      </span>
                      <span v-else-if="item.status === 'paid'" class="text-success font-size-xs">
                        <i class="fas fa-check mr-1"></i>Đã xong
                      </span>
                      <span v-else class="text-muted font-size-xs">
                        Trong hạn
                      </span>
                    </td>
                    <td class="text-center">
                      <button
                        v-if="item.remaining_amount > 0"
                        type="button"
                        class="btn btn-xs btn-primary font-weight-bold"
                        @click="handlePayInstallment(item)"
                      >
                        <i class="fas fa-hand-holding-usd mr-1"></i>Thu tiền
                      </button>
                      <span v-else class="text-success font-size-xs">
                        <i class="fas fa-check-circle text-success"></i> Hoàn tất
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </b-tab>

          <!-- Tab Lịch sử phân bổ phiếu thu -->
          <b-tab>
            <template #title>
              <i class="fas fa-receipt mr-2"></i>Lịch sử thu tiền & Phân bổ ({{ allocations.length }})
            </template>

            <div v-if="allocations.length === 0" class="text-center text-muted py-5">
              Chưa có giao dịch thu tiền nào được ghi nhận cho hợp đồng này.
            </div>
            <div v-else class="table-responsive">
              <table class="table table-head-custom table-vertical-center border">
                <thead class="thead-light">
                  <tr>
                    <th>Ngày thu</th>
                    <th class="text-right">Số tiền</th>
                    <th class="text-center">Kỳ được phân bổ</th>
                    <th>Kênh thanh toán</th>
                    <th>Tài khoản / Phiếu thu</th>
                    <th>Ghi chú</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="alloc in allocations" :key="alloc.id">
                    <td>{{ alloc.created_at | formatDateTime }}</td>
                    <td class="text-right font-weight-bolder text-success">
                      +{{ alloc.amount | formatPrice }}
                    </td>
                    <td class="text-center">
                      <span class="badge badge-secondary font-weight-bold">Kỳ #{{ alloc.installment?.period_number }}</span>
                    </td>
                    <td>
                      <span v-if="alloc.transaction?.payment_method === 'cash'" class="badge badge-light-success">
                        <i class="fas fa-money-bill-wave mr-1"></i>Tiền mặt
                      </span>
                      <span v-else-if="alloc.transaction?.bank_owner_type === 'company'" class="badge badge-light-primary">
                        <i class="fas fa-university mr-1"></i>CK Công ty
                      </span>
                      <span v-else class="badge badge-light-info">
                        <i class="fas fa-university mr-1"></i>CK Cá nhân
                      </span>
                    </td>
                    <td>
                      <span v-if="alloc.transaction?.bank">
                        {{ alloc.transaction.bank.bank_name }} - {{ alloc.transaction.bank.account_number }}
                      </span>
                      <span v-else class="text-muted">Phiếu thu tiền mặt</span>
                    </td>
                    <td class="text-muted font-size-sm">
                      {{ alloc.notes || alloc.transaction?.notes || '-' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </b-tab>
        </b-tabs>
      </div>
    </div>

    <template #modal-footer="{ cancel }">
      <div class="d-flex justify-content-between align-items-center w-100">
        <div>
          <button
            v-if="contract && contract.remaining_debt > 0"
            type="button"
            class="btn btn-warning font-weight-bold mr-2"
            @click="handleOpenDebtNote"
          >
            <i class="fas fa-phone-volume mr-1"></i> Đôn đốc / Nhắc nợ
          </button>
          <button
            v-if="contract && contract.remaining_debt > 0"
            type="button"
            class="btn btn-success font-weight-bold"
            @click="handleOpenFullPayment"
          >
            <i class="fas fa-hand-holding-usd mr-1"></i> Thu tiền kỳ
          </button>
        </div>
        <b-button variant="secondary" @click="cancel">Đóng</b-button>
      </div>
    </template>
  </b-modal>
</template>

<script>
import { LEASE_GET_SHOW } from "@/core/services/store/lease.module";
import moment from "moment";

export default {
  name: "ModalInstallmentSchedule",
  data() {
    return {
      visible: false,
      loading: false,
      contractId: null,
      contract: null,
    };
  },
  computed: {
    installments() {
      return this.contract?.installments || [];
    },
    allocations() {
      return this.contract?.allocations || [];
    },
  },
  methods: {
    open(contractId) {
      this.contractId = contractId;
      this.visible = true;
      this.fetchContract();
    },
    fetchContract() {
      if (!this.contractId) return;
      this.loading = true;
      this.$store
        .dispatch(LEASE_GET_SHOW, this.contractId)
        .then((res) => {
          this.contract = res?.data || null;
        })
        .catch(() => {})
        .finally(() => {
          this.loading = false;
        });
    },
    isInstallmentOverdue(item) {
      if (item.status === "paid") return false;
      const today = moment().startOf("day");
      const due = moment(item.due_date).startOf("day");
      return due.isBefore(today);
    },
    getOverdueDays(item) {
      const today = moment().startOf("day");
      const due = moment(item.due_date).startOf("day");
      const diff = today.diff(due, "days");
      return diff > 0 ? diff : 0;
    },
    getInstallmentStatusLabel(item) {
      if (item.status === "paid") return "Đã thanh toán";
      if (item.status === "partial") return "Đã trả 1 phần";
      if (this.isInstallmentOverdue(item)) return "Quá hạn";
      return "Chờ thanh toán";
    },
    getInstallmentStatusBadge(item) {
      if (item.status === "paid") return "badge badge-success";
      if (item.status === "partial") return "badge badge-warning";
      if (this.isInstallmentOverdue(item)) return "badge badge-danger";
      return "badge badge-secondary";
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
        overdue_30_plus: "badge badge-dark",
      };
      return map[bucket] || "badge badge-secondary";
    },
    handlePayInstallment(item) {
      this.$emit("pay-installment", {
        contract: this.contract,
        amount: item.remaining_amount,
      });
    },
    handleOpenFullPayment() {
      this.$emit("pay-installment", {
        contract: this.contract,
        amount: this.contract.period_amount || this.contract.remaining_debt,
      });
    },
    handleOpenDebtNote() {
      this.$emit("open-debt-note", this.contract);
    },
    resetData() {
      this.contractId = null;
      this.contract = null;
    },
  },
};
</script>

<style scoped>
.table-danger-light {
  background-color: #fff5f5;
}
</style>
