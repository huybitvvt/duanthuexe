<template>
  <div>
    <div
      v-if="mobileOpen"
      class="sidebar-mobile-backdrop"
      @click="$emit('close-mobile-sidebar')"
    ></div>

    <aside
      id="himotoSidebar"
      class="himoto-sidebar"
      :class="{ collapsed, 'mobile-open': mobileOpen }"
      aria-label="Điều hướng chính"
    >
      <div class="sidebar-brand">
        <router-link
          to="/dashboard"
          class="brand-link"
          aria-label="Về Tổng quan"
          @click.native="onNavClick"
        >
          <span v-if="collapsed" class="brand-mark" aria-hidden="true">H</span>
          <template v-else>
            <img
              src="/images/branding/logo-himoto-pdf.png"
              alt="HIMOTO"
              class="brand-logo-img"
            />
            <span class="brand-subtext">Hệ thống quản lý xe</span>
          </template>
        </router-link>

        <button
          v-if="mobileOpen"
          type="button"
          class="btn-close-sidebar-mobile"
          @click="$emit('close-mobile-sidebar')"
          aria-label="Đóng menu"
        >
          Đóng
        </button>
      </div>

      <nav class="sidebar-nav-container">
        <div class="nav-groups-wrapper">
          <section
            v-for="group in visibleNavGroups"
            :key="group.title"
            class="nav-group"
            :aria-label="group.title"
          >
            <div v-if="!collapsed" class="nav-group-heading">{{ group.title }}</div>
            <router-link
              v-for="item in group.items"
              :key="item.to"
              :to="item.to"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive(item.to) }"
              :title="collapsed ? item.label : null"
              :aria-label="item.label"
              @click.native="onNavClick"
            >
              <i :class="['nav-item-icon', item.icon]" aria-hidden="true"></i>
              <span v-if="!collapsed" class="nav-item-label">{{ item.label }}</span>
              <span v-if="!collapsed && item.badge === 'rentals' && activeRentalCount" class="nav-badge-pill">
                {{ activeRentalCount }}
              </span>
            </router-link>
          </section>
        </div>
      </nav>

      <div v-if="!collapsed" class="sidebar-operator-footer">
        <div class="operator-badge">
          <div class="op-dot" aria-hidden="true"></div>
          <div class="op-info">
            <span class="op-name">{{ currentUser ? currentUser.name : "Vận hành" }}</span>
            <span class="op-status">Trực tuyến</span>
          </div>
        </div>
      </div>
    </aside>
  </div>
</template>

<script>
import { mapGetters } from "vuex";

const ADMIN_NAV_GROUPS = [
  {
    title: "Tổng quan",
    items: [
      { to: "/dashboard", label: "Dashboard", icon: "fas fa-chart-line" },
      { to: "/car-rental", label: "Đơn thuê xe", icon: "fas fa-file-contract", badge: "rentals" },
      { to: "/lease-to-own", label: "Thuê sở hữu", icon: "fas fa-key" },
      { to: "/warehouses", label: "Kho xe & Điều chuyển", icon: "fas fa-warehouse" },
      { to: "/customer-reminders", label: "Nhắc nợ khách", icon: "fas fa-bell" },
      { to: "/leads", label: "Nguồn Lead", icon: "fas fa-bullseye" }
    ]
  },
  {
    title: "Quản lý đội xe",
    items: [
      { to: "/vehicles", label: "Danh sách xe", icon: "fas fa-motorcycle" },
      { to: "/maintenance-schedule", label: "Lịch hẹn bảo dưỡng", icon: "fas fa-calendar-check" },
      { to: "/maintenance-log", label: "Lịch sử bảo dưỡng", icon: "fas fa-history" },
      { to: "/maintenance-rule", label: "Tần suất bảo dưỡng", icon: "fas fa-stopwatch" },
      { to: "/maintenance-type", label: "Các loại bảo dưỡng", icon: "fas fa-tools" }
    ]
  },
  {
    title: "Khách & cửa hàng",
    items: [
      { to: "/customers", label: "Khách hàng", icon: "fas fa-users" },
      { to: "/stores", label: "Cửa hàng", icon: "fas fa-store" },
      { to: "/hr/duty-schedule", label: "Lịch trực cơ sở", icon: "fas fa-user-clock" }
    ]
  },
  {
    title: "Tài chính & thu chi",
    items: [
      { to: "/finances/daily-cash-register", label: "Sổ két ngày", icon: "fas fa-clipboard-list" },
      { to: "/banks", label: "Tài khoản ngân hàng", icon: "fas fa-university" },
      { to: "/cash", label: "Quỹ tiền mặt", icon: "fas fa-money-bill-wave" },
      { to: "/transactions", label: "Lịch sử thu chi", icon: "fas fa-exchange-alt" },
      { to: "/receipt", label: "Phiếu thu chi", icon: "fas fa-file-invoice-dollar" },
      { to: "/accounting", label: "Kế toán, VAT & tài sản", icon: "fas fa-calculator" }
    ]
  },
  {
    title: "Báo cáo & thống kê",
    items: [
      { to: "/report/detail-report", label: "Báo cáo tổng quan", icon: "fas fa-chart-bar" },
      { to: "/report/kpi", label: "KPI & chiến dịch", icon: "fas fa-bullhorn" },
      { to: "/report/vehicle-revenue", label: "Doanh thu theo xe", icon: "fas fa-chart-pie" }
    ]
  },
  {
    title: "Hệ thống",
    items: [
      { to: "/pricing", label: "Bảng giá thuê", icon: "fas fa-tags" },
      { to: "/user", label: "Quản lý người dùng", icon: "fas fa-user-shield" }
    ]
  }
];

export default {
  name: "HimotoSidebar",
  props: {
    collapsed: { type: Boolean, default: false },
    mobileOpen: { type: Boolean, default: false }
  },
  data() {
    return {
      activeRentalCount: null,
      navGroups: ADMIN_NAV_GROUPS
    };
  },
  computed: {
    ...mapGetters(["currentUser"]),
    visibleNavGroups() {
      if (this.currentUser && Number(this.currentUser.role_id) === 4) {
        return [
          {
            title: "Tư vấn Lead",
            items: [{ to: "/leads", label: "Lead khách hàng", icon: "fas fa-address-card" }]
          }
        ];
      }
      return this.navGroups;
    }
  },
  methods: {
    isRouteActive(routePath) {
      return this.$route.path.startsWith(routePath);
    },
    onNavClick() {
      if (this.mobileOpen) this.$emit("close-mobile-sidebar");
    }
  }
};
</script>

<style scoped>
.himoto-sidebar {
  position: fixed;
  inset: 0 auto 0 0;
  z-index: 1000;
  display: flex;
  flex-direction: column;
  width: var(--sidebar-width, 248px);
  min-width: var(--sidebar-width, 248px);
  overflow: hidden;
  background: #f7f8fa;
  border-right: 1px solid #d9dee7;
  box-shadow: 5px 0 20px rgba(23, 32, 42, 0.08);
  transition: width 250ms ease, min-width 250ms ease, transform 250ms ease;
}

.sidebar-brand {
  flex: 0 0 auto;
  min-height: 76px;
  display: flex;
  align-items: center;
  background: #d71920;
  padding: 12px 14px 10px;
}

.brand-link { display: block; width: 100%; text-decoration: none; }
.brand-logo-img {
  display: block;
  width: min(190px, 100%);
  max-height: 42px;
  object-fit: contain;
  object-position: left center;
}
.brand-subtext {
  display: block;
  margin-top: 5px;
  color: rgba(255, 255, 255, 0.92);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.35px;
}
.brand-mark {
  display: grid;
  place-items: center;
  width: 42px;
  height: 42px;
  margin: 0 auto;
  border: 2px solid rgba(255, 255, 255, 0.9);
  border-radius: 10px;
  color: #fff;
  font-size: 25px;
  font-weight: 900;
  font-style: italic;
}

.himoto-sidebar.collapsed {
  width: var(--sidebar-collapsed-width, 72px);
  min-width: var(--sidebar-collapsed-width, 72px);
}
.himoto-sidebar.collapsed .sidebar-brand { min-height: 70px; padding: 10px 8px; }

.sidebar-nav-container {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 12px 9px 18px;
  scrollbar-color: #aeb5c0 transparent;
  scrollbar-width: thin;
}
.sidebar-nav-container::-webkit-scrollbar { width: 6px; }
.sidebar-nav-container::-webkit-scrollbar-track { background: transparent; }
.sidebar-nav-container::-webkit-scrollbar-thumb { background: #aeb5c0; border-radius: 999px; }

.nav-groups-wrapper { display: flex; flex-direction: column; gap: 10px; }
.nav-group {
  margin: 0;
  padding: 8px 6px;
  background: #fff;
  border: 1px solid #e3e7ed;
  border-radius: 10px;
  box-shadow: 0 1px 3px rgba(23, 32, 42, 0.04);
}
.nav-group-heading {
  margin: 0 4px 6px;
  padding: 4px 6px 8px;
  border-bottom: 1px solid #e8ebef;
  color: #8f1b21;
  font-size: 12.5px;
  font-weight: 800;
  line-height: 1.25;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}
.sidebar-nav-item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 40px;
  padding: 9px 10px;
  border: 1px solid transparent;
  border-radius: 8px;
  margin: 2px 0;
  color: #344054;
  font-size: 14.5px;
  font-weight: 500;
  line-height: 1.3;
  text-decoration: none;
  transition: background-color 150ms ease, border-color 150ms ease, color 150ms ease, box-shadow 150ms ease;
}
.sidebar-nav-item:hover,
.sidebar-nav-item:focus-visible {
  background-color: #fff2f3;
  border-color: #efc9cc;
  color: #9f1118;
  text-decoration: none;
  outline: none;
}
.sidebar-nav-item.active {
  background-color: #fff0f1;
  border-color: #e9b9bd;
  color: #a90f17;
  font-weight: 700;
  box-shadow: inset 3px 0 0 #c81018, 0 2px 6px rgba(200, 16, 24, 0.08);
}
.nav-item-icon {
  flex: 0 0 20px;
  width: 20px;
  color: #667085;
  font-size: 16px;
  text-align: center;
}
.sidebar-nav-item:hover .nav-item-icon,
.sidebar-nav-item.active .nav-item-icon { color: #b30f18; }
.nav-item-label {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: inherit;
}
.nav-badge-pill {
  margin-left: auto;
  padding: 2px 7px;
  border-radius: 999px;
  background: #f8d7da;
  color: #9f1118;
  font-size: 11px;
  font-weight: 700;
}

.himoto-sidebar.collapsed .sidebar-nav-container { padding: 10px 7px 16px; }
.himoto-sidebar.collapsed .nav-groups-wrapper { gap: 5px; }
.himoto-sidebar.collapsed .nav-group {
  padding: 2px;
  border: 0;
  background: transparent;
  box-shadow: none;
}
.himoto-sidebar.collapsed .sidebar-nav-item {
  justify-content: center;
  min-height: 46px;
  padding: 11px 6px;
}
.himoto-sidebar.collapsed .nav-item-icon { flex-basis: 24px; width: 24px; font-size: 19px; }

.sidebar-operator-footer { padding: 12px 14px; border-top: 1px solid #e0e4ea; background: #fff; }
.operator-badge { display: flex; align-items: center; gap: 9px; }
.op-dot { width: 9px; height: 9px; border-radius: 50%; background: #18a66b; }
.op-info { display: flex; min-width: 0; flex-direction: column; }
.op-name { overflow: hidden; color: #243043; font-size: 13px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.op-status { color: #667085; font-size: 11px; }
.sidebar-mobile-backdrop { display: none; }

@media (max-width: 768px) {
  .himoto-sidebar,
  .himoto-sidebar.collapsed {
    width: min(84vw, 300px);
    min-width: min(84vw, 300px);
    transform: translateX(-105%);
    box-shadow: none;
  }
  .himoto-sidebar.mobile-open {
    transform: translateX(0);
    box-shadow: 10px 0 30px rgba(0, 0, 0, 0.28);
  }
  .sidebar-mobile-backdrop {
    position: fixed;
    inset: 0;
    z-index: 999;
    display: block;
    background: rgba(23, 32, 42, 0.5);
  }
}
</style>
