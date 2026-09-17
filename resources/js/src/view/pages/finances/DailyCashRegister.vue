<template>
  <div class="daily-cash-register-page">
    <div class="card card-custom gutter-b">
      <div class="card-header border-0 pt-5">
        <div class="card-title">
          <h3 class="card-label font-weight-bolder text-dark">Sổ két tính tiền theo ngày</h3>
        </div>
        <div class="card-toolbar d-flex align-items-center">
          <span v-if="summary.status === 'closed'" class="badge badge-success px-3 py-2 font-weight-bold mr-3">
            ĐÃ CHỐT KÉT ({{ summary.closed_by_name || 'Admin' }} - {{ formatDate(summary.closed_at) }})
          </span>
          <span v-else class="badge badge-warning px-3 py-2 font-weight-bold mr-3">
            ĐANG MỞ - CHƯA CHỐT
          </span>
          <button v-if="summary.status === 'closed' && isAdmin" class="btn btn-sm btn-outline-danger font-weight-bold" @click="handleReopen">
            Mở lại két
          </button>
        </div>
      </div>

      <div class="card-body pt-2">
        <!-- Bộ lọc ngày và cơ sở -->
        <div class="row align-items-center mb-6 bg-light rounded p-4">
          <div class="col-md-4 mb-2 mb-md-0">
            <label class="font-weight-bold text-muted font-size-sm">NGÀY XEM SỔ KÉT:</label>
            <el-date-picker
              v-model="selectedDate"
              type="date"
              format="yyyy-MM-dd"
              value-format="yyyy-MM-dd"
              placeholder="Chọn ngày"
              class="w-100"
              @change="onFilterChange"
            />
          </div>
          <div class="col-md-4 mb-2 mb-md-0">
            <label class="font-weight-bold text-muted font-size-sm">CƠ SỞ / CỬA HÀNG:</label>
            <el-select
              v-model="selectedStoreId"
              placeholder="Tất cả cơ sở"
              class="w-100"
              clearable
              filterable
              @change="onFilterChange"
            >
              <el-option
                v-for="s in stores"
                :key="s.id"
                :label="s.store_name"
                :value="s.id"
              />
            </el-select>
          </div>
          <div class="col-md-4 text-right pt-md-4">
            <button class="btn btn-primary font-weight-bold" @click="fetchSummary" :disabled="loading">
              {{ loading ? 'Đang tải...' : 'Làm mới số liệu' }}
            </button>
          </div>
        </div>

        <div v-if="hasUnclassifiedBank" class="alert alert-warning mb-5" role="alert">
          <strong>Chưa thể chốt két:</strong>
          còn {{ formatCurrency(summary.unclassified_bank_income) }} thu và
          {{ formatCurrency(summary.unclassified_bank_expense) }} chi chuyển khoản chưa xác định tài khoản cá nhân/công ty.
          Hãy phân loại các giao dịch này trước.
        </div>

        <!-- 3 Nhóm nguồn tiền: Tiền mặt, CK Cá nhân, CK Công ty -->
        <div class="row mb-6">
          <!-- Cột 1: Tiền mặt tại két -->
          <div class="col-lg-4 mb-4">
            <div class="card border border-primary h-100">
              <div class="card-header bg-light-primary py-3">
                <h5 class="card-title font-weight-bolder text-primary mb-0">Tiền mặt tại két (TM)</h5>
              </div>
              <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Số dư đầu ngày:</span>
                  <span class="font-weight-bold">{{ formatCurrency(summary.opening_balance) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Thu cọc TM:</span>
                  <span class="text-success font-weight-bold">+{{ formatCurrency(summary.deposit_cash) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Thu tiền thuê TM:</span>
                  <span class="text-success font-weight-bold">+{{ formatCurrency(summary.rental_cash) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Thu gia hạn TM:</span>
                  <span class="text-success font-weight-bold">+{{ formatCurrency(summary.renewal_cash) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Phạt & thu khác TM:</span>
                  <span class="text-success font-weight-bold">+{{ formatCurrency((summary.penalty_cash || 0) + (summary.other_income_cash || 0)) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2 border-top pt-2">
                  <span class="text-muted">Chi hoàn cọc TM:</span>
                  <span class="text-danger font-weight-bold">-{{ formatCurrency(summary.refund_deposit_cash) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Chi phí khác TM:</span>
                  <span class="text-danger font-weight-bold">-{{ formatCurrency(summary.other_expense_cash) }}</span>
                </div>
                <div class="d-flex justify-content-between border-top pt-3 mt-3 bg-light p-2 rounded">
                  <span class="font-weight-bold text-dark">DỰ TÍNH TỒN KÉT:</span>
                  <span class="font-weight-bolder text-primary font-size-h6">{{ formatCurrency(summary.system_cash_balance) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Cột 2: Chuyển khoản cá nhân -->
          <div class="col-lg-4 mb-4">
            <div class="card border border-info h-100">
              <div class="card-header bg-light-info py-3">
                <h5 class="card-title font-weight-bolder text-info mb-0">Chuyển khoản Cá nhân</h5>
              </div>
              <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Thu cọc CK cá nhân:</span>
                  <span class="text-success font-weight-bold">+{{ formatCurrency(summary.deposit_bank_personal) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Thu phí thuê:</span>
                  <span class="text-success font-weight-bold">+{{ formatCurrency(summary.rental_bank_personal) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Thu gia hạn:</span>
                  <span class="text-success font-weight-bold">+{{ formatCurrency(summary.renewal_bank_personal) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Phạt & thu khác:</span>
                  <span class="text-success font-weight-bold">+{{ formatCurrency((summary.penalty_bank_personal || 0) + (summary.other_income_bank_personal || 0)) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2 border-top pt-2">
                  <span class="text-muted">Hoàn cọc CK cá nhân:</span>
                  <span class="text-danger font-weight-bold">-{{ formatCurrency(summary.refund_deposit_bank_personal) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Chi khác CK cá nhân:</span>
                  <span class="text-danger font-weight-bold">-{{ formatCurrency(summary.other_expense_bank_personal) }}</span>
                </div>
                <div class="d-flex justify-content-between border-top pt-3 mt-4 bg-light p-2 rounded">
                  <span class="font-weight-bold text-dark">TỔNG CK CÁ NHÂN:</span>
                  <span class="font-weight-bolder text-info font-size-h6">{{ formatCurrency(summary.total_bank_personal) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Cột 3: Chuyển khoản công ty -->
          <div class="col-lg-4 mb-4">
            <div class="card border border-success h-100">
              <div class="card-header bg-light-success py-3">
                <h5 class="card-title font-weight-bolder text-success mb-0">Chuyển khoản Công ty</h5>
              </div>
              <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Thu cọc CK công ty:</span>
                  <span class="text-success font-weight-bold">+{{ formatCurrency(summary.deposit_bank_company) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Thu phí thuê:</span>
                  <span class="text-success font-weight-bold">+{{ formatCurrency(summary.rental_bank_company) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Thu gia hạn:</span>
                  <span class="text-success font-weight-bold">+{{ formatCurrency(summary.renewal_bank_company) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Phạt & thu khác:</span>
                  <span class="text-success font-weight-bold">+{{ formatCurrency((summary.penalty_bank_company || 0) + (summary.other_income_bank_company || 0)) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2 border-top pt-2">
                  <span class="text-muted">Hoàn cọc CK công ty:</span>
                  <span class="text-danger font-weight-bold">-{{ formatCurrency(summary.refund_deposit_bank_company) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Chi khác CK công ty:</span>
                  <span class="text-danger font-weight-bold">-{{ formatCurrency(summary.other_expense_bank_company) }}</span>
                </div>
                <div class="d-flex justify-content-between border-top pt-3 mt-4 bg-light p-2 rounded">
                  <span class="font-weight-bold text-dark">TỔNG CK CÔNG TY:</span>
                  <span class="font-weight-bolder text-success font-size-h6">{{ formatCurrency(summary.total_bank_company) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="card border mb-6 p-5">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="font-weight-bolder mb-0">Ghi thu/chi phát sinh và đổi tiền</h4>
            <span v-if="!selectedStoreId" class="text-danger">Chọn một cơ sở để thao tác</span>
          </div>
          <div class="row">
            <div class="col-lg-6 border-right">
              <h5>Phiếu thu/chi ngoài hợp đồng</h5>
              <div class="row">
                <div class="col-md-4 form-group"><label>Loại</label><el-select v-model="entryForm.type" class="w-100"><el-option label="Thu" value="in"/><el-option label="Chi" value="out"/></el-select></div>
                <div class="col-md-4 form-group"><label>Số tiền</label><el-input v-model.number="entryForm.amount" type="number"/></div>
                <div class="col-md-4 form-group"><label>Nguồn tiền</label><el-select v-model="entryForm.channel" class="w-100"><el-option label="Tiền mặt" value="cash"/><el-option label="Chuyển khoản" value="bank"/></el-select></div>
                <div class="col-md-12 form-group" v-if="entryForm.channel === 'cash'"><label>Két tiền mặt</label><el-select v-model="entryForm.cash_id" class="w-100"><el-option v-for="cash in cashSources" :key="cash.id" :label="cash.name || `Két #${cash.id}`" :value="cash.id"/></el-select></div>
                <div class="col-md-12 form-group" v-else><label>Tài khoản ngân hàng</label><el-select v-model="entryForm.bank_id" filterable class="w-100"><el-option v-for="bank in bankSources" :key="bank.id" :label="bankLabel(bank)" :value="bank.id"/></el-select></div>
                <div class="col-md-12 form-group"><label>Nội dung</label><el-input v-model="entryForm.description" placeholder="VD: Mua thùng rác cho cửa hàng"/></div>
              </div>
              <button class="btn btn-primary" :disabled="!canSaveEntry || savingEntry" @click="saveEntry">{{ savingEntry ? 'Đang ghi...' : 'Ghi phiếu thu/chi' }}</button>
            </div>
            <div class="col-lg-6 pl-lg-5">
              <h5>Đổi tiền mặt ↔ tài khoản</h5>
              <div class="row">
                <div class="col-md-7 form-group"><label>Chiều chuyển</label><el-select v-model="exchangeForm.direction" class="w-100"><el-option label="Tiền mặt → Ngân hàng" value="cash_to_bank"/><el-option label="Ngân hàng → Tiền mặt" value="bank_to_cash"/></el-select></div>
                <div class="col-md-5 form-group"><label>Số tiền</label><el-input v-model.number="exchangeForm.amount" type="number"/></div>
                <div class="col-md-6 form-group"><label>Két tiền mặt</label><el-select v-model="exchangeForm.cash_id" class="w-100"><el-option v-for="cash in cashSources" :key="cash.id" :label="cash.name || `Két #${cash.id}`" :value="cash.id"/></el-select></div>
                <div class="col-md-6 form-group"><label>Tài khoản ngân hàng</label><el-select v-model="exchangeForm.bank_id" filterable class="w-100"><el-option v-for="bank in bankSources" :key="bank.id" :label="bankLabel(bank)" :value="bank.id"/></el-select></div>
                <div class="col-md-12 form-group"><label>Ghi chú</label><el-input v-model="exchangeForm.description" placeholder="VD: Nộp tiền mặt cuối ca vào tài khoản công ty"/></div>
              </div>
              <button class="btn btn-info" :disabled="!canSaveExchange || savingExchange" @click="saveExchange">{{ savingExchange ? 'Đang xử lý...' : 'Tạo cặp bút toán đổi tiền' }}</button>
            </div>
          </div>
        </div>

        <div class="card border mb-8">
          <div class="card-header py-4"><h4 class="card-title font-weight-bolder mb-0">Sổ giao dịch trong ngày</h4></div>
          <div class="card-body p-0" v-loading="loadingTransactions">
            <div class="table-responsive">
              <table class="table table-bordered table-hover mb-0">
                <thead><tr><th>Thời gian</th><th>Khách hàng / Hợp đồng</th><th>Nội dung</th><th>Nguồn tiền</th><th class="text-right">Số tiền</th><th>Người ghi</th></tr></thead>
                <tbody>
                  <tr v-for="transaction in dailyTransactions" :key="transaction.id">
                    <td>{{ formatDate(transaction.created_at) }}</td>
                    <td><strong>{{ transaction.customer_name || 'Giao dịch nội bộ' }}</strong><div v-if="transaction.order_id" class="text-muted">HĐ {{ transaction.contract_number || `#${transaction.order_id}` }}</div></td>
                    <td>{{ transaction.description || transaction.name }}</td>
                    <td>{{ transaction.channel }}<small class="d-block text-muted">{{ transaction.source_name }}</small></td>
                    <td class="text-right font-weight-bolder" :class="transaction.type === 'in' ? 'text-success' : 'text-danger'">{{ transaction.type === 'in' ? '+' : '-' }}{{ formatCurrency(transaction.value) }}</td>
                    <td>{{ transaction.created_by_name || 'Hệ thống' }}</td>
                  </tr>
                  <tr v-if="!dailyTransactions.length"><td colspan="6" class="text-center text-muted py-5">Chưa có giao dịch trong ngày.</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Khối Kiểm đếm thực tế & Chốt két ngày -->
        <div class="card border mb-8 p-5 bg-light-secondary">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="font-weight-bolder text-dark mb-0">Kiểm đếm tiền mặt & Chốt két ngày</h4>
            <span class="text-muted font-size-sm">Đơn vị: VNĐ</span>
          </div>

          <div v-if="summary.status === 'closed'">
            <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
              <div>
                <strong>Sổ két ngày {{ summary.register_date }} đã được chốt.</strong><br/>
                Người chốt: <strong>{{ summary.closed_by_name || 'Quản trị viên' }}</strong> | Thời gian: {{ formatDate(summary.closed_at) }}<br/>
                Tiền mặt thực đếm: <strong>{{ formatCurrency(summary.actual_cash_counted) }}</strong> | Chênh lệch: <strong>{{ formatCurrency(summary.cash_difference) }}</strong>
                <div v-if="summary.difference_reason" class="mt-1">Lý do chênh lệch: <em>{{ summary.difference_reason }}</em></div>
              </div>
            </div>
          </div>

          <div v-else>
            <div class="row">
              <div class="col-md-4 form-group">
                <label class="font-weight-bold">Tiền mặt thực đếm tại két <span class="text-danger">(*)</span></label>
                <el-input
                  v-model.number="closeForm.actual_cash_counted"
                  type="number"
                  placeholder="Nhập số tiền thực đếm"
                  class="w-100"
                />
              </div>
              <div class="col-md-4 form-group">
                <label class="font-weight-bold">Chênh lệch tự động (Thực tế - Dự tính)</label>
                <div class="p-2 border rounded bg-white text-center font-weight-bolder" :class="differenceClass" style="font-size: 16px;">
                  {{ formatCurrency(calculatedDifference) }}
                  <span v-if="calculatedDifference === 0" class="badge badge-success ml-2">Khớp tiền</span>
                  <span v-else-if="calculatedDifference < 0" class="badge badge-danger ml-2">Thiếu tiền</span>
                  <span v-else class="badge badge-warning ml-2">Thừa tiền</span>
                </div>
              </div>
              <div class="col-md-4 form-group">
                <label class="font-weight-bold">Lý do thừa / thiếu <span v-if="calculatedDifference !== 0" class="text-danger">(*)</span></label>
                <el-input
                  v-model="closeForm.difference_reason"
                  placeholder="Bắt buộc ghi rõ nếu có chênh lệch"
                  class="w-100"
                />
              </div>
            </div>

            <div class="row">
              <div class="col-md-8 form-group">
                <label class="font-weight-bold">Ghi chú chốt két</label>
                <el-input
                  v-model="closeForm.notes"
                  placeholder="Ghi chú thêm về ca trực, người bàn giao két..."
                  class="w-100"
                />
              </div>
              <div class="col-md-4 form-group d-flex align-items-end">
                <button
                  type="button"
                  class="btn btn-success font-weight-bolder w-100 py-3"
                  @click="handleCloseRegister"
                  :disabled="loadingClose || !selectedStoreId || hasUnclassifiedBank"
                >
                  {{ loadingClose ? 'Đang chốt...' : 'Xác nhận Chốt két ngày' }}
                </button>
              </div>
            </div>
            <div v-if="!selectedStoreId" class="text-danger font-size-sm">
              * Vui lòng chọn cụ thể một Cơ sở / Cửa hàng ở trên để thực hiện chốt két.
            </div>
          </div>
        </div>

        <!-- Bảng lịch sử chốt két các ngày trước -->
        <div class="mt-6">
          <h4 class="font-weight-bolder text-dark mb-4">Lịch sử chốt két các ngày gần nhất</h4>
          <div class="table-responsive">
            <table class="table table-bordered table-hover">
              <thead class="thead-light">
                <tr>
                  <th>Ngày</th>
                  <th>Cơ sở</th>
                  <th>Số đơn</th>
                  <th>Tiền hệ thống</th>
                  <th>Tiền thực đếm</th>
                  <th>Chênh lệch</th>
                  <th>Lý do</th>
                  <th>Người chốt</th>
                  <th>Thời gian chốt</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in historyList" :key="item.id">
                  <td class="font-weight-bold">{{ item.register_date }}</td>
                  <td>{{ item.store ? item.store.store_name : 'Toàn hệ thống' }}</td>
                  <td>{{ item.total_orders_count }}</td>
                  <td class="font-weight-bold text-primary">{{ formatCurrency(item.system_cash_balance) }}</td>
                  <td class="font-weight-bold text-success">{{ formatCurrency(item.actual_cash_counted) }}</td>
                  <td>
                    <span v-if="item.cash_difference === 0" class="badge badge-light-success text-success font-weight-bold">Khớp</span>
                    <span v-else-if="item.cash_difference < 0" class="badge badge-light-danger text-danger font-weight-bold">Thiếu {{ formatCurrency(Math.abs(item.cash_difference)) }}</span>
                    <span v-else class="badge badge-light-warning text-warning font-weight-bold">Thừa {{ formatCurrency(item.cash_difference) }}</span>
                  </td>
                  <td>{{ item.difference_reason || '-' }}</td>
                  <td>{{ item.closed_by_user ? item.closed_by_user.name : 'N/A' }}</td>
                  <td>{{ formatDate(item.closed_at) }}</td>
                </tr>
                <tr v-if="!historyList.length">
                  <td colspan="9" class="text-center text-muted py-4">Chưa có lịch sử chốt két nào được ghi nhận.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import ApiService from "@/core/services/api.service";
import { mapGetters } from "vuex";

export default {
  name: "DailyCashRegister",
  data() {
    return {
      selectedDate: new Date().toISOString().slice(0, 10),
      selectedStoreId: null,
      stores: [],
      loading: false,
      loadingClose: false,
      loadingTransactions: false,
      savingEntry: false,
      savingExchange: false,
      dailyTransactions: [],
      cashSources: [],
      bankSources: [],
      entryForm: { type: 'out', amount: null, channel: 'cash', cash_id: null, bank_id: null, description: '' },
      exchangeForm: { direction: 'cash_to_bank', amount: null, cash_id: null, bank_id: null, description: '' },
      summary: {
        status: "open",
        opening_balance: 0,
        total_orders_count: 0,
        deposit_cash: 0,
        rental_cash: 0,
        renewal_cash: 0,
        refund_deposit_cash: 0,
        penalty_cash: 0,
        other_income_cash: 0,
        other_expense_cash: 0,
        deposit_bank_personal: 0,
        rental_bank_personal: 0,
        renewal_bank_personal: 0,
        refund_deposit_bank_personal: 0,
        penalty_bank_personal: 0,
        other_income_bank_personal: 0,
        other_expense_bank_personal: 0,
        deposit_bank_company: 0,
        rental_bank_company: 0,
        renewal_bank_company: 0,
        refund_deposit_bank_company: 0,
        penalty_bank_company: 0,
        other_income_bank_company: 0,
        other_expense_bank_company: 0,
        unclassified_bank_income: 0,
        unclassified_bank_expense: 0,
        system_cash_balance: 0,
        actual_cash_counted: null,
        cash_difference: 0,
        difference_reason: null,
        closed_by_name: null,
        closed_at: null,
      },
      closeForm: {
        actual_cash_counted: null,
        difference_reason: "",
        notes: "",
      },
      historyList: [],
    };
  },
  computed: {
    ...mapGetters(["currentUser"]),
    isAdmin() {
      return this.currentUser && (this.currentUser.role_id === 1 || this.currentUser.is_admin);
    },
    calculatedDifference() {
      if (this.closeForm.actual_cash_counted === null || this.closeForm.actual_cash_counted === undefined || this.closeForm.actual_cash_counted === '') {
        return 0;
      }
      return Number(this.closeForm.actual_cash_counted) - Number(this.summary.system_cash_balance || 0);
    },
    differenceClass() {
      if (this.calculatedDifference === 0) return "text-success";
      if (this.calculatedDifference < 0) return "text-danger";
      return "text-warning";
    },
    hasUnclassifiedBank() {
      return Number(this.summary.unclassified_bank_income || 0) > 0 || Number(this.summary.unclassified_bank_expense || 0) > 0;
    },
    canSaveEntry() {
      const sourceReady = this.entryForm.channel === 'cash' ? this.entryForm.cash_id : this.entryForm.bank_id;
      return this.selectedStoreId && this.summary.status !== 'closed' && Number(this.entryForm.amount) > 0 && sourceReady && this.entryForm.description.trim();
    },
    canSaveExchange() {
      return this.selectedStoreId && this.summary.status !== 'closed' && Number(this.exchangeForm.amount) > 0 && this.exchangeForm.cash_id && this.exchangeForm.bank_id;
    },
  },
  created() {
    this.fetchStores();
    if (this.currentUser && this.currentUser.store_id) {
      this.selectedStoreId = this.currentUser.store_id;
    }
    this.fetchSummary();
    this.fetchHistory();
    this.fetchTransactions();
    this.fetchSources();
  },
  methods: {
    onFilterChange() {
      this.fetchSummary();
      this.fetchTransactions();
      this.fetchSources();
      this.fetchHistory();
    },
    async fetchStores() {
      try {
        const res = await ApiService.query("/api/auth/stores/all", {});
        const stores = res.data.data || res.data || [];
        this.stores = Array.isArray(stores) ? stores : [];
      } catch (err) {
        console.error("Failed to load stores", err);
      }
    },
    async fetchSummary() {
      this.loading = true;
      try {
        const params = {
          date: this.selectedDate,
          store_id: this.selectedStoreId || undefined,
        };
        const res = await ApiService.query("/api/auth/daily-cash-registers/summary", params);
        this.summary = res.data.data || res.data || {};
        if (this.summary.status === 'closed') {
          this.closeForm.actual_cash_counted = this.summary.actual_cash_counted;
          this.closeForm.difference_reason = this.summary.difference_reason || '';
          this.closeForm.notes = this.summary.notes || '';
        } else {
          this.closeForm.actual_cash_counted = this.summary.system_cash_balance;
          this.closeForm.difference_reason = '';
          this.closeForm.notes = '';
        }
      } catch (err) {
        this.$message.error(err.response?.data?.message || "Không thể tải số liệu két ngày");
      } finally {
        this.loading = false;
      }
    },
    async fetchTransactions() {
      if (!this.selectedStoreId) { this.dailyTransactions = []; return; }
      this.loadingTransactions = true;
      try {
        const res = await ApiService.query('/api/auth/daily-cash-registers/transactions', { store_id: this.selectedStoreId, date: this.selectedDate, limit: 300 });
        this.dailyTransactions = res.data.data || res.data || [];
      } catch (err) {
        this.dailyTransactions = [];
      } finally { this.loadingTransactions = false; }
    },
    async fetchSources() {
      if (!this.selectedStoreId) { this.cashSources = []; this.bankSources = []; return; }
      try {
        const res = await ApiService.query('/api/auth/daily-cash-registers/sources', { store_id: this.selectedStoreId });
        const data = res.data.data || res.data || {};
        this.cashSources = data.cash || [];
        this.bankSources = data.banks || [];
        if (!this.entryForm.cash_id && this.cashSources[0]) this.entryForm.cash_id = this.cashSources[0].id;
        if (!this.entryForm.bank_id && this.bankSources[0]) this.entryForm.bank_id = this.bankSources[0].id;
        if (!this.exchangeForm.cash_id && this.cashSources[0]) this.exchangeForm.cash_id = this.cashSources[0].id;
        if (!this.exchangeForm.bank_id && this.bankSources[0]) this.exchangeForm.bank_id = this.bankSources[0].id;
      } catch (_) { this.cashSources = []; this.bankSources = []; }
    },
    async saveEntry() {
      if (!this.canSaveEntry) return;
      this.savingEntry = true;
      try {
        await ApiService.post('/api/auth/daily-cash-registers/entries', { ...this.entryForm, store_id: this.selectedStoreId, date: this.selectedDate });
        this.$message.success('Đã ghi phiếu thu/chi.');
        this.entryForm.amount = null; this.entryForm.description = '';
        this.fetchSummary(); this.fetchTransactions();
      } catch (err) { this.$message.error(err.response?.data?.message || 'Không thể ghi giao dịch.'); }
      finally { this.savingEntry = false; }
    },
    async saveExchange() {
      if (!this.canSaveExchange) return;
      this.savingExchange = true;
      try {
        await ApiService.post('/api/auth/daily-cash-registers/exchanges', { ...this.exchangeForm, store_id: this.selectedStoreId, date: this.selectedDate });
        this.$message.success('Đã tạo hai bút toán đối ứng.');
        this.exchangeForm.amount = null; this.exchangeForm.description = '';
        this.fetchSummary(); this.fetchTransactions();
      } catch (err) { this.$message.error(err.response?.data?.message || 'Không thể đổi tiền.'); }
      finally { this.savingExchange = false; }
    },
    bankLabel(bank) {
      const ownerType = bank.owner_type === 'company' ? 'Công ty' : (bank.owner_type === 'personal' ? 'Cá nhân' : 'Chưa phân loại');
      return `${bank.bank_name} - ${bank.account_number} [${ownerType}]`;
    },
    async handleCloseRegister() {
      if (!this.selectedStoreId) {
        this.$message.warning("Vui lòng chọn cơ sở để chốt két.");
        return;
      }
      if (this.hasUnclassifiedBank) {
        this.$message.error("Còn giao dịch chuyển khoản chưa phân loại cá nhân/công ty.");
        return;
      }
      if (this.calculatedDifference !== 0 && !this.closeForm.difference_reason.trim()) {
        this.$message.error("Có chênh lệch tiền mặt, vui lòng ghi rõ lý do thừa/thiếu.");
        return;
      }

      this.loadingClose = true;
      try {
        await ApiService.post("/api/auth/daily-cash-registers/close", {
          store_id: this.selectedStoreId,
          date: this.selectedDate,
          actual_cash_counted: this.closeForm.actual_cash_counted,
          difference_reason: this.closeForm.difference_reason,
          notes: this.closeForm.notes,
        });
        this.$message.success("Chốt két ngày thành công!");
        this.fetchSummary();
        this.fetchHistory();
      } catch (err) {
        this.$message.error(err.response?.data?.message || "Chốt két thất bại");
      } finally {
        this.loadingClose = false;
      }
    },
    async handleReopen() {
      if (!confirm("Bạn có chắc chắn muốn mở lại sổ két ngày này không?")) return;
      try {
        await ApiService.post("/api/auth/daily-cash-registers/reopen", {
          store_id: this.selectedStoreId,
          date: this.selectedDate,
        });
        this.$message.success("Đã mở lại sổ két!");
        this.fetchSummary();
        this.fetchHistory();
      } catch (err) {
        this.$message.error(err.response?.data?.message || "Mở lại sổ két thất bại");
      }
    },
    async fetchHistory() {
      try {
        const res = await ApiService.query("/api/auth/daily-cash-registers/history", {
          store_id: this.selectedStoreId || undefined,
        });
        const paginated = res.data.data || res.data;
        this.historyList = paginated.data || paginated || [];
      } catch (err) {
        console.error("Failed to load history", err);
      }
    },
    formatCurrency(val) {
      if (val === null || val === undefined || isNaN(val)) return "0 đ";
      return Number(val).toLocaleString("vi-VN") + " đ";
    },
    formatDate(d) {
      if (!d) return "";
      return new Date(d).toLocaleString("vi-VN");
    },
  },
};
</script>

<style scoped>
.daily-cash-register-page {
  font-family: inherit;
}
.card {
  border-radius: 8px;
}
</style>
