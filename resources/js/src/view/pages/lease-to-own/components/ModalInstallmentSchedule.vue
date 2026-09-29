<template>
  <b-modal
    v-model="visible"
    title="Lịch trả góp & Theo dõi dòng tiền hợp đồng"
    size="xl"
    hide-footer
    no-close-on-backdrop
    @hidden="resetData"
  >
    <div v-if="loading" class="text-center py-5">
      <span class="spinner-border spinner-border-sm text-primary"></span>
      <span class="ml-2 font-weight-bold">Đang tải thông tin hợp đồng...</span>
    </div>

    <div v-else-if="contract">
      <!-- Thông tin tóm tắt hợp đồng -->
      <div class="row bg-light rounded p-4 mb-4">
        <div class="col-md-3">
          <span class="text-muted font-size-xs d-block">MÃ HỢP ĐỒNG</span>
          <span class="font-size-h5 font-weight-bolder text-primary">
            {{ contract.contract_number }}
          </span>
          <span class="d-block mt-1">
            <span :class="getAgingBadgeClass(contract.aging_bucket)">
              {{ getAgingLabel(contract.aging_bucket) }}
            </span>
          </span>
        </div>

        <div class="col-md-3">
          <span class="text-muted font-size-xs d-block">KHÁCH HÀNG</span>
          <span class="font-weight-bold text-dark d-block">
            {{ contract.customer?.full_name }}
          </span>
          <div>SĐT: {{ contract.customer?.phone }}</div>
          <div>CCCD: {{ contract.customer?.id_card || 'Chưa cập nhật' }}</div>
        </div>

        <div class="col-md-3">
          <span class="text-muted font-size-xs d-block">XE THUÊ SỞ HỮU</span>
          <span class="font-weight-bold text-dark d-block">
            {{ contract.vehicle?.license_plate }}
          </span>
          <span class="text-muted font-size-sm">
            {{ contract.vehicle?.brand }} {{ contract.vehicle?.model }}
          </span>
        </div>

        <div class="col-md-3">
          <span class="text-muted font-size-xs d-block">DƯ NỢ CÒN LẠI</span>
          <span class="font-size-h5 font-weight-bolder text-danger d-block">
            {{ contract.outstanding_balance | formatPrice }}
          </span>
          <span class="text-muted font-size-xs">
            Tổng: {{ contract.total_value | formatPrice }} | Đã thu: {{ contract.total_paid | formatPrice }}
          </span>
        </div>
      </div>

      <!-- Tabs nội dung: Lịch trả góp & Lịch sử phân bổ phiếu thu -->
      <b-tabs content-class="mt-3">
        <!-- Tab Lịch trả góp -->
        <b-tab active>
          <template #title>
            Lịch trả góp ({{ installments.length }} kỳ)
          </template>

          <div class="table-responsive">
            <table class="table table-head-custom table-vertical-center border">
              <thead class="thead-light">
                <tr>
                  <th style="width: 60px;" class="text-center">Kỳ</th>
                  <th>Hạn thanh toán</th>
                  <th class="text-right">Số tiền phải thu</th>
                  <th class="text-right">Đã thanh toán</th>
                  <th class="text-right">Điều chỉnh</th>
                  <th class="text-right">Còn lại</th>
                  <th class="text-center">Trạng thái</th>
                  <th class="text-center">Độ trễ hạn</th>
                  <th class="text-center" style="width: 120px;">Thao tác</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="item in installments"
                  :key="item.id"
                  :class="{ 'table-danger-light': isInstallmentOverdue(item) }"
                >
                  <td class="text-center font-weight-bolder">#{{ item.period_number }}</td>
                  <td>
                    <span class="font-weight-bold" :class="isInstallmentOverdue(item) ? 'text-danger' : 'text-dark'">
                      {{ item.due_date | formatDate }}
                    </span>
                  </td>
                  <td class="text-right font-weight-bold">{{ item.expected_amount | formatPrice }}</td>
                  <td class="text-right text-success font-weight-bold">{{ item.paid_amount | formatPrice }}</td>
                  <td class="text-right text-info font-weight-bold">{{ item.adjustment_amount | formatPrice }}</td>
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
                    <span v-else-if="item.status === 'paid'" class="text-success font-size-xs font-weight-bold">
                      Đã xong
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
                      Thu tiền
                    </button>
                    <span v-else class="text-success font-size-xs font-weight-bold">
                      Hoàn tất
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
            Lịch sử thu tiền & Phân bổ ({{ allocations.length }})
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
                  <th class="text-center" style="width: 100px;">Thao tác</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="alloc in allocations" :key="alloc.id">
                  <td>{{ alloc.payment_date | formatDate }}</td>
                  <td class="text-right font-weight-bolder text-success">
                    {{ alloc.status === 'discount' ? '' : '+' }}{{ alloc.amount | formatPrice }}
                  </td>
                  <td class="text-center">
                    <span class="badge badge-secondary font-weight-bold">Kỳ #{{ alloc.installment?.period_number }}</span>
                  </td>
                  <td>
                    <span v-if="alloc.status === 'discount'" class="badge badge-light-info font-weight-bold">
                      Chiết khấu
                    </span>
                    <span v-else-if="Number(alloc.transaction?.payment_method) === 1" class="badge badge-light-success font-weight-bold">
                      Tiền mặt
                    </span>
                    <span v-else-if="alloc.transaction?.bank_owner_type === 'company'" class="badge badge-light-primary font-weight-bold">
                      CK Công ty
                    </span>
                    <span v-else-if="alloc.transaction?.bank_owner_type === 'personal'" class="badge badge-light-info font-weight-bold">
                      CK Cá nhân
                    </span>
                    <span v-else class="badge badge-light font-weight-bold">CK khác</span>
                  </td>
                  <td>
                    <span v-if="alloc.status === 'discount'" class="text-muted">Không phát sinh phiếu thu</span>
                    <span v-else-if="alloc.transaction?.bank">
                      {{ alloc.transaction.bank.bank_name }} - {{ alloc.transaction.bank.account_number }}
                    </span>
                    <span v-else class="text-muted">Phiếu thu tiền mặt</span>
                  </td>
                  <td class="text-muted font-size-sm">
                    {{ alloc.notes || alloc.transaction?.notes || '-' }}
                  </td>
                  <td class="text-center">
                    <button
                      v-if="alloc.status !== 'discount' && alloc.status !== 'reversed'"
                      type="button"
                      class="btn btn-xs btn-outline-danger font-weight-bold"
                      @click="handleReverseAllocation(alloc)"
                    >
                      Đảo thu
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </b-tab>
      </b-tabs>

      <!-- Footer điều hướng và nghiệp vụ -->
      <div class="d-flex justify-content-between align-items-center mt-5 pt-3 border-top">
        <div>
          <button
            v-if="contract && contract.outstanding_balance > 0"
            type="button"
            class="btn btn-warning font-weight-bold mr-2"
            @click="handleOpenDebtNote"
          >
            Đôn đốc / Nhắc nợ
          </button>
          <button
            v-if="contract && contract.outstanding_balance > 0"
            type="button"
            class="btn btn-success font-weight-bold mr-2"
            @click="handleOpenFullPayment"
          >
            Thu tiền kỳ
          </button>
          <button
            v-if="contract && contract.outstanding_balance > 0"
            type="button"
            class="btn btn-outline-primary font-weight-bold mr-2"
            @click="handleEarlySettlement"
          >
            Tất toán sớm hợp đồng
          </button>
        </div>
        <div>
          <button type="button" class="btn btn-secondary font-weight-bold" @click="visible = false">
            Đóng
          </button>
        </div>
      </div>
    </div>
  </b-modal>
</template>

<script>
import ApiService from "@/core/services/api.service";
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
        amount: this.contract.period_amount || this.contract.outstanding_balance,
      });
    },
    handleOpenDebtNote() {
      this.$emit("open-debt-note", this.contract);
    },
    async handleEarlySettlement() {
      try {
        const { value: notes } = await this.$prompt(
          `Xác nhận tất toán toàn bộ dư nợ còn lại (${this.formatCurrency(this.contract.outstanding_balance)}) của hợp đồng này? Nhập ghi chú tất toán (nếu có):`,
          "Tất toán hợp đồng thuê sở hữu",
          {
            confirmButtonText: "Xác nhận tất toán",
            cancelButtonText: "Hủy bỏ",
            inputPlaceholder: "Ghi chú tất toán sớm...",
          }
        );

        await ApiService.post(`/api/auth/lease-contracts/${this.contractId}/settle`, {
          settlement_amount: this.contract.outstanding_balance,
          notes: notes || "Tất toán sớm toàn bộ dư nợ",
          note: notes || "Tất toán sớm toàn bộ dư nợ",
          payment_method: 1,
        });

        this.$message.success("Hợp đồng đã được tất toán thành công!");
        this.fetchContract();
        this.$emit("contract-updated");
      } catch (err) {
        if (err !== "cancel") {
          this.$message.error(err.response?.data?.message || "Không thể tất toán hợp đồng");
        }
      }
    },
    async handleReverseAllocation(alloc) {
      try {
        const { value: reason } = await this.$prompt(
          `Bạn có chắc muốn đảo thu khoản ${this.formatCurrency(alloc.amount)} ngày ${alloc.payment_date}? Vui lòng nhập lý do đảo thu (bắt buộc, tối thiểu 5 ký tự):`,
          "Xác nhận đảo thu",
          {
            confirmButtonText: "Đồng ý đảo thu",
            cancelButtonText: "Hủy bỏ",
            inputValidator: (val) => {
              if (!val || val.trim().length < 5) {
                return "Vui lòng nhập lý do cụ thể (ít nhất 5 ký tự)";
              }
              return true;
            },
          }
        );

        await ApiService.post(`/api/auth/lease-contracts/reverse-allocation/${alloc.id}`, {
          reason: reason.trim(),
        });

        this.$message.success("Đã đảo thu thành công. Dư nợ kỳ trả góp đã được phục hồi.");
        this.fetchContract();
        this.$emit("contract-updated");
      } catch (err) {
        if (err !== "cancel") {
          this.$message.error(err.response?.data?.message || "Đảo thu thất bại");
        }
      }
    },
    formatCurrency(val) {
      return new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(val || 0);
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
