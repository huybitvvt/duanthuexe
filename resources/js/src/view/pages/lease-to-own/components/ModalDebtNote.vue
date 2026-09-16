<template>
  <b-modal
    id="modal-debt-note"
    v-model="visible"
    title="Ghi chú đôn đốc & Lịch sử nhắc nợ"
    size="lg"
    no-close-on-backdrop
    @hidden="resetForm"
  >
    <div v-loading="loading">
      <!-- Thông tin hợp đồng & khách hàng -->
      <div v-if="contract" class="alert alert-custom alert-light-danger p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <span class="font-weight-bold font-size-h6 text-danger">HĐ: {{ contract.contract_code }}</span>
          <span class="badge badge-danger">Quá hạn: {{ contract.overdue_days || 0 }} ngày</span>
        </div>
        <div class="text-dark font-size-sm">
          <div><strong>Khách hàng:</strong> {{ contract.customer?.name }} | <strong>SĐT:</strong> <a :href="'tel:' + contract.customer?.phone" class="text-primary font-weight-bold">{{ contract.customer?.phone }}</a></div>
          <div><strong>Xe bàn giao:</strong> {{ contract.vehicle?.license }} - {{ contract.vehicle?.name }}</div>
          <div><strong>Tổng dư nợ còn lại:</strong> <span class="text-danger font-weight-bold">{{ contract.remaining_debt | formatPrice }}</span></div>
        </div>
      </div>

      <!-- Form thêm ghi chú mới -->
      <div class="card card-custom card-bordered mb-4">
        <div class="card-header bg-light py-2 px-3 min-h-40px">
          <div class="card-title m-0">
            <h6 class="font-weight-bolder text-dark m-0">
              Thêm lượt đôn đốc / Nhắc nợ
            </h6>
          </div>
        </div>
        <div class="card-body p-3">
          <div class="row">
            <div class="col-md-6 form-group">
              <label class="font-weight-bold">Kết quả liên hệ <span class="text-danger">*</span></label>
              <el-select v-model="form.call_status" class="w-100" placeholder="Chọn trạng thái">
                <el-option label="Đã nghe máy - Đồng ý thanh toán" value="connected" />
                <el-option label="Khách hẹn ngày thanh toán" value="promise" />
                <el-option label="Không nghe máy / Thuê bao" value="no_answer" />
                <el-option label="Máy bận / Gọi lại sau" value="busy" />
                <el-option label="Khiếu nại / Khó đòi" value="dispute" />
                <el-option label="Khác" value="other" />
              </el-select>
            </div>
            <div class="col-md-6 form-group">
              <label class="font-weight-bold">Ngày hẹn thanh toán (nếu có)</label>
              <el-date-picker
                v-model="form.promised_date"
                type="date"
                placeholder="Chọn ngày hẹn"
                format="dd/MM/yyyy"
                value-format="yyyy-MM-dd"
                class="w-100"
              />
            </div>
            <div class="col-12 form-group">
              <label class="font-weight-bold">Phân loại công nợ</label>
              <el-select v-model="form.debt_classification" class="w-100">
                <el-option label="Bình thường" value="normal" />
                <el-option label="Cần nhắc" value="reminder" />
                <el-option label="Cảnh báo" value="warning" />
                <el-option label="Nợ xấu" value="bad_debt" />
              </el-select>
            </div>
            <div class="col-12 form-group mb-0">
              <label class="font-weight-bold">Nội dung trao đổi / Kết quả nhắc nợ <span class="text-danger">*</span></label>
              <el-input
                type="textarea"
                :rows="2"
                v-model="form.notes"
                placeholder="Khách hứa chuyển khoản trước 17h, lý do chậm trễ, yêu cầu hỗ trợ..."
              />
            </div>
          </div>
          <div class="text-right mt-3">
            <button
              type="button"
              class="btn btn-sm btn-primary font-weight-bold"
              :disabled="loading"
              @click="handleSubmit"
            >
              Lưu lượt nhắc nợ
            </button>
          </div>
        </div>
      </div>

      <!-- Lịch sử các lần nhắc nợ trước -->
      <div class="card card-custom card-bordered">
        <div class="card-header bg-light py-2 px-3 min-h-40px">
          <div class="card-title m-0">
            <h6 class="font-weight-bolder text-dark m-0">
              Lịch sử các lần đôn đốc trước ({{ (contract && contract.debt_notes) ? contract.debt_notes.length : 0 }})
            </h6>
          </div>
        </div>
        <div class="card-body p-3" style="max-height: 250px; overflow-y: auto;">
          <div v-if="!contract || !contract.debt_notes || contract.debt_notes.length === 0" class="text-center text-muted py-3">
            Chưa có ghi chú đôn đốc nào cho hợp đồng này.
          </div>
          <div v-else class="timeline timeline-3">
            <div
              v-for="item in contract.debt_notes"
              :key="item.id"
              class="timeline-item d-flex align-items-start mb-3 pb-2 border-bottom"
            >
              <div class="timeline-badge mr-3">
                <span :class="getStatusBadgeClass(item.call_status)" class="badge px-2 py-1 font-weight-bold">
                  {{ item.call_status }}
                </span>
              </div>
              <div class="timeline-content flex-grow-1">
                <div class="d-flex justify-content-between align-items-center">
                  <span class="font-weight-bold text-dark">{{ item.debt_classification }}</span>
                  <span class="text-muted font-size-xs">{{ item.created_at | formatDateTime }}</span>
                </div>
                <div v-if="item.promised_date" class="text-primary font-size-xs my-1 font-weight-bold">
                  Hẹn thanh toán: {{ item.promised_date | formatDate }}
                </div>
                <div class="text-dark-75 font-size-sm mt-1 bg-light rounded p-2">
                  {{ item.notes }}
                </div>
                <div v-if="item.user" class="text-muted font-size-xs text-right mt-1">
                  Nhân viên: {{ item.user.name }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <template #modal-footer="{ cancel }">
      <b-button variant="secondary" @click="cancel">Đóng</b-button>
    </template>
  </b-modal>
</template>

<script>
import { LEASE_ADD_NOTE, LEASE_GET_SHOW } from "@/core/services/store/lease.module";
import Swal from "sweetalert2";

export default {
  name: "ModalDebtNote",
  data() {
    return {
      visible: false,
      loading: false,
      contract: null,
      form: {
        call_status: "connected",
        debt_classification: "normal",
        promised_date: null,
        notes: "",
      },
    };
  },
  methods: {
    open(contract) {
      this.contract = contract;
      this.visible = true;
      this.refreshContract();
    },
    refreshContract() {
      if (!this.contract?.id) return;
      this.$store
        .dispatch(LEASE_GET_SHOW, this.contract.id)
        .then((res) => {
          this.contract = res?.data || this.contract;
        })
        .catch(() => {});
    },
    handleSubmit() {
      if (!this.form.notes || !this.form.notes.trim()) {
        Swal.fire("Lỗi", "Vui lòng nhập nội dung trao đổi / ghi chú đôn đốc.", "warning");
        return;
      }

      this.loading = true;
      this.$store
        .dispatch(LEASE_ADD_NOTE, {
          contractId: this.contract.id,
          payload: {
            call_status: this.form.call_status,
            appointment_date: this.form.promised_date,
            debt_classification: this.form.debt_classification,
            note_content: '[' + this.getStatusLabel(this.form.call_status) + '] ' + this.form.notes,
          },
        })
        .then((res) => {
          Swal.fire("Thành công", res?.message || "Đã lưu ghi chú đôn đốc.", "success");
          this.form.notes = "";
          this.form.promised_date = null;
          this.refreshContract();
          this.$emit("success");
        })
        .catch((err) => {
          const msg = err?.data?.message || err?.message || "Lỗi khi lưu ghi chú.";
          Swal.fire("Lỗi", msg, "error");
        })
        .finally(() => {
          this.loading = false;
        });
    },
    getStatusLabel(status) {
      const map = {
        connected: "Đã nghe máy - Đồng ý thanh toán",
        promise: "Khách hẹn ngày thanh toán",
        no_answer: "Không nghe máy / Thuê bao",
        busy: "Máy bận / Gọi lại sau",
        dispute: "Khiếu nại / Khó đòi",
        other: "Khác",
      };
      return map[status] || status;
    },
    getStatusBadgeClass(status) {
      const map = {
        connected: "badge badge-success",
        promise: "badge badge-primary",
        no_answer: "badge badge-warning",
        busy: "badge badge-secondary",
        dispute: "badge badge-danger",
        other: "badge badge-info",
      };
      return map[status] || "badge badge-light";
    },

    resetForm() {
      this.contract = null;
      this.form = {
        call_status: "connected",
        debt_classification: "normal",
        promised_date: null,
        notes: "",
      };
    },
  },
};
</script>
