<template>
  <div class="kpi-report-page">
    <div class="card card-custom gutter-b">
      <div class="card-header border-0 pt-5">
        <div class="card-title">
          <div>
            <h3 class="card-label font-weight-bolder text-dark mb-1">KPI Lead & Chiến dịch</h3>
            <p class="text-muted mb-0">Đo nguồn lead, tỷ lệ chuyển đổi và hiệu quả theo nhân viên.</p>
          </div>
        </div>
        <div class="card-toolbar">
          <button type="button" class="btn btn-outline-primary font-weight-bold" :disabled="!report" @click="exportCsv">
            Xuất CSV
          </button>
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
            <button type="button" class="btn btn-primary font-weight-bold px-6" :disabled="loading" @click="fetchReport">
              {{ loading ? 'Đang tổng hợp...' : 'Xem báo cáo' }}
            </button>
          </div>
        </div>

        <div v-if="schemaMessage" class="alert alert-warning" role="alert">{{ schemaMessage }}</div>
        <div v-else-if="errorMessage" class="alert alert-danger" role="alert">{{ errorMessage }}</div>

        <template v-if="report && !schemaMessage">
          <div class="kpi-summary-grid mb-6">
            <div class="kpi-card"><span>Tổng lead</span><strong>{{ report.summary.total_leads }}</strong></div>
            <div class="kpi-card"><span>Đã chuyển thành đơn</span><strong>{{ report.summary.converted_leads }}</strong></div>
            <div class="kpi-card"><span>Lead đang chờ</span><strong>{{ report.summary.pending_leads }}</strong></div>
            <div class="kpi-card kpi-card-accent"><span>Tỷ lệ chuyển đổi</span><strong>{{ report.summary.conversion_rate }}%</strong></div>
          </div>

          <section class="report-section mb-6">
            <div class="report-section-header">
              <h4>Xu hướng theo ngày</h4>
              <span>{{ report.period.start_date }} → {{ report.period.end_date }}</span>
            </div>
            <div v-if="report.daily.length" class="daily-bars" aria-label="Biểu đồ số lead theo ngày">
              <div v-for="day in report.daily" :key="day.date" class="daily-bar-row">
                <span class="daily-date">{{ day.date }}</span>
                <div class="daily-track"><span :style="{ width: dailyBarWidth(day.total) }"></span></div>
                <strong>{{ day.total }}</strong>
                <small>{{ day.converted }} chuyển đổi ({{ day.conversion_rate }}%)</small>
              </div>
            </div>
            <div v-else class="empty-report">Chưa có lead trong khoảng thời gian đã chọn.</div>
          </section>

          <div class="row">
            <div class="col-xl-6 mb-6">
              <report-table title="Theo nguồn" :rows="report.by_source" />
            </div>
            <div class="col-xl-6 mb-6">
              <report-table title="Theo chiến dịch" :rows="report.by_campaign" />
            </div>
            <div class="col-xl-6 mb-6">
              <report-table title="Theo nhân viên phụ trách" :rows="report.by_staff" />
            </div>
            <div class="col-xl-6 mb-6">
              <report-table title="Theo trạng thái" :rows="statusRows" :status-mode="true" />
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script>
import ApiService from "@/core/services/api.service";
import ReportTable from "./KpiReportTable.vue";

function localDate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

export default {
  name: "KpiReport",
  components: { ReportTable },
  data() {
    const today = new Date();
    const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
    return {
      filters: {
        start_date: localDate(monthStart),
        end_date: localDate(today),
        store_id: null,
      },
      stores: [],
      report: null,
      loading: false,
      errorMessage: "",
      schemaMessage: "",
    };
  },
  computed: {
    statusRows() {
      return this.report ? this.report.by_status || [] : [];
    },
    maxDailyTotal() {
      if (!this.report || !this.report.daily.length) return 1;
      return Math.max(...this.report.daily.map((item) => Number(item.total) || 0), 1);
    },
  },
  created() {
    this.fetchStores();
    this.fetchReport();
  },
  methods: {
    dailyBarWidth(value) {
      return `${Math.max(2, (Number(value) / this.maxDailyTotal) * 100)}%`;
    },
    async fetchStores() {
      try {
        const response = await ApiService.query("/api/auth/stores/all", {});
        const payload = response.data.data || [];
        this.stores = Array.isArray(payload) ? payload : [];
      } catch (error) {
        this.stores = [];
      }
    },
    async fetchReport() {
      this.loading = true;
      this.errorMessage = "";
      this.schemaMessage = "";
      try {
        const response = await ApiService.query("/api/auth/report/kpi", {
          ...this.filters,
          store_id: this.filters.store_id || undefined,
        });
        this.report = response.data.data || null;
      } catch (error) {
        this.report = null;
        const payload = error.response?.data || {};
        if (payload.code === "SCHEMA_NOT_READY") {
          this.schemaMessage = "Báo cáo KPI đang khóa an toàn. Cần chạy migration 000009 trên staging để bổ sung trường nguồn/chiến dịch.";
        } else if (payload.errors && typeof payload.errors === "object") {
          const firstKey = Object.keys(payload.errors)[0];
          const firstErr = Array.isArray(payload.errors[firstKey]) ? payload.errors[firstKey][0] : payload.errors[firstKey];
          this.errorMessage = firstErr || payload.message || "Không thể tải báo cáo KPI.";
        } else {
          this.errorMessage = payload.message || "Không thể tải báo cáo KPI.";
        }
      } finally {
        this.loading = false;
      }
    },
    csvCell(value) {
      return `"${String(value == null ? "" : value).replace(/"/g, '""')}"`;
    },
    exportCsv() {
      if (!this.report) return;
      const rows = [["Nhóm", "Giá trị", "Tổng lead", "Chuyển đổi", "Tỷ lệ (%)"]];
      [
        ["Nguồn", this.report.by_source],
        ["Chiến dịch", this.report.by_campaign],
        ["Nhân viên", this.report.by_staff],
      ].forEach(([group, items]) => {
        items.forEach((item) => rows.push([group, item.label, item.total, item.converted, item.conversion_rate]));
      });
      const csv = "\ufeff" + rows.map((row) => row.map(this.csvCell).join(",")).join("\r\n");
      const url = URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8" }));
      const link = document.createElement("a");
      link.href = url;
      link.download = `kpi-lead-${this.filters.start_date}-${this.filters.end_date}.csv`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    },
  },
};
</script>

<style scoped>
.kpi-summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
.kpi-card { min-height: 112px; padding: 18px; border: 1px solid #e2e7ef; border-radius: 12px; background: #fff; box-shadow: 0 4px 14px rgba(28, 39, 60, .05); }
.kpi-card span { display: block; margin-bottom: 12px; color: #667085; font-weight: 600; }
.kpi-card strong { color: #1f2937; font-size: 28px; }
.kpi-card-accent { border-color: #efc9cc; background: #fff7f7; }
.kpi-card-accent strong { color: #a90f17; }
.report-section { overflow: hidden; border: 1px solid #e2e7ef; border-radius: 12px; background: #fff; }
.report-section-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 58px; padding: 14px 18px; border-bottom: 1px solid #edf0f4; background: #f8fafc; }
.report-section-header h4 { margin: 0; font-size: 17px; }
.report-section-header span { color: #667085; }
.daily-bars { padding: 14px 18px; }
.daily-bar-row { display: grid; grid-template-columns: 94px minmax(100px, 1fr) 45px 190px; align-items: center; gap: 12px; min-height: 34px; }
.daily-track { height: 10px; overflow: hidden; border-radius: 999px; background: #edf1f5; }
.daily-track span { display: block; height: 100%; border-radius: inherit; background: #2f80c9; }
.daily-bar-row small { color: #667085; }
.empty-report { padding: 36px; color: #667085; text-align: center; }
@media (max-width: 992px) { .kpi-summary-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 576px) {
  .kpi-summary-grid { grid-template-columns: 1fr; }
  .daily-bar-row { grid-template-columns: 82px minmax(70px, 1fr) 35px; }
  .daily-bar-row small { grid-column: 2 / 4; }
}
</style>
