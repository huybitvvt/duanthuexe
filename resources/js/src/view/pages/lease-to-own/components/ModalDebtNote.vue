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
          <div><strong>Tổng dư nợ còn lại:</strong> <span class="text-danger font-weight-bold">{{ contract.outstanding_balance | formatPrice }}</span></div>
        </div>

        <!-- Mở rộng thông tin người thân để đôn đốc / sự cố -->
        <div class="mt-3 pt-2 border-top border-danger-subtle">
          <div class="d-flex justify-content-between align-items-center cursor-pointer" @click="showRelatives = !showRelatives">
            <span class="font-weight-bolder text-dark">
              <i class="flaticon-users mr-1 text-primary"></i> Thông tin người thân (để liên hệ khi xe gặp sự cố hoặc nhắc nợ):
              <span class="badge badge-secondary ml-1">{{ relativesList.length }} người thân</span>
            </span>
            <span class="btn btn-xs btn-outline-primary font-weight-bold">
              {{ showRelatives ? 'Thu gọn ▲' : 'Xem chi tiết ▼' }}
            </span>
          </div>

          <div v-if="showRelatives" class="mt-2">
            <div v-if="relativesList.length === 0" class="text-muted font-italic font-size-xs bg-white p-2 rounded border">
              Chưa lưu thông tin người thân trong hồ sơ khách hàng.
            </div>
            <div v-else class="row">
              <div v-for="(rel, idx) in relativesList" :key="idx" class="col-md-6 mb-2">
                <div class="bg-white p-2 rounded border shadow-sm h-100">
                  <div class="font-weight-bold text-dark font-size-sm">
                    <span class="badge badge-light-primary mr-1">#{{ idx + 1 }}</span>
                    {{ rel.name || '(Chưa nhập tên)' }}
                    <span v-if="rel.relationship" class="text-muted font-size-xs font-weight-normal">({{ rel.relationship }})</span>
                  </div>
                  <div class="mt-1 d-flex align-items-center justify-content-between">
                    <span class="font-size-xs text-dark">SĐT: <strong>{{ rel.phone || 'N/A' }}</strong></span>
                    <a v-if="rel.phone" :href="'tel:' + rel.phone" class="btn btn-xs btn-outline-success font-weight-bold">
                      Gọi ngay
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
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
              <label class="font-weight-bold">Hành động đôn đốc <span class="text-danger">*</span></label>
              <el-select v-model="form.call_status" class="w-100" placeholder="Chọn hành động" @change="handleActionChange">
                <el-option label="Đã liên hệ" value="contacted" />
                <el-option label="Hứa thanh toán" value="promise" />
                <el-option label="Ko nghe máy" value="no_answer" />
                <el-option label="Mất liên lạc" value="lost_contact" />
                <el-option label="Không hợp tác" value="uncooperative" />
                <el-option label="Đã thanh toán" value="paid" />
                <el-option label="Cần thu hồi xe" value="recall_vehicle" />
                <el-option label="Cần check xe" value="check_vehicle" />
                <el-option label="Đi thu tiền" value="collect_money" />
              </el-select>
            </div>
            <div class="col-md-6 form-group">
              <label class="font-weight-bold">Số tiền đã thanh toán hôm nay (VNĐ)</label>
              <el-input-number
                v-model="form.paid_today"
                :min="0"
                :step="100000"
                class="w-100"
                placeholder="Nhập số tiền đã thanh toán..."
                controls-position="right"
              />
              <span v-if="form.paid_today > 0" class="form-text text-success font-weight-bold font-size-xs">
                Đã nhận hôm nay: {{ form.paid_today | formatPrice }}
              </span>
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
            <div class="col-md-6 form-group">
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
                placeholder="Khách hẹn mấy giờ, lý do chậm trễ, yêu cầu hỗ trợ, thỏa thuận thanh toán..."
              />
            </div>
          </div>
          <div class="d-flex justify-content-between align-items-center mt-3">
            <button
              v-if="form.paid_today > 0 || form.call_status === 'paid'"
              type="button"
              class="btn btn-sm btn-outline-success font-weight-bold"
              @click="forwardToPayment"
            >
              Chuyển sang lập phiếu thu tiền
            </button>
            <span v-else></span>
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
          <div v-if="!contract || (!contract.debt_notes || !contract.debt_notes.length) && (!contract.contact_logs || !contract.contact_logs.length)" class="text-center text-muted py-3">
            Chưa có ghi chú đôn đốc nào cho hợp đồng này.
          </div>
          <div v-else class="timeline timeline-3">
            <div
              v-for="item in ((contract.debt_notes && contract.debt_notes.length) ? contract.debt_notes : (contract.contact_logs || []))"
              :key="item.id"
              class="timeline-item d-flex align-items-start mb-3 pb-2 border-bottom"
            >
              <div class="timeline-badge mr-3">
                <span class="badge badge-light-primary px-2 py-1 font-weight-bold">
                  {{ item.action ? getStatusLabel(item.action) : 'Nhắc nợ' }}
                </span>
              </div>
              <div class="timeline-content flex-grow-1">
                <div class="d-flex justify-content-between align-items-center">
                  <span class="font-weight-bold text-dark">{{ getClassificationLabel(item.debt_classification) }}</span>
                  <span class="text-muted font-size-xs">{{ (item.created_at || item.contact_date) | formatDateTime }}</span>
                </div>
                <div v-if="item.appointment_date" class="text-primary font-size-xs my-1 font-weight-bold">
                  Hẹn thanh toán: {{ item.appointment_date | formatDate }}
                </div>
                <div class="text-dark-75 font-size-sm mt-1 bg-light rounded p-2">
                  {{ item.note_content || item.note }}
                </div>
                <div v-if="item.created_by_user || item.user" class="text-muted font-size-xs text-right mt-1">
                  Nhân viên: {{ (item.created_by_user ? item.created_by_user.name : (item.user ? item.user.name : '')) }}
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
import ApiService from "@/core/services/api.service";
import { LEASE_ADD_NOTE, LEASE_GET_SHOW } from "@/core/services/store/lease.module";
import Swal from "sweetalert2";

export default {
  name: "ModalDebtNote",
  data() {
    return {
      visible: false,
      loading: false,
      contract: null,
      showRelatives: true,
      form: {
        call_status: "contacted",
        debt_classification: "normal",
        promised_date: null,
        paid_today: 0,
        notes: "",
      },
    };
  },
  computed: {
    relativesList() {
      if (!this.contract || !this.contract.customer) return [];
      let rels = this.contract.customer.relatives;
      if (typeof rels === "string") {
        try {
          rels = JSON.parse(rels);
        } catch (e) {
          rels = [];
        }
      }
      if (!Array.isArray(rels)) return [];
      return rels.filter((r) => r && (r.name || r.phone || r.relationship));
    },
  },
  methods: {
    open(contract, defaultAction = null) {
      this.contract = contract;
      this.visible = true;
      this.showRelatives = true;
      if (defaultAction) {
        this.form.call_status = defaultAction;
        this.handleActionChange(defaultAction);
      }
      this.refreshContract();
    },
    handleActionChange(action) {
      const mapClass = {
        contacted: "normal",
        promise: "reminder",
        no_answer: "reminder",
        lost_contact: "warning",
        uncooperative: "warning",
        paid: "normal",
        recall_vehicle: "bad_debt",
        check_vehicle: "warning",
        collect_money: "bad_debt",
      };
      if (mapClass[action]) {
        this.form.debt_classification = mapClass[action];
      }
    },
    refreshContract() {
      if (!this.contract?.id || this.contract.reminder_id) return;
      this.$store
        .dispatch(LEASE_GET_SHOW, this.contract.id)
        .then((res) => {
          this.contract = Object.assign({}, this.contract, res?.data || {});
        })
        .catch(() => {});
    },
    handleSubmit() {
      if (!this.form.notes || !this.form.notes.trim()) {
        Swal.fire("Lỗi", "Vui lòng nhập nội dung trao đổi / ghi chú đôn đốc.", "warning");
        return;
      }

      this.loading = true;
      let noteText = "[" + this.getStatusLabel(this.form.call_status) + "] ";
      if (this.form.paid_today && Number(this.form.paid_today) > 0) {
        const formatted = Number(this.form.paid_today).toLocaleString("vi-VN");
        noteText += "[Đã thanh toán hôm nay: " + formatted + "đ] ";
      }
      noteText += this.form.notes.trim();

      const savePromise = this.contract.reminder_id
        ? ApiService.post(`/api/auth/customer-reminders/${this.contract.reminder_id}/contact`, {
            action: this.form.call_status,
            paid_amount: this.form.paid_today || 0,
            appointment_date: this.form.promised_date,
            note: this.form.notes.trim(),
          })
        : this.$store.dispatch(LEASE_ADD_NOTE, {
            contractId: this.contract.id,
            payload: {
              call_status: this.form.call_status,
              appointment_date: this.form.promised_date,
              debt_classification: this.form.debt_classification,
              note_content: noteText,
            },
          });

      savePromise
        .then((res) => {
          Swal.fire("Thành công", res?.data?.message || res?.message || "Đã lưu ghi chú đôn đốc.", "success");
          this.form.notes = "";
          this.form.promised_date = null;
          this.form.paid_today = 0;
          this.refreshContract();
          this.$emit("success");
          this.visible = false;
        })
        .catch((err) => {
          const msg = err?.response?.data?.message || err?.data?.message || err?.message || "Lỗi khi lưu ghi chú.";
          Swal.fire("Lỗi", msg, "error");
        })
        .finally(() => {
          this.loading = false;
        });
    },
    forwardToPayment() {
      const amount = this.form.paid_today || null;
      this.visible = false;
      this.$emit("open-payment", { contract: this.contract, amount });
    },
    getStatusLabel(status) {
      const map = {
        contacted: "Đã liên hệ",
        promise: "Hứa thanh toán",
        no_answer: "Ko nghe máy",
        lost_contact: "Mất liên lạc",
        uncooperative: "Không hợp tác",
        paid: "Đã thanh toán",
        recall_vehicle: "Cần thu hồi xe",
        check_vehicle: "Cần check xe",
        collect_money: "Đi thu tiền",
      };
      return map[status] || status;
    },
    getClassificationLabel(classification) {
      const map = {
        normal: "Bình thường",
        reminder: "Cần nhắc",
        warning: "Cảnh báo",
        bad_debt: "Nợ xấu",
      };
      return map[classification] || classification || "Đôn đốc";
    },
    resetForm() {
      this.contract = null;
      this.showRelatives = true;
      this.form = {
        call_status: "contacted",
        debt_classification: "normal",
        promised_date: null,
        paid_today: 0,
        notes: "",
      };
    },
  },
};
</script>
