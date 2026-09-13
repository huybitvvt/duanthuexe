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
            src="/images/branding/logo-himoto.svg"
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
          &times;
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
            <span class="nav-item-icon">📥</span>
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
              <span class="nav-item-icon">📊</span>
              <span v-if="!collapsed" class="nav-item-label">Dashboard</span>
            </router-link>
            <router-link
              to="/car-rental"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/car-rental') }"
              @click.native="onNavClick"
            >
              <span class="nav-item-icon">🛵</span>
              <span v-if="!collapsed" class="nav-item-label">Đơn thuê xe</span>
              <span v-if="!collapsed && activeRentalCount" class="nav-badge-pill">
                {{ activeRentalCount }}
              </span>
            </router-link>
            <router-link
              to="/car-sell"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/car-sell') }"
              @click.native="onNavClick"
            >
              <span class="nav-item-icon">🏷️</span>
              <span v-if="!collapsed" class="nav-item-label">Đơn bán xe</span>
            </router-link>
            <router-link
              to="/leads"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/leads') }"
              @click.native="onNavClick"
            >
              <span class="nav-item-icon">📥</span>
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
              <span class="nav-item-icon">🏍️</span>
              <span v-if="!collapsed" class="nav-item-label">Danh sách xe</span>
            </router-link>
            <router-link
              to="/maintenance-schedule"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/maintenance-schedule') }"
              @click.native="onNavClick"
            >
              <span class="nav-item-icon">📅</span>
              <span v-if="!collapsed" class="nav-item-label">Lịch hẹn bảo dưỡng</span>
            </router-link>
            <router-link
              to="/maintenance-log"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/maintenance-log') }"
              @click.native="onNavClick"
            >
              <span class="nav-item-icon">🔧</span>
              <span v-if="!collapsed" class="nav-item-label">Lịch sử bảo dưỡng</span>
            </router-link>
            <router-link
              to="/maintenance-rule"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/maintenance-rule') }"
              @click.native="onNavClick"
            >
              <span class="nav-item-icon">⚙️</span>
              <span v-if="!collapsed" class="nav-item-label">Tần suất bảo dưỡng</span>
            </router-link>
            <router-link
              to="/maintenance-type"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/maintenance-type') }"
              @click.native="onNavClick"
            >
              <span class="nav-item-icon">🔩</span>
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
              <span class="nav-item-icon">👥</span>
              <span v-if="!collapsed" class="nav-item-label">Khách hàng</span>
            </router-link>
            <router-link
              to="/stores"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/stores') }"
              @click.native="onNavClick"
            >
              <span class="nav-item-icon">🏪</span>
              <span v-if="!collapsed" class="nav-item-label">Cửa hàng</span>
            </router-link>
          </div>

          <!-- GROUP 4: TÀI CHÍNH & NGUỒN TIỀN -->
          <div class="nav-group">
            <div v-if="!collapsed" class="nav-group-heading">TÀI CHÍNH & THU CHI</div>
            <router-link
              to="/banks"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/banks') }"
              @click.native="onNavClick"
            >
              <span class="nav-item-icon">🏦</span>
              <span v-if="!collapsed" class="nav-item-label">Tài khoản ngân hàng</span>
            </router-link>
            <router-link
              to="/cash"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/cash') }"
              @click.native="onNavClick"
            >
              <span class="nav-item-icon">💵</span>
              <span v-if="!collapsed" class="nav-item-label">Quỹ tiền mặt</span>
            </router-link>
            <router-link
              to="/transactions"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/transactions') }"
              @click.native="onNavClick"
            >
              <span class="nav-item-icon">📜</span>
              <span v-if="!collapsed" class="nav-item-label">Lịch sử thu chi</span>
            </router-link>
            <router-link
              to="/receipt"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/receipt') }"
              @click.native="onNavClick"
            >
              <span class="nav-item-icon">🧾</span>
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
              <span class="nav-item-icon">📈</span>
              <span v-if="!collapsed" class="nav-item-label">Báo cáo tổng quan</span>
            </router-link>
            <router-link
              to="/report/vehicle-revenue"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/report/vehicle-revenue') }"
              @click.native="onNavClick"
            >
              <span class="nav-item-icon">💰</span>
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
              <span class="nav-item-icon">💲</span>
              <span v-if="!collapsed" class="nav-item-label">Bảng giá thuê</span>
            </router-link>
            <router-link
              to="/user"
              class="sidebar-nav-item"
              :class="{ active: isRouteActive('/user') }"
              @click.native="onNavClick"
            >
              <span class="nav-item-icon">🛡️</span>
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
    isRouteActive(path) {
      return this.$route.path === path || this.$route.path.startsWith(path + "/");
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
  width: var(--sidebar-width, 248px);
  min-width: var(--sidebar-width, 248px);
  height: 100vh;
  position: fixed;
  top: 0;
  left: 0;
  z-index: var(--z-sidebar, 100);
  background: linear-gradient(180deg, #ed1c24 0%, #c81018 100%);
  color: #ffffff;
  display: flex;
  flex-direction: column;
  transition: width 250ms cubic-bezier(0.4, 0, 0.2, 1), transform 250ms cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 4px 0 20px rgba(200, 16, 24, 0.18);
  user-select: none;
}
.himoto-sidebar.collapsed {
  width: var(--sidebar-collapsed-width, 72px);
  min-width: var(--sidebar-collapsed-width, 72px);
}
.sidebar-brand {
  height: var(--header-height, 64px);
  padding: 0 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid rgba(255, 255, 255, 0.14);
}
.brand-link {
  display: flex;
  flex-direction: column;
  justify-content: center;
  text-decoration: none;
  overflow: hidden;
}
.brand-logo-img {
  height: 24px;
  width: auto;
  max-width: 130px;
  object-fit: contain;
}
.brand-subtext {
  font-size: 9px;
  color: var(--brand-yellow, #fff200);
  font-weight: 700;
  letter-spacing: 0.8px;
  text-transform: uppercase;
  margin-top: 2px;
}
.btn-close-sidebar-mobile {
  background: transparent;
  border: none;
  color: #ffffff;
  font-size: 26px;
  line-height: 1;
  cursor: pointer;
}
.sidebar-nav-container {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 12px 8px;
}
.sidebar-nav-container::-webkit-scrollbar {
  width: 4px;
}
.sidebar-nav-container::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.2);
  border-radius: 4px;
}
.nav-group {
  margin-bottom: 16px;
}
.nav-group-heading {
  padding: 6px 12px;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.6);
  white-space: nowrap;
}
.sidebar-nav-item {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 9px 12px;
  border-radius: 8px;
  color: rgba(255, 255, 255, 0.85);
  font-size: 13.5px;
  font-weight: 500;
  text-decoration: none;
  margin-bottom: 2px;
  position: relative;
  transition: all 150ms ease;
}
.sidebar-nav-item:hover {
  background: rgba(255, 255, 255, 0.12);
  color: #ffffff;
  text-decoration: none;
}
.sidebar-nav-item.active {
  background: rgba(0, 0, 0, 0.22);
  color: #ffffff;
  font-weight: 700;
}
.sidebar-nav-item.active::before {
  content: "";
  position: absolute;
  left: 0;
  top: 6px;
  bottom: 6px;
  width: 4px;
  background-color: var(--brand-yellow, #fff200);
  border-radius: 0 4px 4px 0;
  box-shadow: 0 0 8px rgba(255, 242, 0, 0.8);
}
.nav-item-icon {
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 15px;
}
.nav-item-label {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.nav-badge-pill {
  padding: 2px 7px;
  font-size: 10.5px;
  font-weight: 700;
  border-radius: 9999px;
  background: var(--brand-yellow, #fff200);
  color: #990000;
}
.sidebar-operator-footer {
  padding: 12px 14px;
  border-top: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(0, 0, 0, 0.12);
}
.operator-badge {
  display: flex;
  align-items: center;
  gap: 10px;
}
.op-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #2ecc71;
  box-shadow: 0 0 6px #2ecc71;
  flex-shrink: 0;
}
.op-info {
  display: flex;
  flex-direction: column;
  line-height: 1.2;
  overflow: hidden;
}
.op-name {
  font-size: 12px;
  font-weight: 700;
  color: #ffffff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.op-status {
  font-size: 10px;
  color: rgba(255, 255, 255, 0.7);
}
.sidebar-mobile-backdrop {
  display: none;
}

@media (max-width: 768px) {
  .himoto-sidebar {
    transform: translateX(-100%);
    width: 260px;
    min-width: 260px;
  }
  .himoto-sidebar.mobile-open {
    transform: translateX(0);
  }
  .sidebar-mobile-backdrop {
    display: block;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 99;
  }
}
</style>
