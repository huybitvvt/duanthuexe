<template>
  <div class="accounting-page">
    <div class="card card-custom gutter-b">
      <div class="card-header border-0 pt-5">
        <div class="card-title">
          <div>
            <h3 class="card-label font-weight-bolder text-dark mb-1">Kế toán · VAT · Tài sản</h3>
            <p class="text-muted mb-0">Sổ chứng từ VAT và theo dõi khấu hao tài sản nội bộ.</p>
          </div>
        </div>
        <div v-if="isAdmin" class="card-toolbar">
          <button type="button" class="btn btn-outline-primary font-weight-bold mr-2" @click="openVatDialog">Thêm chứng từ VAT</button>
          <button type="button" class="btn btn-primary font-weight-bold" @click="openAssetDialog">Thêm tài sản</button>
        </div>
      </div>

      <div class="card-body pt-3">
        <div class="row align-items-end bg-light rounded p-4 mb-6">
          <div class="col-md-3 mb-3 mb-md-0">
            <label class="font-weight-bold text-muted font-size-sm">TỪ NGÀY</label>
            <el-date-picker v-model="filters.start_date" type="date" format="yyyy-MM-dd" value-format="yyyy-MM-dd" class="w-100" />
          </div>
          <div class="col-md-3 mb-3 mb-md-0">
            <label class="font-weight-bold text-muted font-size-sm">ĐẾN NGÀY</label>
            <el-date-picker v-model="filters.end_date" type="date" format="yyyy-MM-dd" value-format="yyyy-MM-dd" class="w-100" />
          </div>
          <div class="col-md-3 mb-3 mb-md-0">
            <label class="font-weight-bold text-muted font-size-sm">CƠ SỞ</label>
            <el-select v-model="filters.store_id" placeholder="Toàn hệ thống" clearable filterable class="w-100">
              <el-option v-for="store in stores" :key="store.id" :label="store.store_name" :value="store.id" />
            </el-select>
          </div>
          <div class="col-md-3 text-right">
            <button type="button" class="btn btn-primary font-weight-bold" :disabled="loading" @click="fetchDashboard">
              {{ loading ? 'Đang tải...' : 'Xem báo cáo' }}
            </button>
          </div>
        </div>

        <div v-if="schemaMessage" class="alert alert-warning" role="alert">{{ schemaMessage }}</div>
        <div v-else-if="errorMessage" class="alert alert-danger" role="alert">{{ errorMessage }}</div>

        <template v-if="dashboard && !schemaMessage">
          <div class="accounting-summary mb-6">
            <div><span>VAT đầu vào</span><strong>{{ money(dashboard.summary.input_vat) }}</strong></div>
            <div><span>VAT đầu ra</span><strong>{{ money(dashboard.summary.output_vat) }}</strong></div>
            <div :class="{ negative: dashboard.summary.vat_payable_estimate < 0 }"><span>VAT phải nộp (ước tính)</span><strong>{{ money(dashboard.summary.vat_payable_estimate) }}</strong></div>
            <div><span>Nguyên giá tài sản</span><strong>{{ money(dashboard.summary.asset_purchase_cost) }}</strong></div>
            <div><span>Khấu hao/tháng</span><strong>{{ money(dashboard.summary.monthly_depreciation) }}</strong></div>
          </div>

          <ul class="nav nav-pills font-weight-bold mb-4">
            <li class="nav-item mr-2"><button type="button" class="nav-link btn" :class="activeTab === 'vat' ? 'btn-primary active text-white' : 'btn-light'" @click="activeTab = 'vat'">Sổ VAT</button></li>
            <li class="nav-item"><button type="button" class="nav-link btn" :class="activeTab === 'assets' ? 'btn-primary active text-white' : 'btn-light'" @click="activeTab = 'assets'">Tài sản</button></li>
          </ul>

          <div v-show="activeTab === 'vat'" v-drag-scroll class="table-responsive" role="region" aria-label="Sổ chứng từ VAT">
            <table class="table table-bordered table-hover accounting-table">
              <thead class="thead-light"><tr><th>Ngày</th><th>Số hóa đơn</th><th>Loại</th><th>Đối tác</th><th>MST</th><th class="text-right">Trước thuế</th><th class="text-right">Thuế suất</th><th class="text-right">VAT</th><th class="text-right">Tổng</th><th>Thanh toán</th><th v-if="isAdmin">Thao tác</th></tr></thead>
              <tbody>
                <tr v-for="item in dashboard.vat_documents" :key="item.id">
                  <td>{{ item.invoice_date }}</td>
                  <td class="font-weight-bold">{{ item.invoice_number }}</td>
                  <td>{{ item.document_type === 'input' ? 'Đầu vào' : 'Đầu ra' }}</td>
                  <td>{{ item.counterparty_name }}</td>
                  <td>{{ item.tax_code || '-' }}</td>
                  <td class="text-right">{{ money(item.amount_before_tax) }}</td>
                  <td class="text-right">{{ item.vat_rate }}%</td>
                  <td class="text-right">{{ money(item.vat_amount) }}</td>
                  <td class="text-right font-weight-bold">{{ money(item.total_amount) }}</td>
                  <td>{{ paymentLabel(item.payment_status) }}</td>
                  <td v-if="isAdmin"><button type="button" class="btn btn-sm btn-outline-danger" @click="removeVat(item)">Xóa</button></td>
                </tr>
                <tr v-if="!dashboard.vat_documents.length"><td :colspan="isAdmin ? 11 : 10" class="text-center text-muted py-5">Chưa có chứng từ VAT trong kỳ.</td></tr>
              </tbody>
            </table>
          </div>

          <div v-show="activeTab === 'assets'" v-drag-scroll class="table-responsive" role="region" aria-label="Danh sách tài sản">
            <table class="table table-bordered table-hover accounting-table">
              <thead class="thead-light"><tr><th>Mã tài sản</th><th>Tên</th><th>Nhóm</th><th>Cơ sở</th><th>Ngày mua</th><th class="text-right">Nguyên giá</th><th class="text-right">Giá trị còn lại</th><th class="text-right">Số tháng KH</th><th class="text-right">Khấu hao/tháng</th><th>Trạng thái</th><th v-if="isAdmin">Thao tác</th></tr></thead>
              <tbody>
                <tr v-for="item in dashboard.assets" :key="item.id">
                  <td class="font-weight-bold">{{ item.asset_code }}</td>
                  <td>{{ item.name }}</td>
                  <td>{{ item.category || '-' }}</td>
                  <td>{{ item.store ? item.store.store_name : 'Toàn hệ thống' }}</td>
                  <td>{{ item.purchase_date || '-' }}</td>
                  <td class="text-right">{{ money(item.purchase_cost) }}</td>
                  <td class="text-right">{{ money(item.residual_value) }}</td>
                  <td class="text-right">{{ item.depreciation_months }}</td>
                  <td class="text-right font-weight-bold">{{ money(item.monthly_depreciation) }}</td>
                  <td>{{ assetStatusLabel(item.status) }}</td>
                  <td v-if="isAdmin"><button type="button" class="btn btn-sm btn-outline-danger" @click="removeAsset(item)">Xóa</button></td>
                </tr>
                <tr v-if="!dashboard.assets.length"><td :colspan="isAdmin ? 11 : 10" class="text-center text-muted py-5">Chưa có tài sản.</td></tr>
              </tbody>
            </table>
          </div>
        </template>
      </div>
    </div>

    <el-dialog :visible.sync="showVatDialog" title="Thêm chứng từ VAT" width="680px" :close-on-click-modal="false">
      <div class="row">
        <div class="col-md-6 form-group"><label>Loại chứng từ *</label><el-select v-model="vatForm.document_type" class="w-100"><el-option label="VAT đầu vào" value="input" /><el-option label="VAT đầu ra" value="output" /></el-select></div>
        <div class="col-md-6 form-group"><label>Số hóa đơn *</label><el-input v-model="vatForm.invoice_number" /></div>
        <div class="col-md-6 form-group"><label>Ngày hóa đơn *</label><el-date-picker v-model="vatForm.invoice_date" type="date" format="yyyy-MM-dd" value-format="yyyy-MM-dd" class="w-100" /></div>
        <div class="col-md-6 form-group"><label>Đối tác *</label><el-input v-model="vatForm.counterparty_name" /></div>
        <div class="col-md-6 form-group"><label>Mã số thuế</label><el-input v-model="vatForm.tax_code" /></div>
        <div class="col-md-6 form-group"><label>Cơ sở</label><el-select v-model="vatForm.store_id" clearable class="w-100"><el-option v-for="store in stores" :key="store.id" :label="store.store_name" :value="store.id" /></el-select></div>
        <div class="col-md-6 form-group"><label>Tiền trước thuế *</label><el-input-number v-model="vatForm.amount_before_tax" :min="0" :controls="false" class="w-100" /></div>
        <div class="col-md-6 form-group"><label>Thuế suất (%) *</label><el-select v-model="vatForm.vat_rate" class="w-100"><el-option v-for="rate in [0, 5, 8, 10]" :key="rate" :label="rate + '%'" :value="rate" /></el-select></div>
        <div class="col-md-6 form-group"><label>Thanh toán</label><el-select v-model="vatForm.payment_status" class="w-100"><el-option label="Chưa thanh toán" value="unpaid" /><el-option label="Thanh toán một phần" value="partial" /><el-option label="Đã thanh toán" value="paid" /></el-select></div>
        <div class="col-md-12 form-group"><label>Ghi chú</label><el-input v-model="vatForm.notes" type="textarea" :rows="2" /></div>
      </div>
      <div slot="footer"><button type="button" class="btn btn-light mr-2" @click="showVatDialog = false">Hủy</button><button type="button" class="btn btn-primary" :disabled="saving" @click="saveVat">{{ saving ? 'Đang lưu...' : 'Lưu chứng từ' }}</button></div>
    </el-dialog>

    <el-dialog :visible.sync="showAssetDialog" title="Thêm tài sản" width="680px" :close-on-click-modal="false">
      <div class="row">
        <div class="col-md-6 form-group"><label>Mã tài sản *</label><el-input v-model="assetForm.asset_code" /></div>
        <div class="col-md-6 form-group"><label>Tên tài sản *</label><el-input v-model="assetForm.name" /></div>
        <div class="col-md-6 form-group"><label>Nhóm tài sản</label><el-input v-model="assetForm.category" /></div>
        <div class="col-md-6 form-group"><label>Cơ sở</label><el-select v-model="assetForm.store_id" clearable class="w-100"><el-option v-for="store in stores" :key="store.id" :label="store.store_name" :value="store.id" /></el-select></div>
        <div class="col-md-6 form-group"><label>Ngày mua</label><el-date-picker v-model="assetForm.purchase_date" type="date" format="yyyy-MM-dd" value-format="yyyy-MM-dd" class="w-100" /></div>
        <div class="col-md-6 form-group"><label>Nguyên giá *</label><el-input-number v-model="assetForm.purchase_cost" :min="0" :controls="false" class="w-100" /></div>
        <div class="col-md-6 form-group"><label>Giá trị còn lại</label><el-input-number v-model="assetForm.residual_value" :min="0" :controls="false" class="w-100" /></div>
        <div class="col-md-6 form-group"><label>Thời gian khấu hao (tháng)</label><el-input-number v-model="assetForm.depreciation_months" :min="0" :max="1200" class="w-100" /></div>
        <div class="col-md-6 form-group"><label>Trạng thái</label><el-select v-model="assetForm.status" class="w-100"><el-option label="Đang sử dụng" value="active" /><el-option label="Bảo trì" value="maintenance" /><el-option label="Đã thanh lý" value="disposed" /></el-select></div>
        <div class="col-md-12 form-group"><label>Ghi chú</label><el-input v-model="assetForm.notes" type="textarea" :rows="2" /></div>
      </div>
      <div slot="footer"><button type="button" class="btn btn-light mr-2" @click="showAssetDialog = false">Hủy</button><button type="button" class="btn btn-primary" :disabled="saving" @click="saveAsset">{{ saving ? 'Đang lưu...' : 'Lưu tài sản' }}</button></div>
    </el-dialog>
  </div>
</template>

<script>
import { mapGetters } from "vuex";
import ApiService from "@/core/services/api.service";

function localDate(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
}

export default {
  name: "AccountingDashboard",
  data() {
    const today = new Date();
    return {
      activeTab: "vat",
      filters: { start_date: localDate(new Date(today.getFullYear(), today.getMonth(), 1)), end_date: localDate(today), store_id: null },
      stores: [], dashboard: null, loading: false, saving: false, errorMessage: "", schemaMessage: "",
      showVatDialog: false, showAssetDialog: false,
      vatForm: {}, assetForm: {},
    };
  },
  computed: {
    ...mapGetters(["currentUser"]),
    isAdmin() { return this.currentUser && Number(this.currentUser.role_id) === 1; },
  },
  created() { this.fetchStores(); this.fetchDashboard(); },
  methods: {
    money(value) { return new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND", maximumFractionDigits: 0 }).format(Number(value) || 0); },
    paymentLabel(value) { return { unpaid: "Chưa thanh toán", partial: "Một phần", paid: "Đã thanh toán" }[value] || value; },
    assetStatusLabel(value) { return { active: "Đang sử dụng", maintenance: "Bảo trì", disposed: "Đã thanh lý" }[value] || value; },
    async fetchStores() { try { const res = await ApiService.query("/api/auth/stores/all", {}); this.stores = Array.isArray(res.data.data) ? res.data.data : []; } catch (error) { this.stores = []; } },
    async fetchDashboard() {
      this.loading = true; this.errorMessage = ""; this.schemaMessage = "";
      try { const res = await ApiService.query("/api/auth/accounting", { ...this.filters, store_id: this.filters.store_id || undefined }); this.dashboard = res.data.data; }
      catch (error) { this.dashboard = null; const payload = error.response?.data || {}; if (payload.code === "SCHEMA_NOT_READY") this.schemaMessage = "Phân hệ kế toán đang khóa an toàn. Cần chạy migration 000010 trên staging trước khi sử dụng."; else this.errorMessage = payload.message || "Không thể tải dữ liệu kế toán."; }
      finally { this.loading = false; }
    },
    openVatDialog() { this.vatForm = { document_type: "input", invoice_number: "", invoice_date: localDate(new Date()), counterparty_name: "", tax_code: "", amount_before_tax: 0, vat_rate: 10, payment_status: "unpaid", store_id: null, notes: "" }; this.showVatDialog = true; },
    openAssetDialog() { this.assetForm = { asset_code: "", name: "", category: "", store_id: null, purchase_date: localDate(new Date()), purchase_cost: 0, residual_value: 0, depreciation_months: 36, status: "active", notes: "" }; this.showAssetDialog = true; },
    async saveVat() {
      if (!this.vatForm.invoice_number || !this.vatForm.counterparty_name) {
        return this.$message.warning("Vui lòng nhập số hóa đơn và đối tác");
      }
      this.saving = true;
      try {
        await ApiService.post("/api/auth/accounting/vat-documents", this.vatForm);
        this.$message.success("Đã lưu chứng từ VAT");
        this.showVatDialog = false;
        await this.fetchDashboard();
      } catch (error) {
        this.$message.error(error.response?.data?.message || "Không thể lưu chứng từ");
      } finally {
        this.saving = false;
      }
    },
    async saveAsset() {
      if (!this.assetForm.asset_code || !this.assetForm.name) {
        return this.$message.warning("Vui lòng nhập mã và tên tài sản");
      }
      this.saving = true;
      try {
        await ApiService.post("/api/auth/accounting/assets", this.assetForm);
        this.$message.success("Đã lưu tài sản");
        this.showAssetDialog = false;
        await this.fetchDashboard();
      } catch (error) {
        this.$message.error(error.response?.data?.message || "Không thể lưu tài sản");
      } finally {
        this.saving = false;
      }
    },
    async removeVat(item) { try { await this.$confirm(`Xóa chứng từ ${item.invoice_number}?`, "Xác nhận", { type: "warning" }); await ApiService.delete(`/api/auth/accounting/vat-documents/${item.id}`); await this.fetchDashboard(); } catch (error) { if (error !== "cancel") this.$message.error(error.response?.data?.message || "Không thể xóa chứng từ"); } },
    async removeAsset(item) { try { await this.$confirm(`Xóa tài sản ${item.asset_code}?`, "Xác nhận", { type: "warning" }); await ApiService.delete(`/api/auth/accounting/assets/${item.id}`); await this.fetchDashboard(); } catch (error) { if (error !== "cancel") this.$message.error(error.response?.data?.message || "Không thể xóa tài sản"); } },
  },
};
</script>

<style scoped>
.accounting-summary { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; }
.accounting-summary > div { min-height: 105px; padding: 16px; border: 1px solid #e2e7ef; border-radius: 11px; background: #fff; }
.accounting-summary span { display: block; min-height: 38px; color: #667085; font-weight: 600; }
.accounting-summary strong { color: #1f2937; font-size: 20px; }
.accounting-summary .negative strong { color: #087f5b; }
.accounting-table { min-width: 1250px; }
@media (max-width: 1200px) { .accounting-summary { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 768px) { .accounting-summary { grid-template-columns: 1fr; } }
</style>
