<template>
  <div>
    <!-- Mobile Backdrop -->
    <div
      v-if="mobileOpen"
      class="sidebar-mobile-backdrop"
      @click="$emit('close-mobile-sidebar')"
    ></div>

    <aside
      class="himoto-sidebar"
      :class="{
        collapsed: collapsed,
        'mobile-open': mobileOpen
      }"
      id="himotoSidebar"
    >
      <!-- Brand Header -->
      <div class="sidebar-brand">
        <router-link to="/dashboard" class="brand-link" @click.native="onNavClick">
          <img
            src="/images/branding/logo-himoto-pdf.png"
            alt="HIMOTO Logo"
            class="brand-logo-img"
          />
          <span v-if="!collapsed" class="brand-subtext">Hệ thống quản lý xe</span>
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

      <!-- Navigation Links -->
      <nav class="sidebar-nav-container">
        <!-- Lead Consultant View (Role 4) -->
        <div v-if="currentUser && currentUser.role_id === 4" class="nav-group">
          <div v-if="!collapsed" class="nav-group-heading">TƯ VẤN LEAD</div>
          <router-link
            to="/leads"
            class="sidebar-nav-item"
            :class="{ active: isRouteActive('/leads') }"
            @click.native="onNavClick"
          >
              <span v-if="!collapsed" class="nav-item-label">Lead khách hàng</span>
          </router-link>
        </div>

        <!-- Full Admin / Staff View -->
        <div v-else class="nav-groups-wrapper">
          <!-- GROUP 1: TỔNG QUAN -->
          <div class="nav-group">
            <div v-if="!collapsed" class="nav-group-heading">TỔNG QUAN</div>
            <router-link
              to="/dashboard"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/dashboard') }"
              @click.native="onNavClick"
            >
              <span v-if="!collapsed" class="nav-item-label">Dashboard</span>
            </router-link>
            <router-link
              to="/car-rental"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/car-rental') }"
              @click.native="onNavClick"
            >
              <span v-if="!collapsed" class="nav-item-label">Đơn thuê xe</span>
              <span v-if="!collapsed && activeRentalCount" class="nav-badge-pill">
                {{ activeRentalCount }}
              </span>
            </router-link>
            <router-link
              to="/lease-to-own"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/lease-to-own') }"
              @click.native="onNavClick"
            >
              <span v-if="!collapsed" class="nav-item-label">Thuê sở hữu</span>
            </router-link>
            <router-link
              to="/warehouses"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/warehouses') }"
              @click.native="onNavClick"
            >
              <span v-if="!collapsed" class="nav-item-label">Kho xe & Điều chuyển</span>
            </router-link>
            <router-link
              to="/customer-reminders"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/customer-reminders') }"
              @click.native="onNavClick"
            >
              <span v-if="!collapsed" class="nav-item-label">Nhắc nợ khách</span>
            </router-link>
            <router-link
              to="/leads"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/leads') }"
              @click.native="onNavClick"
            >
              <span v-if="!collapsed" class="nav-item-label">Nguồn Lead</span>
            </router-link>
          </div>

          <!-- GROUP 2: QUẢN LÝ ĐỘI XE -->
          <div class="nav-group">
            <div v-if="!collapsed" class="nav-group-heading">QUẢN LÝ ĐỘI XE</div>
            <router-link
              to="/vehicles"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/vehicles') }"
              @click.native="onNavClick"
            >
              <span v-if="!collapsed" class="nav-item-label">Danh sách xe</span>
            </router-link>
            <router-link
              to="/maintenance-schedule"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/maintenance-schedule') }"
              @click.native="onNavClick"
            >
              <span v-if="!collapsed" class="nav-item-label">Lịch hẹn bảo dưỡng</span>
            </router-link>
            <router-link
              to="/maintenance-log"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/maintenance-log') }"
              @click.native="onNavClick"
            >
              <span v-if="!collapsed" class="nav-item-label">Lịch sử bảo dưỡng</span>
            </router-link>
            <router-link
              to="/maintenance-rule"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/maintenance-rule') }"
              @click.native="onNavClick"
            >
              <span v-if="!collapsed" class="nav-item-label">Tần suất bảo dưỡng</span>
            </router-link>
            <router-link
              to="/maintenance-type"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/maintenance-type') }"
              @click.native="onNavClick"
            >
              <span v-if="!collapsed" class="nav-item-label">Các loại bảo dưỡng</span>
            </router-link>
          </div>

          <!-- GROUP 3: KHÁCH HÀNG & CỬA HÀNG -->
          <div class="nav-group">
            <div v-if="!collapsed" class="nav-group-heading">KHÁCH & CỬA HÀNG</div>
            <router-link
              to="/customers"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/customers') }"
              @click.native="onNavClick"
            >
              <span v-if="!collapsed" class="nav-item-label">Khách hàng</span>
            </router-link>
            <router-link
              to="/stores"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/stores') }"
              @click.native="onNavClick"
            >
              <span v-if="!collapsed" class="nav-item-label">Cửa hàng</span>
            </router-link>
            <router-link
              to="/hr/duty-schedule"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/hr/duty-schedule') }"
              @click.native="onNavClick"
            >
              <span v-if="!collapsed" class="nav-item-label">Lịch trực cơ sở</span>
            </router-link>
          </div>

          <!-- GROUP 4: TÀI CHÍNH & NGUỒN TIỀN -->
          <div class="nav-group">
            <div v-if="!collapsed" class="nav-group-heading">TÀI CHÍNH & THU CHI</div>
            <router-link
              to="/finances/daily-cash-register"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/finances/daily-cash-register') }"
              @click.native="onNavClick"
            >
              <span v-if="!collapsed" class="nav-item-label">Sổ két ngày</span>
            </router-link>
            <router-link
              to="/banks"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/banks') }"
              @click.native="onNavClick"
            >
              <span v-if="!collapsed" class="nav-item-label">Tài khoản ngân hàng</span>
            </router-link>
            <router-link
              to="/cash"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/cash') }"
              @click.native="onNavClick"
            >
              <span v-if="!collapsed" class="nav-item-label">Quỹ tiền mặt</span>
            </router-link>
            <router-link
              to="/transactions"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/transactions') }"
              @click.native="onNavClick"
            >
              <span v-if="!collapsed" class="nav-item-label">Lịch sử thu chi</span>
            </router-link>
            <router-link
              to="/receipt"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/receipt') }"
              @click.native="onNavClick"
            >
              <span v-if="!collapsed" class="nav-item-label">Phiếu thu chi</span>
            </router-link>
          </div>

          <!-- GROUP 5: BÁO CÁO -->
          <div class="nav-group">
            <div v-if="!collapsed" class="nav-group-heading">BÁO CÁO & THỐNG KÊ</div>
            <router-link
              to="/report/detail-report"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/report/detail-report') }"
              @click.native="onNavClick"
            >
              <span v-if="!collapsed" class="nav-item-label">Báo cáo tổng quan</span>
            </router-link>
            <router-link
              to="/report/vehicle-revenue"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/report/vehicle-revenue') }"
              @click.native="onNavClick"
            >
              <span v-if="!collapsed" class="nav-item-label">Doanh thu theo xe</span>
            </router-link>
          </div>

          <!-- GROUP 6: CÀI ĐẶT -->
          <div class="nav-group">
            <div v-if="!collapsed" class="nav-group-heading">HỆ THỐNG</div>
            <router-link
              to="/pricing"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/pricing') }"
              @click.native="onNavClick"
            >
              <span v-if="!collapsed" class="nav-item-label">Bảng giá thuê</span>
            </router-link>
            <router-link
              to="/user"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/user') }"
              @click.native="onNavClick"
            >
              <span v-if="!collapsed" class="nav-item-label">Quản lý người dùng</span>
            </router-link>
          </div>
        </div>
      </nav>

      <!-- Sidebar Operator Footer -->
      <div v-if="!collapsed" class="sidebar-operator-footer">
        <div class="operator-badge">
          <div class="op-dot"></div>
          <div class="op-info">
            <span class="op-name">{{ currentUser ? currentUser.name : 'Vận hành' }}</span>
            <span class="op-status">Trực tuyến</span>
          </div>
        </div>
      </div>
    </aside>
  </div>
</template>

<script>
import { mapGetters } from "vuex";

export default {
  name: "HimotoSidebar",
  props: {
    collapsed: {
      type: Boolean,
      default: false
    },
    mobileOpen: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      activeRentalCount: null
    };
  },
  computed: {
    ...mapGetters(["currentUser"])
  },
  methods: {
    isRouteActive(routePath) {
      return this.$route.path.startsWith(routePath);
    },
    onNavClick() {
      if (this.mobileOpen) {
        this.$emit("close-mobile-sidebar");
      }
    }
  }
};
</script>

<style scoped>
.himoto-sidebar {
  position: fixed;
  top: 0;
  bottom: 0;
  left: 0;
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
  background: #ed1c24;
  padding: 16px 14px 12px;
}

.brand-link {
  display: block;
}

.brand-logo-img {
  display: block;
  width: min(190px, 100%);
  height: auto;
  max-height: 42px;
  object-fit: contain;
  object-position: left center;
}

.brand-subtext {
  display: block;
  margin-top: 8px;
  color: rgba(255, 255, 255, 0.9);
  font-size: 11px;
  line-height: 1.2;
}

.himoto-sidebar.collapsed .sidebar-brand {
  padding: 14px 8px;
}

.himoto-sidebar.collapsed .brand-logo-img {
  width: 56px;
  max-height: 22px;
  object-fit: cover;
  object-position: left center;
}

.himoto-sidebar.collapsed {
  width: var(--sidebar-collapsed-width, 72px);
  min-width: var(--sidebar-collapsed-width, 72px);
}

.sidebar-nav-container {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 12px 9px 18px;
  scrollbar-color: #b9bec8 transparent;
  scrollbar-width: thin;
}

.sidebar-nav-container::-webkit-scrollbar {
  width: 6px;
}

.sidebar-nav-container::-webkit-scrollbar-track {
  background: transparent;
}

.sidebar-nav-container::-webkit-scrollbar-thumb {
  background: #b9bec8;
  border-radius: 999px;
}

.nav-groups-wrapper {
  display: flex;
  flex-direction: column;
  gap: 9px;
}

.nav-group {
  margin: 0;
  padding: 7px 6px 8px;
  background: #ffffff;
  border: 1px solid #e8ebf0;
  border-radius: 10px;
  box-shadow: 0 1px 3px rgba(23, 32, 42, 0.04);
}

.nav-group-heading {
  margin: 0 4px 5px;
  padding: 3px 6px 7px;
  border-bottom: 1px solid #eef0f3;
  color: #8f1b21;
  font-size: 10px;
  font-weight: 700;
  line-height: 1.25;
  letter-spacing: 0.65px;
  text-transform: uppercase;
}

.sidebar-mobile-backdrop {
  display: none;
}

.sidebar-nav-item {
  position: relative;
  display: flex;
  align-items: center;
  min-height: 37px;
  padding: 8px 10px;
  border: 1px solid transparent;
  border-radius: 8px;
  margin: 2px 0;
  color: #344054;
  font-size: 13.5px;
  font-weight: 500;
  line-height: 1.3;
  text-decoration: none;
  transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
}
.sidebar-nav-item:hover {
  background-color: #fff4f4;
  border-color: #f5d7d9;
  color: #a41119;
  text-decoration: none;
}
.sidebar-nav-item.active {
  background-color: #fff0f1;
  border-color: #efc3c6;
  color: #b30f18;
  font-weight: 700;
  box-shadow: inset 3px 0 0 #d70f19, 0 2px 6px rgba(200, 16, 24, 0.08);
}

.sidebar-nav-item .nav-item-label {
  min-width: 0;
  color: inherit !important;
  font-weight: inherit !important;
}

.nav-badge-pill {
  margin-left: auto;
  padding: 2px 7px;
  border-radius: 999px;
  background: #fce0e2;
  color: #a41119;
  font-size: 11px;
  font-weight: 700;
}

@media (min-width: 769px) and (max-width: 1024px) {
  .himoto-sidebar:not(.collapsed) {
    width: 248px;
    min-width: 248px;
  }
}

@media (max-width: 768px) {
  .himoto-sidebar,
  .himoto-sidebar.collapsed {
    width: min(82vw, 300px);
    min-width: min(82vw, 300px);
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
