<template>
  <div class="himoto-app-shell" v-if="isAuthenticated">
    <!-- HIMOTO Brand Sidebar -->
    <HimotoSidebar
      :collapsed="sidebarCollapsed"
      :mobileOpen="mobileSidebarOpen"
      @close-mobile-sidebar="mobileSidebarOpen = false"
    />

    <!-- Main Shell Area -->
    <div
      class="himoto-main-shell"
      :class="{
        'sidebar-collapsed': sidebarCollapsed
      }"
    >
      <!-- Topbar Header -->
      <HimotoHeader
        :drawer-active="drawerOpen"
        @toggle-desktop-sidebar="sidebarCollapsed = !sidebarCollapsed"
        @toggle-mobile-sidebar="mobileSidebarOpen = !mobileSidebarOpen"
        @search-select="onSelectSearchResult"
        @select-result="onSelectSearchResult"
        @quick-create-order="onQuickCreateOrder"
      />

      <!-- Content Area -->
      <main class="himoto-page-content" id="himotoPageContent">
        <transition name="fade-in-fast" mode="out-in">
          <keep-alive :include="cachedViews">
            <router-view :key="$route.name || $route.path" />
          </keep-alive>
        </transition>
      </main>
    </div>

    <!-- Global Inspection Drawer -->
    <HimotoDrawer
      v-model="drawerOpen"
      :title="drawerTitle"
      :subtitle="drawerSubtitle"
      :badge="drawerBadge"
      @close="onDrawerClose"
    >
      <div v-if="drawerItem" class="drawer-detail-content">
        <!-- Vehicle Details -->
        <div v-if="drawerItem.type === 'vehicle'" class="drawer-section">
          <div class="drawer-info-grid">
            <div class="info-row">
              <span class="info-label">Biển số:</span>
              <span class="info-value text-brand-red font-weight-bold" style="font-size: 1.1rem;">
                {{ drawerItem.data.license || drawerItem.data.license_plate || drawerItem.title }}
              </span>
            </div>
            <div class="info-row">
              <span class="info-label">Dòng xe:</span>
              <span class="info-value">{{ drawerItem.data.name || 'HIMOTO Fleet' }}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Chi nhánh:</span>
              <span class="info-value">{{ drawerItem.data.store_name || drawerItem.subtitle }}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Trạng thái:</span>
              <span class="status-badge" :class="drawerItem.data.status || 'ready'">
                {{ drawerItem.data.status_label || drawerItem.meta || 'Sẵn sàng' }}
              </span>
            </div>
            <div class="info-row" v-if="drawerItem.data.odometer != null">
              <span class="info-label">Số km (ODO):</span>
              <span class="info-value">{{ drawerItem.data.odometer }} km</span>
            </div>
            <div class="info-row" v-if="drawerItem.data.daily_price">
              <span class="info-label">Giá thuê ngày:</span>
              <span class="info-value text-danger font-weight-bold">
                {{ drawerItem.data.daily_price | formatPrice }}
              </span>
            </div>
          </div>
        </div>

        <!-- Customer Details -->
        <div v-else-if="drawerItem.type === 'customer'" class="drawer-section">
          <div class="drawer-info-grid">
            <div class="info-row">
              <span class="info-label">Họ và tên:</span>
              <span class="info-value font-weight-bold">{{ drawerItem.data.name || drawerItem.title }}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Điện thoại:</span>
              <span class="info-value">{{ drawerItem.data.phone || drawerItem.subtitle }}</span>
            </div>
            <div class="info-row" v-if="drawerItem.data.id_card">
              <span class="info-label">CCCD / CMND:</span>
              <span class="info-value">{{ drawerItem.data.id_card }}</span>
            </div>
            <div class="info-row" v-if="drawerItem.data.address">
              <span class="info-label">Địa chỉ:</span>
              <span class="info-value">{{ drawerItem.data.address }}</span>
            </div>
          </div>
        </div>

        <!-- Order / Rental Contract Details -->
        <div v-else class="drawer-section">
          <div class="drawer-info-grid">
            <div class="info-row">
              <span class="info-label">Mã hợp đồng:</span>
              <span class="info-value text-brand-red font-weight-bold">
                #{{ drawerItem.data.id || drawerItem.title }}
              </span>
            </div>
            <div class="info-row">
              <span class="info-label">Khách thuê:</span>
              <span class="info-value font-weight-bold">{{ drawerItem.data.customer_name || 'Khách hàng' }}</span>
            </div>
            <div class="info-row" v-if="drawerItem.data.customer_phone">
              <span class="info-label">Số điện thoại:</span>
              <span class="info-value">{{ drawerItem.data.customer_phone }}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Chi nhánh:</span>
              <span class="info-value">{{ drawerItem.data.store_name || drawerItem.subtitle }}</span>
            </div>
            <div class="info-row" v-if="drawerItem.data.rent_at">
              <span class="info-label">Thời gian thuê:</span>
              <span class="info-value">{{ drawerItem.data.rent_at | formatDate }} - {{ drawerItem.data.return_at | formatDate }}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Trạng thái:</span>
              <span class="status-badge" :class="drawerItem.data.order_status || 'renting'">
                {{ drawerItem.data.status_label || drawerItem.subtitle }}
              </span>
            </div>
            <div class="info-row" v-if="drawerItem.data.total">
              <span class="info-label">Tổng tiền:</span>
              <span class="info-value text-danger font-weight-bold">
                {{ drawerItem.data.total | formatPrice }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="d-flex justify-content-between align-items-center w-100">
          <button type="button" class="btn btn-secondary btn-sm" @click="drawerOpen = false">
            Đóng
          </button>
          <button
            v-if="drawerItem"
            type="button"
            class="btn btn-primary btn-sm"
            @click="navigateToItemPage"
          >
            {{ getActionButtonText() }}
          </button>
        </div>
      </template>
    </HimotoDrawer>
  </div>
</template>

<script>
import { mapGetters } from "vuex";
import HimotoSidebar from "@/view/layout/himoto/HimotoSidebar.vue";
import HimotoHeader from "@/view/layout/himoto/HimotoHeader.vue";
import HimotoDrawer from "@/view/layout/himoto/HimotoDrawer.vue";
import { REMOVE_BODY_CLASSNAME } from "@/core/services/store/htmlclass.module.js";

export default {
  name: "Layout",
  components: {
    HimotoSidebar,
    HimotoHeader,
    HimotoDrawer
  },
  data() {
    return {
      sidebarCollapsed: false,
      mobileSidebarOpen: false,
      drawerOpen: false,
      drawerTitle: "Chi tiết",
      drawerSubtitle: "",
      drawerBadge: null,
      drawerItem: null,
      cachedViews: [
        "Dashboard",
        "dashboard",
        "VehicleIndex",
        "CustomerIndex",
        "OrderCarRental",
        "LeadIndex",
        "MaintenanceSchedule",
        "BankIndex",
        "CashIndex",
        "ReportCardRental",
        "ReportVehicleRevenue"
      ]
    };
  },
  computed: {
    ...mapGetters(["isAuthenticated", "currentUser"])
  },
  mounted() {
    if (!this.isAuthenticated) {
      this.$router.push({ name: "login" });
      return;
    }
    if (this.currentUser && this.currentUser.role_id === 4 && (this.$route.path === "/" || this.$route.path === "/dashboard")) {
      this.$router.replace("/leads");
    }
    // Remove old legacy loading classes immediately (Zero artificial lag)
    this.$store.dispatch(REMOVE_BODY_CLASSNAME, "page-loading");
  },
  methods: {
    onSelectSearchResult(item) {
      if (!item) return;
      if (!item.data) item.data = item.raw || item;
      this.drawerItem = item;
      this.drawerTitle = item.title;
      this.drawerSubtitle = item.subtitle || "";
      if (item.type === "vehicle") {
        this.drawerBadge = { text: "Phương tiện", type: "success" };
      } else if (item.type === "customer") {
        this.drawerBadge = { text: "Khách hàng", type: "info" };
      } else {
        this.drawerBadge = { text: "Hợp đồng", type: "warning" };
      }
      this.drawerOpen = true;
    },
    onDrawerClose() {
      this.drawerItem = null;
    },
    getActionButtonText() {
      if (!this.drawerItem) return "Xem chi tiết";
      if (this.drawerItem.type === "vehicle") return "Mở Danh sách xe";
      if (this.drawerItem.type === "customer") return "Mở Hồ sơ khách";
      return "Xem danh sách hợp đồng";
    },
    navigateToItemPage() {
      if (!this.drawerItem) return;
      this.drawerOpen = false;
      const type = this.drawerItem.type;
      if (type === "vehicle") {
        this.$router.push({ path: "/vehicles" });
      } else if (type === "customer") {
        this.$router.push({ path: "/customers" });
      } else {
        this.$router.push({ path: "/car-rental" });
      }
    },
    onQuickCreateOrder() {
      const target = this.currentUser && this.currentUser.role_id === 4 ? "/leads" : "/car-rental";
      if (this.$route.path !== target) {
        this.$router.push(target).catch(() => {});
      }
    }
  }
};
</script>

<style scoped>
.himoto-app-shell {
  display: flex;
  min-height: 100vh;
  position: relative;
  background-color: var(--page-bg, #f5f7fa);
  overflow-x: hidden;
}

.himoto-main-shell {
  flex: 1;
  margin-left: var(--sidebar-width, 248px);
  display: flex;
  flex-direction: column;
  min-width: 0;
  transition: margin-left 250ms cubic-bezier(0.4, 0, 0.2, 1);
}

.himoto-main-shell.sidebar-collapsed {
  margin-left: var(--sidebar-collapsed-width, 72px);
}

.himoto-page-content {
  flex: 1;
  padding: 24px;
  max-width: 1600px;
  width: 100%;
  margin: 0 auto;
  min-height: calc(100vh - var(--header-height, 64px));
}

@media (max-width: 768px) {
  .himoto-main-shell {
    margin-left: 0 !important;
  }
  .himoto-page-content {
    padding: 14px 12px;
  }
}

@media (min-width: 769px) and (max-width: 1024px) {
  .himoto-main-shell {
    margin-left: 248px;
  }

  .himoto-main-shell.sidebar-collapsed {
    margin-left: var(--sidebar-collapsed-width, 72px);
  }
}

.fade-in-fast-enter-active,
.fade-in-fast-leave-active {
  transition: opacity 120ms ease;
}
.fade-in-fast-enter,
.fade-in-fast-leave-to {
  opacity: 0;
}

/* Drawer Internal Info Grid */
.drawer-detail-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.drawer-info-grid {
  display: flex;
  flex-direction: column;
  gap: 12px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--border-light, #eaedf1);
  border-radius: var(--radius-md, 8px);
  padding: 16px;
}

.info-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 8px;
  border-bottom: 1px dashed var(--border-light, #eaedf1);
}

.info-row:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.info-label {
  font-size: 13px;
  color: var(--text-secondary, #687386);
  font-weight: 500;
}

.info-value {
  font-size: 13.5px;
  color: var(--text-primary, #17202a);
  text-align: right;
}

.text-brand-red {
  color: var(--brand-red, #ed1c24) !important;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 9999px;
  font-size: 11.5px;
  font-weight: 700;
  line-height: 1;
}

.status-badge.ready,
.status-badge.success {
  background: rgba(24, 166, 107, 0.12);
  color: #18a66b;
}

.status-badge.repairing,
.status-badge.renting,
.status-badge.warning {
  background: rgba(245, 158, 11, 0.14);
  color: #d97706;
}

.status-badge.info {
  background: rgba(14, 165, 233, 0.12);
  color: #0284c7;
}

.status-badge.broken,
.status-badge.danger {
  background: rgba(237, 28, 36, 0.12);
  color: #ed1c24;
}
</style>
