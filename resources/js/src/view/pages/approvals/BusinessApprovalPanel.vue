<template>
  <section v-if="can('approval.view')" class="card card-custom p-4 my-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">Đề nghị và phê duyệt</h5>
      <button type="button" class="btn btn-sm btn-light-primary" @click="load">Làm mới</button>
    </div>

    <form v-if="subjectId && availableActions.length" class="row mb-3" @submit.prevent="submit">
      <div class="col-md-3 mb-2">
        <label>Nghiệp vụ</label>
        <select v-model="form.action" class="form-control" required>
          <option value="" disabled>Chọn nghiệp vụ</option>
          <option v-for="action in availableActions" :key="action.value" :value="action.value">{{ action.label }}</option>
        </select>
      </div>
      <div v-if="form.action === 'order_discount'" class="col-md-2 mb-2">
        <label>Số tiền giảm (đ)</label>
        <input v-model.number="form.amount" class="form-control" type="number" min="1" step="1" required>
      </div>
      <template v-if="form.action === 'lease_schedule'">
        <div class="col-md-2 mb-2">
          <label>Số kỳ trả góp</label>
          <input v-model.number="form.period_number" class="form-control" type="number" min="1" step="1" required>
        </div>
        <div class="col-md-2 mb-2">
          <label>Ngày đến hạn mới</label>
          <input v-model="form.new_due_date" class="form-control" type="date" required>
        </div>
      </template>
      <div v-if="['lease_recovery', 'lease_liquidation'].includes(form.action)" class="col-md-4 mb-2">
        <label>Phương án</label>
        <input v-model="form.plan" class="form-control" maxlength="5000" required>
      </div>
      <div class="col-md-4 mb-2">
        <label>Lý do đề nghị</label>
        <input v-model="form.reason" class="form-control" maxlength="2000" required>
      </div>
      <div class="col-md-2 mb-2 d-flex align-items-end">
        <button type="submit" class="btn btn-primary" :disabled="busy">Gửi đề nghị</button>
      </div>
    </form>

    <div v-if="requests.length" class="table-responsive">
      <table class="table table-sm table-bordered">
        <thead><tr><th>Mã</th><th>Đơn/HĐ</th><th>Nghiệp vụ</th><th>Lý do và số tiền</th><th>Người đề nghị</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
        <tbody>
          <tr v-for="item in requests" :key="item.id">
            <td>#{{ item.id }}</td>
            <td>#{{ item.subject_id }}</td>
            <td>{{ label(item.action) }}</td>
            <td>
              {{ item.reason }}
              <span v-if="item.action === 'order_discount'"> · Giảm {{ formatMoney(item.payload && item.payload.amount) }} đ</span>
              <span v-if="item.action === 'order_cancel' && item.status !== 'submitted'"> · Hoàn dự kiến {{ formatMoney(item.payload && item.payload.expected_refund) }} đ</span>
              <span v-if="item.action === 'order_cancel' && item.status === 'settled'"> · Đã hoàn {{ formatMoney(item.payload && item.payload.refund_amount) }} đ</span>
            </td>
            <td>{{ item.requester && item.requester.name }}</td>
            <td>{{ statusLabel(item.status) }}<div v-if="item.decision_note" class="text-muted small">{{ item.decision_note }}</div></td>
            <td>
              <template v-if="item.status === 'submitted' && can('approval.decide') && Number(item.requested_by) !== Number(currentUser && currentUser.id)">
                <button type="button" class="btn btn-sm btn-success mr-1" :disabled="busy" @click="decide(item, true)">Duyệt</button>
                <button type="button" class="btn btn-sm btn-outline-danger" :disabled="busy" @click="decide(item, false)">Từ chối</button>
              </template>
              <button v-if="item.action === 'order_cancel' && item.status === 'approved' && can('order.cancel_settle')"
                type="button" class="btn btn-sm btn-warning" @click="openSettlement(item)">Quyết toán hoàn tiền</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <p v-else class="text-muted mb-0">Chưa có đề nghị.</p>

    <form v-if="settlement" class="border rounded p-3 mt-2" @submit.prevent="settle">
      <strong>Quyết toán hủy #{{ settlement.id }} · Hoàn {{ formatMoney(settlement.payload && settlement.payload.expected_refund) }} đ</strong>
      <div class="row mt-2">
        <div v-if="Number(settlement.payload && settlement.payload.expected_refund) > 0" class="col-md-3 mb-2">
          <label>Kênh hoàn</label>
          <select v-model="refund.channel" class="form-control" required>
            <option value="" disabled>Chọn kênh</option><option value="cash">Tiền mặt</option><option value="bank">Chuyển khoản</option>
          </select>
        </div>
        <div v-if="Number(settlement.payload && settlement.payload.expected_refund) > 0" class="col-md-3 mb-2">
          <label>Két / tài khoản đã chi</label>
          <select v-model.number="refund.source_id" class="form-control" required>
            <option :value="null" disabled>Chọn nguồn</option>
            <option v-for="source in refund.channel === 'cash' ? sources.cash : sources.banks" :key="source.id" :value="source.id">
              {{ source.name || source.bank_name || 'Két #' + source.id }} {{ source.account_number || '' }}
            </option>
          </select>
        </div>
        <div class="col-md-4 mb-2"><label>Ghi chú đã chi / đối soát</label><input v-model="refund.note" class="form-control" maxlength="2000" required></div>
        <div class="col-md-2 mb-2 d-flex align-items-end"><button type="submit" class="btn btn-warning" :disabled="busy">Xác nhận đã hoàn</button></div>
      </div>
    </form>
  </section>
</template>

<script>
import ApiService from '@/core/services/api.service';
import { mapGetters } from 'vuex';
import Swal from 'sweetalert2';

const ACTIONS = {
  order: [
    { value: 'order_cancel', label: 'Hủy đơn', permission: 'order.cancel_request' },
    { value: 'order_discount', label: 'Giảm giá đặc biệt', permission: 'order.discount_request' },
  ],
  lease: [
    { value: 'lease_schedule', label: 'Đổi lịch trả góp', permission: 'lease.schedule_propose' },
    { value: 'lease_recovery', label: 'Thu hồi xe', permission: 'lease.recovery_propose' },
    { value: 'lease_liquidation', label: 'Thanh lý xe nợ xấu', permission: 'lease.liquidation_propose' },
  ],
};

export default {
  name: 'BusinessApprovalPanel',
  props: { subjectType: { type: String, required: true }, subjectId: { type: Number, default: 0 }, storeId: { type: Number, default: 0 } },
  data() {
    return {
      requests: [], busy: false, settlement: null,
      form: { action: '', reason: '', amount: null, period_number: null, new_due_date: '', plan: '' },
      refund: { channel: '', source_id: null, note: '' },
      sources: { cash: [], banks: [] },
    };
  },
  computed: {
    ...mapGetters(['currentUser', 'capabilities']),
    availableActions() { return (ACTIONS[this.subjectType] || []).filter(action => this.can(action.permission)); },
  },
  mounted() { this.load(); },
  watch: { subjectId() { this.settlement = null; this.load(); } },
  methods: {
    can(permission) { return (this.capabilities || []).includes('*') || (this.capabilities || []).includes(permission); },
    label(action) { const found = (ACTIONS[this.subjectType] || []).find(item => item.value === action); return found ? found.label : action; },
    statusLabel(status) { return { submitted: 'Chờ duyệt', approved: 'Đã duyệt', rejected: 'Từ chối', settled: 'Đã quyết toán' }[status] || status; },
    formatMoney(value) { return Number(value || 0).toLocaleString('vi-VN'); },
    errorMessage(error) { return (error.response && error.response.data && error.response.data.message) || (error.data && error.data.message) || 'Thao tác thất bại.'; },
    async load() {
      if (!this.can('approval.view')) return;
      try {
        const filters = { subject_type: this.subjectType, per_page: 100 };
        if (this.subjectId) filters.subject_id = this.subjectId;
        const response = await ApiService.query('/api/auth/business-approvals', filters);
        const page = response.data.data || response.data;
        this.requests = page.data || [];
      } catch (error) { this.$message.error(this.errorMessage(error)); }
    },
    async submit() {
      if (!this.subjectId || this.busy) return;
      this.busy = true;
      try {
        await ApiService.post(`/api/auth/business-approvals/${this.subjectType}/${this.subjectId}`, this.form);
        this.form = { action: '', reason: '', amount: null, period_number: null, new_due_date: '', plan: '' };
        this.$message.success('Đã gửi đề nghị.');
        await this.load();
      } catch (error) { this.$message.error(this.errorMessage(error)); }
      finally { this.busy = false; }
    },
    async decide(item, approve) {
      const prompt = await Swal.fire({ title: approve ? 'Duyệt đề nghị' : 'Từ chối đề nghị', input: 'textarea',
        inputLabel: 'Lý do quyết định', inputValidator: value => !String(value || '').trim() && 'Cần ghi lý do.',
        showCancelButton: true, confirmButtonText: approve ? 'Duyệt' : 'Từ chối', cancelButtonText: 'Đóng' });
      if (!prompt.isConfirmed || this.busy) return;
      this.busy = true;
      try {
        await ApiService.post(`/api/auth/business-approvals/${item.id}/decide`, {
          decision: approve ? 'approve' : 'reject', decision_note: prompt.value,
        });
        this.$message.success('Đã xử lý đề nghị.');
        await this.load();
        this.$emit('changed');
      } catch (error) { this.$message.error(this.errorMessage(error)); }
      finally { this.busy = false; }
    },
    async openSettlement(item) {
      this.settlement = item;
      this.refund = { channel: '', source_id: null, note: '' };
      if (Number(item.payload && item.payload.expected_refund) <= 0) return;
      try {
        const response = await ApiService.query('/api/auth/daily-cash-registers/sources', { store_id: item.store_id || this.storeId });
        this.sources = response.data.data || response.data || { cash: [], banks: [] };
      } catch (error) { this.$message.error(this.errorMessage(error)); }
    },
    async settle() {
      if (!this.settlement || this.busy) return;
      const confirmation = await Swal.fire({ title: 'Xác nhận đã hoàn tiền?',
        text: `Hệ thống sẽ ghi phiếu chi ${this.formatMoney(this.settlement.payload && this.settlement.payload.expected_refund)} đ và đóng đơn.`,
        icon: 'warning', showCancelButton: true, confirmButtonText: 'Đã chi hoàn', cancelButtonText: 'Đóng' });
      if (!confirmation.isConfirmed) return;
      this.busy = true;
      try {
        await ApiService.post(`/api/auth/business-approvals/${this.settlement.id}/settle-cancellation`, this.refund);
        this.settlement = null;
        this.$message.success('Đã quyết toán hủy đơn.');
        await this.load();
        this.$emit('changed');
      } catch (error) { this.$message.error(this.errorMessage(error)); }
      finally { this.busy = false; }
    },
  },
};
</script>
