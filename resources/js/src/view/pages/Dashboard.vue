<template>
  <div class="himoto-dashboard">
    <!-- Role 4 / 403 Forbidden Fallback State -->
    <div v-if="!hasPermission" class="himoto-unauthorized-container">
      <div class="unauthorized-card">
        <div class="unauthorized-icon">🔒</div>
        <h2 class="unauthorized-title">Không có quyền truy cập</h2>
        <p class="unauthorized-desc">
          Tài khoản của bạn (Tư vấn Lead) không có quyền truy cập trang Tổng quan quản trị theo chính sách phân quyền hệ thống.
        </p>
        <div class="unauthorized-actions">
          <router-link to="/leads" class="btn btn-primary">
            <span>📥 Chuyển sang Quản lý Lead</span>
          </router-link>
        </div>
      </div>
    </div>

    <!-- Main Dashboard when permitted -->
    <template v-else>
      <!-- Page Header -->
      <div class="page-header-row">
      <div class="page-title-block">
        <h1 class="page-title">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="page-title-icon">
            <rect width="7" height="9" x="3" y="3" rx="1" />
            <rect width="7" height="5" x="14" y="3" rx="1" />
            <rect width="7" height="9" x="14" y="12" rx="1" />
            <rect width="7" height="5" x="3" y="16" rx="1" />
          </svg>
          Tổng quan vận hành
        </h1>
        <div class="page-subtitle">
          {{ currentDateFormatted }} • <span class="text-brand-red font-weight-bold">{{ currentStoreName }}</span>
        </div>
      </div>

      <div class="page-header-actions">
        <!-- Period Filter Tabs -->
        <div class="segmented-tabs" role="tablist">
          <button
            type="button"
            class="segmented-tab-btn"
            :class="{ active: currentPeriod === 'day' }"
            @click="currentPeriod = 'day'"
          >
            Hôm nay
          </button>
          <button
            type="button"
            class="segmented-tab-btn"
            :class="{ active: currentPeriod === 'month' }"
            @click="currentPeriod = 'month'"
          >
            Tháng này
          </button>
        </div>

        <!-- Quick Action: Create Order -->
        <button
          type="button"
          class="btn btn-primary btn-quick-order"
          @click="$router.push('/car-rental')"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M5 12h14" />
            <path d="M12 5v14" />
          </svg>
          Tạo đơn thuê mới
        </button>
      </div>
    </div>

    <!-- 5 Core HIMOTO KPI Cards -->
    <div class="kpi-grid">
      <!-- KPI 1: Đơn thuê -->
      <div class="kpi-card accent-red">
        <div class="kpi-card-top">
          <span class="kpi-label">Lượt thuê {{ currentPeriod === 'day' ? 'hôm nay' : 'tháng này' }}</span>
          <div class="kpi-icon-wrap bg-red-dim text-brand-red">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z" />
              <path d="M3 6h18" />
              <path d="M16 10a4 4 0 0 1-8 0" />
            </svg>
          </div>
        </div>
        <div class="kpi-value">
          {{ currentPeriod === 'day' ? (reports.total_order_in_day || 0) : (reports.total_order_in_month || 0) }}
        </div>
        <div class="kpi-footer">
          <span class="kpi-subtext">Hợp đồng quá hạn: <strong>{{ reports.total_order_out_date_in_month || 0 }}</strong></span>
        </div>
      </div>

      <!-- KPI 2: Tổng thu thực tế -->
      <div class="kpi-card accent-yellow">
        <div class="kpi-card-top">
          <span class="kpi-label">Tổng thu thực tế</span>
          <div class="kpi-icon-wrap bg-yellow-dim text-brand-yellow-dark">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" x2="12" y1="2" y2="22" />
              <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
            </svg>
          </div>
        </div>
        <div class="kpi-value">
          {{ (currentPeriod === 'day' ? totalInByDay : totalInByMonth) | formatPrice }}
        </div>
        <div class="kpi-footer">
          <span class="kpi-subtext">Chi thực tế: {{ (currentPeriod === 'day' ? reports.total_refund_in_day_new : reports.total_refund_in_month_new) | formatPrice }}</span>
        </div>
      </div>

      <!-- KPI 3: Xe đang cho thuê -->
      <div class="kpi-card accent-green">
        <div class="kpi-card-top">
          <span class="kpi-label">Xe đang cho thuê</span>
          <div class="kpi-icon-wrap bg-green-dim text-success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="18.5" cy="17.5" r="3.5" />
              <circle cx="5.5" cy="17.5" r="3.5" />
              <circle cx="15" cy="5" r="1" />
              <path d="M12 17.5V14l-3-3 4-3 2 3h2" />
            </svg>
          </div>
        </div>
        <div class="kpi-value">
          {{ reports.total_vehicle_using || 0 }} <span class="kpi-unit">/ {{ reports.total_vehicle || 0 }} xe</span>
        </div>
        <div class="kpi-footer">
          <span class="kpi-subtext">Tỷ lệ lấp đầy: <strong>{{ occupancyRate }}%</strong></span>
        </div>
      </div>

      <!-- KPI 4: Xe sẵn sàng -->
      <div class="kpi-card accent-blue">
        <div class="kpi-card-top">
          <span class="kpi-label">Xe sẵn sàng</span>
          <div class="kpi-icon-wrap bg-blue-dim text-info">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
              <polyline points="22 4 12 14.01 9 11.01" />
            </svg>
          </div>
        </div>
        <div class="kpi-value">
          {{ reports.total_vehicle_ready || 0 }}
        </div>
        <div class="kpi-footer">
          <span class="kpi-subtext">Sẵn sàng giao khách ngay</span>
        </div>
      </div>

      <!-- KPI 5: Xe bảo dưỡng / hỏng -->
      <div class="kpi-card accent-purple">
        <div class="kpi-card-top">
          <span class="kpi-label">Xe hỏng / Bảo dưỡng</span>
          <div class="kpi-icon-wrap bg-purple-dim text-danger">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
            </svg>
          </div>
        </div>
        <div class="kpi-value">
          {{ (reports.total_vehicle_broken || 0) + (reports.total_vehicle_repairing || 0) }}
        </div>
        <div class="kpi-footer">
          <span class="kpi-subtext">Cần xử lý kỹ thuật</span>
        </div>
      </div>
    </div>

    <!-- Financial Breakdown Cards Row -->
    <div class="row mt-4 mb-4">
      <div class="col-lg-6 mb-4">
        <div class="himoto-card">
          <div class="himoto-card-header">
            <h3 class="himoto-card-title">
              <span class="card-indicator bg-success"></span>
              Chi tiết các khoản thu ({{ currentPeriod === 'day' ? 'Hôm nay' : 'Tháng này' }})
            </h3>
          </div>
          <div class="himoto-card-body">
            <div class="finance-stats-grid">
              <div class="finance-stat-item">
                <span class="finance-label">Tổng thu cọc</span>
                <span class="finance-value text-brand-red font-weight-bold">
                  {{ (currentPeriod === 'day' ? reports.total_deposit_in_day_new : reports.total_deposit_in_month_new) | formatPrice }}
                </span>
              </div>
              <div class="finance-stat-item">
                <span class="finance-label">Thu phí thuê</span>
                <span class="finance-value font-weight-bold">
                  {{ (currentPeriod === 'day' ? reports.total_rental_fees_in_day_new : reports.total_rental_fees_in_month_new) | formatPrice }}
                </span>
              </div>
              <div class="finance-stat-item">
                <span class="finance-label">Thu gia hạn</span>
                <span class="finance-value font-weight-bold">
                  {{ (currentPeriod === 'day' ? reports.total_renew_in_day_new : reports.total_renew_in_month_new) | formatPrice }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-6 mb-4">
        <div class="himoto-card">
          <div class="himoto-card-header">
            <h3 class="himoto-card-title">
              <span class="card-indicator bg-danger"></span>
              Chi tiết các khoản chi & phạt ({{ currentPeriod === 'day' ? 'Hôm nay' : 'Tháng này' }})
            </h3>
          </div>
          <div class="himoto-card-body">
            <div class="finance-stats-grid">
              <div class="finance-stat-item">
                <span class="finance-label">Cọc cần hoàn</span>
                <span class="finance-value font-weight-bold">
                  {{ (currentPeriod === 'day' ? reports.total_origin_refund_in_day_new : reports.total_origin_refund_in_month_new) | formatPrice }}
                </span>
              </div>
              <div class="finance-stat-item">
                <span class="finance-label">Hoàn do trả sớm</span>
                <span class="finance-value font-weight-bold">
                  {{ Math.abs(currentPeriod === 'day' ? reports.total_money_early_in_day_new : reports.total_money_early_in_month_new || 0) | formatPrice }}
                </span>
              </div>
              <div class="finance-stat-item">
                <span class="finance-label">Tiền phạt muộn</span>
                <span class="finance-value text-danger font-weight-bold">
                  {{ (currentPeriod === 'day' ? reports.total_money_out_date_in_day_new : reports.total_money_out_date_in_month_new) | formatPrice }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Charts & Fleet Distribution Row -->
    <div class="row mb-4">
      <!-- Revenue Trend Chart -->
      <div class="col-lg-8 mb-4">
        <div class="himoto-card h-100">
          <div class="himoto-card-header d-flex justify-content-between align-items-center">
            <h3 class="himoto-card-title">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mr-2 text-brand-red">
                <polyline points="22 7 13.5 15.5 8.5 10.5 2 17" />
                <polyline points="16 7 22 7 22 13" />
              </svg>
              Biểu đồ doanh thu theo ngày trong tháng
            </h3>
            <span class="chart-sum-badge font-weight-bold">
              Tổng tháng: {{ totalMonthRevenueFormatted }}
            </span>
          </div>
          <div class="himoto-card-body">
            <div class="chart-container-wrapper">
              <zingchart :data="chartData" :theme="chartTheme" height="320"></zingchart>
            </div>
          </div>
        </div>
      </div>

      <!-- Fleet Status Distribution Card -->
      <div class="col-lg-4 mb-4">
        <div class="himoto-card h-100">
          <div class="himoto-card-header">
            <h3 class="himoto-card-title">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mr-2 text-brand-red">
                <circle cx="12" cy="12" r="10" />
                <path d="M12 2a10 10 0 0 1 10 10" />
              </svg>
              Cơ cấu đội xe hiện tại
            </h3>
          </div>
          <div class="himoto-card-body d-flex flex-column justify-content-center">
            <div class="fleet-progress-bars">
              <!-- Using -->
              <div class="fleet-stat-row">
                <div class="fleet-stat-header">
                  <span class="fleet-label">Đang cho thuê</span>
                  <span class="fleet-count font-weight-bold text-success">{{ reports.total_vehicle_using || 0 }} xe</span>
                </div>
                <div class="fleet-bar-track">
                  <div class="fleet-bar-fill bg-success" :style="{ width: getVehiclePercent(reports.total_vehicle_using) + '%' }"></div>
                </div>
              </div>

              <!-- Ready -->
              <div class="fleet-stat-row">
                <div class="fleet-stat-header">
                  <span class="fleet-label">Đang sẵn sàng</span>
                  <span class="fleet-count font-weight-bold text-info">{{ reports.total_vehicle_ready || 0 }} xe</span>
                </div>
                <div class="fleet-bar-track">
                  <div class="fleet-bar-fill bg-info" :style="{ width: getVehiclePercent(reports.total_vehicle_ready) + '%' }"></div>
                </div>
              </div>

              <!-- Broken / Maintenance -->
              <div class="fleet-stat-row">
                <div class="fleet-stat-header">
                  <span class="fleet-label">Xe hỏng / Bảo dưỡng</span>
                  <span class="fleet-count font-weight-bold text-danger">{{ (reports.total_vehicle_broken || 0) + (reports.total_vehicle_repairing || 0) }} xe</span>
                </div>
                <div class="fleet-bar-track">
                  <div class="fleet-bar-fill bg-danger" :style="{ width: getVehiclePercent((reports.total_vehicle_broken || 0) + (reports.total_vehicle_repairing || 0)) + '%' }"></div>
                </div>
              </div>
            </div>

            <!-- Quick Summary Box -->
            <div class="fleet-summary-box mt-4">
              <div class="summary-item">
                <span class="s-label">Tổng khách hàng</span>
                <span class="s-val">{{ reports.total_customer || 0 }}</span>
              </div>
              <div class="summary-item border-left pl-3">
                <span class="s-label">Tổng nhân sự</span>
                <span class="s-val">{{ reports.total_staff || 0 }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Lead Management Integration -->
    <div class="himoto-card">
      <div class="himoto-card-header">
        <h3 class="himoto-card-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mr-2 text-brand-red">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
          </svg>
          Danh sách Lead khách hàng mới nhất
        </h3>
      </div>
      <div class="himoto-card-body p-0">
        <lead-index context="dashboard"></lead-index>
      </div>
    </div>
  </template>
</div>
</template>

<script>
import { mapGetters } from "vuex";
import { SET_BREADCRUMB } from "@/core/services/store/breadcrumbs.module";
import { DASHBOARD_REPORT, DASHBOARD_REPORT_CHART } from "@/core/services/store/dashboard.module";
import { STORE_GET_ALL } from "@/core/services/store/store.module";
import { zingChartTheme } from "@/core/config/zingChartTheme";
import LeadIndex from "@/view/pages/lead/LeadIndex";

export default {
  name: "dashboard",
  components: {
    LeadIndex
  },
  data() {
    return {
      hasPermission: true,
      currentPeriod: "day", // 'day' or 'month'
      labels: [],
      values: [],
      chartData: {
        type: "line",
        "scale-x": {
          labels: []
        },
        "scale-y": {
          short: false,
          "short-unit": "M",
          "thousands-separator": ","
        },
        plot: {
          lineColor: "#ed1c24",
          lineWidth: 3,
          marker: {
            backgroundColor: "#ed1c24",
            size: 4
          }
        },
        series: [
          {
            values: []
          }
        ]
      },
      chartTheme: zingChartTheme,
      reports: {},
      storeList: []
    };
  },
  computed: {
    ...mapGetters(["currentUser", "selectedStoreId"]),
    currentStoreName() {
      const storeId = this.selectedStoreId;
      if (!storeId || storeId === "all") return "Toàn hệ thống HIMOTO";
      const found = this.storeList.find((s) => s.id === storeId || String(s.id) === String(storeId));
      return found ? found.store_name : "Chi nhánh";
    },
    currentDateFormatted() {
      const now = new Date();
      const days = ["Chủ Nhật", "Thứ Hai", "Thứ Ba", "Thứ Tư", "Thứ Năm", "Thứ Sáu", "Thứ Bảy"];
      const dayName = days[now.getDay()];
      const d = String(now.getDate()).padStart(2, "0");
      const m = String(now.getMonth() + 1).padStart(2, "0");
      const y = now.getFullYear();
      return `${dayName}, ngày ${d}/${m}/${y}`;
    },
    totalInByDay() {
      return (
        parseInt(this.reports.total_deposit_in_day_new || 0) +
        parseInt(this.reports.total_renew_in_day_new || 0) +
        parseInt(this.reports.total_rental_fees_in_day_new || 0)
      );
    },
    totalInByMonth() {
      return (
        parseInt(this.reports.total_deposit_in_month_new || 0) +
        parseInt(this.reports.total_renew_in_month_new || 0) +
        parseInt(this.reports.total_rental_fees_in_month_new || 0)
      );
    },
    occupancyRate() {
      const total = this.reports.total_vehicle || 0;
      if (total === 0) return 0;
      const using = this.reports.total_vehicle_using || 0;
      return Math.round((using / total) * 100);
    },
    totalMonthRevenueFormatted() {
      const sum = (this.values || []).reduce((acc, v) => acc + (Number(v) || 0), 0);
      return new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(sum);
    }
  },
  watch: {
    selectedStoreId() {
      this.loadDashboardData();
    }
  },
  mounted() {
    this.$store.dispatch(SET_BREADCRUMB, [{ title: "Dashboard" }]);
    if (this.currentUser && this.currentUser.role_id === 4) {
      this.hasPermission = false;
      return;
    }
    this.fetchStores();
    this.loadDashboardData();
  },
  methods: {
    fetchStores() {
      this.$store.dispatch(STORE_GET_ALL, {}).then((res) => {
        this.storeList = res?.data || [];
      });
    },
    loadDashboardData() {
      if (this.currentUser && this.currentUser.role_id === 4) {
        this.hasPermission = false;
        return;
      }
      const params = {};
      if (this.selectedStoreId && this.selectedStoreId !== "all") {
        params.store_id = this.selectedStoreId;
      }
      this.report(params);
      this.reportChart(params);
    },
    report(params) {
      this.$store
        .dispatch(DASHBOARD_REPORT, params)
        .then((res) => {
          this.reports = res.data || {};
          this.hasPermission = true;
        })
        .catch((err) => {
          if (err && (err.status === 403 || err.statusCode === 403 || err.response?.status === 403)) {
            this.hasPermission = false;
          }
        });
    },
    reportChart(params) {
      this.$store
        .dispatch(DASHBOARD_REPORT_CHART, params)
        .then((res) => {
          this.labels = res.data?.labels || [];
          this.values = res.data?.values || [];
          this.hasPermission = true;

        this.chartData = {
          type: "line",
          "scale-x": {
            labels: this.labels,
            guide: {
              lineStyle: "dashed"
            }
          },
          "scale-y": {
            short: false,
            "short-unit": "M",
            "thousands-separator": ","
          },
          plot: {
            lineColor: "#ed1c24",
            lineWidth: 3,
            marker: {
              backgroundColor: "#ed1c24",
              borderColor: "#ffffff",
              borderWidth: 2,
              size: 5
            }
          },
          series: [
            {
              values: this.values,
              text: "Doanh thu"
            }
          ]
        };
      })
      .catch((err) => {
        if (err && (err.status === 403 || err.statusCode === 403 || err.response?.status === 403)) {
          this.hasPermission = false;
        }
      });
    },
    getVehiclePercent(count) {
      const total = this.reports.total_vehicle || 0;
      if (total === 0 || !count) return 0;
      return Math.min(100, Math.round((count / total) * 100));
    }
  }
};
</script>

<style scoped>
.himoto-dashboard {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* Page Header Block */
.page-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
}

.page-title-block {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--text-primary, #17202a);
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0;
}

.page-title-icon {
  color: var(--brand-red, #ed1c24);
}

.page-subtitle {
  font-size: 13px;
  color: var(--text-secondary, #687386);
}

.page-header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

/* Segmented Period Tabs */
.segmented-tabs {
  display: flex;
  background: #eaedf1;
  padding: 3px;
  border-radius: var(--radius-sm, 6px);
  gap: 2px;
}

.segmented-tab-btn {
  border: none;
  background: transparent;
  padding: 6px 14px;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--text-secondary, #687386);
  border-radius: 4px;
  cursor: pointer;
  transition: all 150ms ease;
}

.segmented-tab-btn.active {
  background: #ffffff;
  color: var(--text-primary, #17202a);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

.btn-quick-order {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--brand-red, #ed1c24);
  border-color: var(--brand-red, #ed1c24);
  font-weight: 700;
  font-size: 13px;
  padding: 8px 16px;
  border-radius: var(--radius-sm, 6px);
  color: #ffffff;
}

.btn-quick-order:hover {
  background: #c81018;
  border-color: #c81018;
}

/* 5 KPI Grid */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 16px;
}

@media (max-width: 1200px) {
  .kpi-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 768px) {
  .kpi-grid {
    grid-template-columns: 1fr;
  }
}

.kpi-card {
  background: #ffffff;
  border: 1px solid var(--border-light, #eaedf1);
  border-radius: var(--radius-md, 8px);
  padding: 16px 18px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  position: relative;
  overflow: hidden;
}

.kpi-card::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
}

.kpi-card.accent-red::before {
  background: var(--brand-red, #ed1c24);
}
.kpi-card.accent-yellow::before {
  background: var(--brand-yellow, #fff200);
}
.kpi-card.accent-green::before {
  background: #18a66b;
}
.kpi-card.accent-blue::before {
  background: #0ea5e9;
}
.kpi-card.accent-purple::before {
  background: #8b5cf6;
}

.kpi-card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.kpi-label {
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--text-secondary, #687386);
}

.kpi-icon-wrap {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.bg-red-dim {
  background: rgba(237, 28, 36, 0.1);
}
.bg-yellow-dim {
  background: rgba(255, 242, 0, 0.2);
}
.bg-green-dim {
  background: rgba(24, 166, 107, 0.1);
}
.bg-blue-dim {
  background: rgba(14, 165, 233, 0.1);
}
.bg-purple-dim {
  background: rgba(139, 92, 246, 0.1);
}

.kpi-value {
  font-size: 1.45rem;
  font-weight: 800;
  color: var(--text-primary, #17202a);
}

.kpi-unit {
  font-size: 0.9rem;
  font-weight: 500;
  color: var(--text-secondary, #687386);
}

.kpi-footer {
  font-size: 11.5px;
  color: var(--text-secondary, #687386);
}

/* HIMOTO Cards */
.himoto-card {
  background: #ffffff;
  border: 1px solid var(--border-light, #eaedf1);
  border-radius: var(--radius-md, 8px);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

.himoto-card-header {
  padding: 16px 20px;
  border-bottom: 1px solid var(--border-light, #eaedf1);
  display: flex;
  align-items: center;
}

.himoto-card-title {
  font-size: 14.5px;
  font-weight: 700;
  color: var(--text-primary, #17202a);
  margin: 0;
  display: flex;
  align-items: center;
}

.card-indicator {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-right: 8px;
}

.himoto-card-body {
  padding: 20px;
}

.finance-stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

@media (max-width: 576px) {
  .finance-stats-grid {
    grid-template-columns: 1fr;
  }
}

.finance-stat-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.finance-label {
  font-size: 12px;
  color: var(--text-secondary, #687386);
}

.finance-value {
  font-size: 15px;
  color: var(--text-primary, #17202a);
}

.chart-sum-badge {
  font-size: 12px;
  background: rgba(237, 28, 36, 0.08);
  color: var(--brand-red, #ed1c24);
  padding: 4px 10px;
  border-radius: 9999px;
}

.chart-container-wrapper {
  min-height: 320px;
}

/* Fleet distribution */
.fleet-progress-bars {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.fleet-stat-header {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  margin-bottom: 6px;
}

.fleet-bar-track {
  height: 8px;
  background: #eaedf1;
  border-radius: 4px;
  overflow: hidden;
}

.fleet-bar-fill {
  height: 100%;
  border-radius: 4px;
  transition: width 300ms ease;
}

.fleet-summary-box {
  display: flex;
  align-items: center;
  justify-content: space-around;
  background: #f8fafc;
  border-radius: 6px;
  padding: 12px;
}

.summary-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}

.s-label {
  font-size: 11px;
  color: var(--text-secondary, #687386);
}

.s-val {
  font-size: 16px;
  font-weight: 800;
  color: var(--text-primary, #17202a);
}

.himoto-unauthorized-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 60vh;
  padding: 32px 16px;
}

.unauthorized-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 48px 32px;
  text-align: center;
  max-width: 480px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

.unauthorized-icon {
  font-size: 48px;
  margin-bottom: 16px;
}

.unauthorized-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 12px;
}

.unauthorized-desc {
  font-size: 0.925rem;
  color: #64748b;
  line-height: 1.6;
  margin-bottom: 24px;
}

.unauthorized-actions .btn {
  padding: 10px 24px;
  font-weight: 600;
  font-size: 0.95rem;
}
</style>
