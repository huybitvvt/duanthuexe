<template>
  <header class="app-header">
    <div class="header-left">
      <!-- Mobile Sidebar Toggle -->
      <button
        type="button"
        class="header-action-btn mobile-menu-toggle"
        id="btnSidebarToggleMobile"
        @click="$emit('toggle-mobile-sidebar')"
        aria-label="Mở menu"
      >
        <span class="btn-text-label">Menu</span>
      </button>

      <!-- Desktop Sidebar Collapse Toggle -->
      <button
        type="button"
        class="header-action-btn desktop-menu-toggle"
        id="btnSidebarToggleDesktop"
        @click="$emit('toggle-desktop-sidebar')"
        :title="sidebarCollapsed ? 'Mở rộng menu' : 'Thu gọn menu'"
        :aria-label="sidebarCollapsed ? 'Mở rộng menu' : 'Thu gọn menu'"
      >
        <svg
          class="sidebar-toggle-icon"
          :class="{ 'is-collapsed': sidebarCollapsed }"
          viewBox="0 0 24 24"
          aria-hidden="true"
          focusable="false"
        >
          <path d="M5 4v16" />
          <path d="m15 7-5 5 5 5" />
        </svg>
      </button>

      <!-- Store / Branch Selector -->
      <div class="store-selector-wrapper">
        <span class="store-label-text">Chi nhánh:</span>
        <select
          id="globalStoreSelect"
          class="store-select"
          v-model="selectedStoreId"
          @change="onStoreChange"
          aria-label="Chọn chi nhánh"
        >
          <option value="all">Toàn hệ thống (Tất cả)</option>
          <option v-for="st in storeList" :key="st.id" :value="st.id">
            {{ st.store_name }}
          </option>
        </select>
      </div>
    </div>

    <!-- Desktop Global Search Bar -->
    <div class="header-search-wrapper" ref="searchContainer">
      <div class="search-input-box">
        <input
          type="text"
          id="globalSearchInput"
          ref="desktopSearchInput"
          class="global-search-input"
          placeholder="Tìm: xe, khách hàng, hợp đồng..."
          v-model="searchQuery"
          @input="onSearchInput"
          @focus="onSearchFocus"
          @keydown="onSearchKeydown"
          autocomplete="off"
          aria-label="Tìm kiếm toàn hệ thống"
        />
        <kbd class="search-kbd-hint">Ctrl K</kbd>
        <button
          v-if="searchQuery"
          type="button"
          class="search-clear-btn"
          @click="clearSearch"
          aria-label="Xóa từ khóa"
        >
          Xóa
        </button>
      </div>

      <!-- Search Results Dropdown -->
      <div
        v-if="isSearchDropdownOpen"
        id="globalSearchResults"
        class="search-dropdown-menu"
        role="listbox"
      >
        <!-- Category Tabs -->
        <div class="search-tabs">
          <button
            type="button"
            class="search-tab-btn"
            :class="{ active: activeSearchCategory === 'all' }"
            @click="activeSearchCategory = 'all'"
          >
            Tất cả ({{ totalResultsCount }})
          </button>
          <button
            type="button"
            class="search-tab-btn"
            :class="{ active: activeSearchCategory === 'vehicles' }"
            @click="activeSearchCategory = 'vehicles'"
          >
            Xe ({{ searchResults.vehicles.length }})
          </button>
          <button
            type="button"
            class="search-tab-btn"
            :class="{ active: activeSearchCategory === 'customers' }"
            @click="activeSearchCategory = 'customers'"
          >
            Khách ({{ searchResults.customers.length }})
          </button>
          <button
            type="button"
            class="search-tab-btn"
            :class="{ active: activeSearchCategory === 'orders' }"
            @click="activeSearchCategory = 'orders'"
          >
            Đơn ({{ searchResults.orders.length }})
          </button>
        </div>

        <div class="search-results-list" ref="resultsList">
          <div v-if="filteredSearchResults.length === 0" class="search-empty-state">
            Không tìm thấy kết quả cho "{{ searchQuery }}"
          </div>

          <div
            v-for="(item, idx) in filteredSearchResults"
            :key="item.type + '_' + item.id"
            class="search-result-row"
            :class="{ highlighted: highlightedIndex === idx }"
            role="option"
            :aria-selected="highlightedIndex === idx"
            @mouseenter="highlightedIndex = idx"
            @click="selectSearchResult(item)"
          >
            <div class="search-result-icon">
              <span v-if="item.type === 'vehicle'">[Xe]</span>
              <span v-else-if="item.type === 'customer'">[Khách]</span>
              <span v-else>[Đơn]</span>
            </div>
            <div class="search-result-info">
              <div class="search-result-title">
                {{ item.title }}
                <span class="search-result-type-tag">{{ item.typeLabel }}</span>
              </div>
              <div class="search-result-sub">{{ item.subtitle }}</div>
            </div>
            <div class="search-result-meta">
              <span v-if="item.meta" class="status-badge-sm">{{ item.meta }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Header Right Controls -->
    <div class="header-right">
      <HeaderPreferences />
      <!-- Mobile Search Button -->
      <button
        type="button"
        class="header-action-btn mobile-search-toggle"
        id="btnSearchToggleMobile"
        @click="toggleMobileSearch"
        aria-label="Mở tìm kiếm"
      >
        <span class="btn-text-label">Tìm</span>
      </button>

      <!-- Quick Create Contract Button -->
      <button
        type="button"
        class="btn btn-primary btn-sm btn-quick-order"
        @click="onQuickCreateOrder"
      >
        <span>Tạo đơn</span>
      </button>

      <!-- User Profile Dropdown -->
      <div class="header-user-badge">
        <div class="user-avatar-circle">
          {{ userInitials }}
        </div>
        <div class="user-info-text d-none d-md-flex">
          <span class="user-display-name">{{ currentUserName }}</span>
          <span class="user-role-name">{{ userRoleText }}</span>
        </div>
        <button
          type="button"
          class="btn-logout-header"
          @click="handleLogout"
          title="Đăng xuất"
          aria-label="Đăng xuất"
        >
          <span class="logout-text">Đăng xuất</span>
        </button>
      </div>
    </div>

    <!-- Mobile Search Bar Drawer/Bar -->
    <div v-if="searchMobileOpen" class="mobile-search-bar show" ref="mobileSearchContainer">
      <div class="search-input-box">
        <input
          type="text"
          id="globalSearchInputMobile"
          ref="mobileSearchInput"
          class="global-search-input"
          placeholder="Tìm: xe, khách hàng, hợp đồng..."
          v-model="searchQuery"
          @input="onSearchInput"
          @focus="onSearchFocus"
          @keydown="onSearchKeydown"
          autocomplete="off"
          aria-label="Tìm kiếm di động"
        />
        <button
          type="button"
          class="btn-icon-only search-close-mobile"
          @click="closeMobileSearch"
          aria-label="Đóng tìm kiếm"
        >
          Đóng
        </button>
      </div>

      <!-- Mobile Dropdown Results -->
      <div v-if="isSearchDropdownOpen" class="search-dropdown-menu mobile-dropdown">
        <div class="search-results-list">
          <div v-if="filteredSearchResults.length === 0" class="search-empty-state">
            Không tìm thấy kết quả
          </div>
          <div
            v-for="(item, idx) in filteredSearchResults"
            :key="'m_' + item.type + '_' + item.id"
            class="search-result-row"
            :class="{ highlighted: highlightedIndex === idx }"
            @click="selectSearchResult(item)"
          >
            <div class="search-result-icon">
              <span v-if="item.type === 'vehicle'">[Xe]</span>
              <span v-else-if="item.type === 'customer'">[Khách]</span>
              <span v-else>[Đơn]</span>
            </div>
            <div class="search-result-info">
              <div class="search-result-title">{{ item.title }}</div>
              <div class="search-result-sub">{{ item.subtitle }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>
</template>

<script>
import { mapGetters } from "vuex";
import HeaderPreferences from "./HeaderPreferences.vue";
import { LOGOUT } from "@/core/services/store/auth.module";
import { STORE_GET_ALL, SET_SELECTED_STORE_ID } from "@/core/services/store/store.module";
import ApiService from "@/core/services/api.service";

const unwrapList = (payload) => {
  if (!payload) return [];
  const val = payload.data !== undefined ? payload.data : payload;
  if (Array.isArray(val)) return val;
  if (val && Array.isArray(val.data)) return val.data;
  return [];
};

const VEHICLE_STATUS_MAP = {
  ready: "Sẵn sàng",
  using: "Đang sử dụng",
  renting: "Đang thuê",
  repairing: "Đang sửa",
  pending: "Chờ duyệt",
  sold: "Đã bán",
  broken: "Đã hỏng",
  bad_debt: "Nợ xấu",
  1: "Sẵn sàng",
  2: "Đang thuê",
  3: "Đang sửa",
  4: "Chờ duyệt",
  5: "Đã bán",
  6: "Đã hỏng",
  7: "Nợ xấu"
};

const adaptVehicle = (v) => {
  if (!v) return {};
  const statusKey = v.status != null ? String(v.status).toLowerCase() : "ready";
  const statusLabel =
    v.status_name ||
    v.status_label ||
    VEHICLE_STATUS_MAP[statusKey] ||
    VEHICLE_STATUS_MAP[v.status] ||
    (typeof v.status === "string" ? v.status : "Sẵn sàng");

  const license = v.license || v.license_plate || "";
  const odo = v.odometer != null ? v.odometer : (v.total_km != null ? v.total_km : 0);
  const storeName = v.store?.store_name || v.store_name || (v.store_id ? `Chi nhánh #${v.store_id}` : "Kho");

  return {
    ...v,
    id: v.id,
    name: v.name || "Xe",
    license,
    license_plate: license,
    odometer: odo,
    total_km: odo,
    status: typeof v.status === "string" ? v.status : (v.status === 1 ? "ready" : (v.status === 3 ? "repairing" : "using")),
    status_label: statusLabel,
    status_name: statusLabel,
    store_name: storeName
  };
};

export default {
  name: "HimotoHeader",
  components: { HeaderPreferences },
  props: {
    drawerActive: {
      type: Boolean,
      default: false
    },
    sidebarCollapsed: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      selectedStoreId: this.$store?.getters?.selectedStoreId || "all",
      storeList: [],
      searchQuery: "",
      isSearchDropdownOpen: false,
      searchMobileOpen: false,
      activeSearchCategory: "all",
      highlightedIndex: -1,
      resizeDebounceTimer: null,
      searchDebounceTimer: null,
      searchResults: {
        vehicles: [],
        customers: [],
        orders: []
      }
    };
  },
  computed: {
    ...mapGetters(["currentUser"]),
    currentUserName() {
      return this.currentUser?.name || "Vận hành HIMOTO";
    },
    userRoleText() {
      if (this.currentUser?.role_id === 1) return "Quản trị viên";
      if (this.currentUser?.role_id === 4) return "Tư vấn Lead";
      return "Quản lý chi nhánh";
    },
    userInitials() {
      const name = this.currentUserName;
      const parts = name.trim().split(" ");
      if (parts.length >= 2) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
      }
      return (name.substring(0, 2) || "HM").toUpperCase();
    },
    totalResultsCount() {
      return (
        this.searchResults.vehicles.length +
        this.searchResults.customers.length +
        this.searchResults.orders.length
      );
    },
    filteredSearchResults() {
      let list = [];
      if (this.activeSearchCategory === "all" || this.activeSearchCategory === "vehicles") {
        list = list.concat(this.searchResults.vehicles);
      }
      if (this.activeSearchCategory === "all" || this.activeSearchCategory === "customers") {
        list = list.concat(this.searchResults.customers);
      }
      if (this.activeSearchCategory === "all" || this.activeSearchCategory === "orders") {
        list = list.concat(this.searchResults.orders);
      }
      return list;
    }
  },
  mounted() {
    this.fetchStores();
    window.addEventListener("resize", this.handleViewportResize);
    window.addEventListener("keydown", this.handleGlobalShortcuts);
    document.addEventListener("click", this.handleDocumentClick);
  },
  beforeDestroy() {
    window.removeEventListener("resize", this.handleViewportResize);
    window.removeEventListener("keydown", this.handleGlobalShortcuts);
    document.removeEventListener("click", this.handleDocumentClick);
  },
  methods: {
    fetchStores() {
      this.$store.dispatch(STORE_GET_ALL, {}).then((res) => {
        this.storeList = res?.data || [];
      });
    },
    onStoreChange() {
      this.$store.dispatch(SET_SELECTED_STORE_ID, this.selectedStoreId);
      this.$emit("store-change", this.selectedStoreId);
    },
    handleViewportResize() {
      clearTimeout(this.resizeDebounceTimer);
      this.resizeDebounceTimer = setTimeout(() => {
        const isMobile = window.innerWidth <= 768;
        const isSearching =
          this.isSearchDropdownOpen ||
          (this.$refs.desktopSearchInput && document.activeElement === this.$refs.desktopSearchInput) ||
          (this.$refs.mobileSearchInput && document.activeElement === this.$refs.mobileSearchInput) ||
          (this.searchQuery && this.searchQuery.trim().length > 0);

        if (isSearching) {
          if (isMobile) {
            this.searchMobileOpen = true;
          } else {
            this.searchMobileOpen = false;
          }
          this.$nextTick(() => {
            const target = isMobile ? this.$refs.mobileSearchInput : this.$refs.desktopSearchInput;
            if (target && typeof target.focus === "function") {
              target.focus();
            }
          });
        }
      }, 50);
    },
    handleGlobalShortcuts(e) {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "k") {
        // Guard against stealing focus when drawer or modal dialog is open
        const isOverlayOpen =
          this.drawerActive ||
          !!document.querySelector(
            ".drawer-backdrop.active, .drawer-panel.active, #slideDrawer.active, .modal.show, [role='dialog']"
          );
        if (isOverlayOpen) {
          e.preventDefault();
          e.stopPropagation();
          return;
        }

        e.preventDefault();
        const isMobile = window.innerWidth <= 768;
        if (isMobile) {
          this.searchMobileOpen = true;
          this.$nextTick(() => {
            if (this.$refs.mobileSearchInput) this.$refs.mobileSearchInput.focus();
          });
        } else {
          if (this.$refs.desktopSearchInput) this.$refs.desktopSearchInput.focus();
        }
      }
    },
    onQuickCreateOrder() {
      this.$emit("quick-create-order");
    },
    handleDocumentClick(e) {
      const searchBox = this.$refs.searchContainer;
      const mobileBox = this.$refs.mobileSearchContainer;
      if (
        (searchBox && searchBox.contains(e.target)) ||
        (mobileBox && mobileBox.contains(e.target))
      ) {
        return;
      }
      this.isSearchDropdownOpen = false;
    },
    onSearchFocus() {
      if (this.searchQuery.trim().length >= 1) {
        this.isSearchDropdownOpen = true;
      }
    },
    onSearchInput() {
      const q = this.searchQuery.trim();
      if (!q) {
        this.isSearchDropdownOpen = false;
        this.highlightedIndex = -1;
        return;
      }
      this.isSearchDropdownOpen = true;
      this.highlightedIndex = -1;

      clearTimeout(this.searchDebounceTimer);
      this.searchDebounceTimer = setTimeout(() => {
        this.performLiveSearch(q);
      }, 200);
    },
    performLiveSearch(query) {
      const qLower = query.toLowerCase();

      // Search real backend endpoints where available or filter cached list
      const storeIdParam = this.selectedStoreId !== "all" ? { store_id: this.selectedStoreId } : {};

      // Vehicle search (supports Laravel LengthAwarePaginator and flat array)
      ApiService.query("/api/auth/vehicle/vehicles", {
        name: qLower,
        keyword: qLower,
        limit: 5,
        ...storeIdParam
      })
        .then(({ data }) => {
          const vList = unwrapList(data);
          this.searchResults.vehicles = vList.map((rawV) => {
            const v = adaptVehicle(rawV);
            return {
              id: v.id,
              type: "vehicle",
              typeLabel: "Xe",
              title: `${v.name} (${v.license})`,
              subtitle: `${v.store_name}${v.color ? " • " + v.color : ""} • ODO ${v.odometer}km`,
              meta: v.status_label,
              raw: rawV,
              data: v
            };
          });
        })
        .catch((err) => {
          console.warn("Himoto search vehicles failed:", err);
          this.searchResults.vehicles = [];
        });

      // Customer search (supports Laravel LengthAwarePaginator and flat array)
      ApiService.query("/api/auth/customers", { keyword: qLower, limit: 5 })
        .then(({ data }) => {
          const cList = unwrapList(data);
          this.searchResults.customers = cList.map((c) => ({
            id: c.id,
            type: "customer",
            typeLabel: "Khách hàng",
            title: c.name || "Khách",
            subtitle: `SĐT: ${c.phone || "---"} • CCCD: ${c.id_card || c.identity_card || "---"}`,
            meta: c.total_order ? `${c.total_order} đơn` : null,
            raw: c,
            data: c
          }));
        })
        .catch((err) => {
          console.warn("Himoto search customers failed:", err);
          this.searchResults.customers = [];
        });

      // Order search (supports Laravel LengthAwarePaginator and flat array)
      ApiService.query("/api/auth/order/car-rental", { keyword: qLower, limit: 5, ...storeIdParam })
        .then(({ data }) => {
          const oList = unwrapList(data);
          this.searchResults.orders = oList.map((o) => ({
            id: o.id,
            type: "order",
            typeLabel: "Đơn thuê",
            title: `HĐ #${o.id} - ${o.customer_name || ""}`,
            subtitle: `Xe: ${o.vehicles?.[0]?.name || ""} (${o.vehicles?.[0]?.license || ""})`,
            meta: o.order_status,
            raw: o,
            data: o
          }));
        })
        .catch((err) => {
          console.warn("Himoto search orders failed:", err);
          this.searchResults.orders = [];
        });
    },
    onSearchKeydown(e) {
      const items = this.filteredSearchResults;
      if (!this.isSearchDropdownOpen || items.length === 0) return;

      if (e.key === "ArrowDown") {
        e.preventDefault();
        if (this.highlightedIndex < items.length - 1) {
          this.highlightedIndex++;
        } else {
          this.highlightedIndex = 0;
        }
      } else if (e.key === "ArrowUp") {
        e.preventDefault();
        if (this.highlightedIndex > 0) {
          this.highlightedIndex--;
        } else {
          this.highlightedIndex = items.length - 1;
        }
      } else if (e.key === "Enter") {
        e.preventDefault();
        if (this.highlightedIndex >= 0 && this.highlightedIndex < items.length) {
          this.selectSearchResult(items[this.highlightedIndex]);
        }
      } else if (e.key === "Escape") {
        e.preventDefault();
        this.isSearchDropdownOpen = false;
      }
    },
    selectSearchResult(item) {
      this.isSearchDropdownOpen = false;
      this.$emit("search-select", item);
      this.$emit("select-result", item);
    },
    clearSearch() {
      this.searchQuery = "";
      this.isSearchDropdownOpen = false;
      const isMobile = window.innerWidth <= 768;
      const target = isMobile ? this.$refs.mobileSearchInput : this.$refs.desktopSearchInput;
      if (target) target.focus();
    },
    toggleMobileSearch() {
      this.searchMobileOpen = !this.searchMobileOpen;
      if (this.searchMobileOpen) {
        this.$nextTick(() => {
          if (this.$refs.mobileSearchInput) this.$refs.mobileSearchInput.focus();
        });
      }
    },
    closeMobileSearch() {
      this.searchMobileOpen = false;
      this.isSearchDropdownOpen = false;
    },
    handleLogout() {
      this.$store.dispatch(LOGOUT);
      this.$router.push({ name: "login" });
    }
  }
};
</script>

<style scoped>
.app-header {
  height: var(--header-height, 64px);
  background: var(--surface, #ffffff);
  border-bottom: 1px solid var(--border, #e7ebf0);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
  position: sticky;
  top: 0;
  z-index: var(--z-header, 90);
  box-shadow: var(--shadow-sm, 0 2px 6px rgba(23, 32, 42, 0.04));
}
.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}
.header-action-btn {
  width: 36px;
  height: 36px;
  border-radius: var(--radius-sm, 8px);
  border: 1px solid var(--border, #e7ebf0);
  background: var(--surface, #ffffff);
  color: var(--text-primary, #17202a);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all var(--transition-fast, 150ms);
}
.header-action-btn:hover {
  background: var(--surface-alt, #f0f3f7);
  border-color: #d1d7df;
}
.sidebar-toggle-icon {
  width: 20px;
  height: 20px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
  transition: transform var(--transition-fast, 150ms);
}
.sidebar-toggle-icon.is-collapsed {
  transform: scaleX(-1);
}
.store-selector-wrapper {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--surface-alt, #f0f3f7);
  border: 1px solid var(--border, #e7ebf0);
  border-radius: var(--radius-md, 12px);
  padding: 4px 12px;
}
.store-icon {
  color: var(--brand-red, #ed1c24);
  flex-shrink: 0;
}
.store-select {
  border: none;
  background: transparent;
  font-size: var(--font-size-base, 14px);
  font-weight: 600;
  color: var(--text-primary, #17202a);
  outline: none;
  cursor: pointer;
}
.header-search-wrapper {
  position: relative;
  flex: 1;
  max-width: 440px;
  margin: 0 20px;
}
.search-input-box {
  display: flex;
  align-items: center;
  position: relative;
}
.search-icon {
  position: absolute;
  left: 12px;
  color: var(--text-muted, #9aa4b2);
  pointer-events: none;
}
.global-search-input {
  width: 100%;
  height: 38px;
  padding: 0 68px 0 16px;
  border-radius: var(--radius-full, 9999px);
  border: 1px solid var(--border, #e7ebf0);
  background: var(--surface-alt, #f0f3f7);
  font-size: var(--font-size-base, 14px);
  color: var(--text-primary, #17202a);
  outline: none;
  transition: all var(--transition-fast, 150ms);
}
.global-search-input:focus {
  background: var(--surface, #ffffff);
  border-color: var(--brand-red, #ed1c24);
  box-shadow: 0 0 0 3px var(--brand-red-subtle, rgba(237, 28, 36, 0.08));
}
.search-kbd-hint {
  position: absolute;
  right: 12px;
  padding: 2px 6px;
  font-size: 10px;
  font-weight: 700;
  color: var(--text-muted, #9aa4b2);
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e7ebf0);
  border-radius: 4px;
}
.search-clear-btn {
  position: absolute;
  right: 38px;
  background: transparent;
  border: none;
  font-size: 18px;
  color: var(--text-secondary, #687386);
  cursor: pointer;
}
.search-dropdown-menu {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  right: 0;
  background: var(--surface, #ffffff);
  border-radius: var(--radius-md, 12px);
  border: 1px solid var(--border, #e7ebf0);
  box-shadow: var(--shadow-dropdown, 0 12px 32px rgba(23, 32, 42, 0.12));
  z-index: var(--z-dropdown, 120);
  overflow: hidden;
}
.search-tabs {
  display: flex;
  background: var(--surface-alt, #f0f3f7);
  border-bottom: 1px solid var(--border, #e7ebf0);
  padding: 4px;
  gap: 4px;
}
.search-tab-btn {
  flex: 1;
  padding: 6px 8px;
  border: none;
  background: transparent;
  font-size: var(--font-size-xs, 10.5px);
  font-weight: 600;
  color: var(--text-secondary, #687386);
  border-radius: var(--radius-sm, 8px);
  cursor: pointer;
  transition: all var(--transition-fast, 150ms);
}
.search-tab-btn.active {
  background: var(--surface, #ffffff);
  color: var(--brand-red, #ed1c24);
  box-shadow: var(--shadow-sm, 0 2px 6px rgba(23, 32, 42, 0.04));
}
.search-results-list {
  max-height: 320px;
  overflow-y: auto;
}
.search-result-row {
  display: flex;
  align-items: center;
  padding: 10px 14px;
  border-bottom: 1px solid var(--border-light, #f1f3f6);
  cursor: pointer;
  transition: background var(--transition-fast, 150ms);
  gap: 12px;
}
.search-result-row:hover,
.search-result-row.highlighted {
  background: var(--surface-alt, #f0f3f7);
}
.search-result-type-tag {
  font-size: 10px;
  padding: 1px 5px;
  border-radius: 4px;
  background: var(--brand-red-subtle, rgba(237, 28, 36, 0.08));
  color: var(--brand-red, #ed1c24);
  font-weight: 700;
  margin-left: 6px;
}
.search-result-title {
  font-size: var(--font-size-base, 14px);
  font-weight: 600;
  color: var(--text-primary, #17202a);
}
.search-result-sub {
  font-size: var(--font-size-xs, 10.5px);
  color: var(--text-secondary, #687386);
}
.search-empty-state {
  padding: 20px;
  text-align: center;
  color: var(--text-muted, #9aa4b2);
  font-size: var(--font-size-sm, 12px);
}
.header-right {
  display: flex;
  align-items: center;
  gap: 12px;
}
.header-user-badge {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 4px 8px;
  border-radius: var(--radius-full, 9999px);
  background: var(--surface-alt, #f0f3f7);
  border: 1px solid var(--border, #e7ebf0);
}
.user-avatar-circle {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--brand-red, #ed1c24);
  color: #ffffff;
  font-size: 11px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}
.user-info-text {
  flex-direction: column;
  line-height: 1.2;
}
.user-display-name {
  font-size: 12px;
  font-weight: 700;
  color: var(--text-primary, #17202a);
}
.user-role-name {
  font-size: 10px;
  color: var(--text-secondary, #687386);
}
.btn-logout-header {
  background: transparent;
  border: none;
  color: var(--text-secondary, #687386);
  cursor: pointer;
  display: flex;
  align-items: center;
  padding: 4px;
  border-radius: 4px;
}
.btn-logout-header:hover {
  color: var(--brand-red, #ed1c24);
  background: rgba(237, 28, 36, 0.08);
}
.mobile-search-bar {
  display: block !important;
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: var(--surface, #ffffff);
  padding: 10px 16px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  border-bottom: 1px solid var(--border, #e7ebf0);
  z-index: 150;
}
.mobile-search-toggle {
  display: none;
}
.mobile-menu-toggle {
  display: none;
}

@media (max-width: 768px) {
  .app-header {
    height: 56px;
    padding: 0 8px;
    gap: 6px;
  }
  .header-left,
  .header-right {
    gap: 6px;
    min-width: 0;
  }
  .header-left {
    flex: 1 1 auto;
  }
  .header-search-wrapper {
    display: none;
  }
  .mobile-search-toggle {
    display: flex;
  }
  .mobile-menu-toggle {
    display: flex;
  }
  .desktop-menu-toggle {
    display: none;
  }
  .store-selector-wrapper {
    flex: 1 1 auto;
    min-width: 0;
    max-width: 140px;
    overflow: hidden;
    padding: 4px 8px;
  }
  .store-label-text {
    display: none;
  }
  .store-select {
    font-size: 12px;
    width: 100%;
    min-width: 0;
    max-width: 124px;
    text-overflow: ellipsis;
  }
  .btn-quick-order {
    padding: 7px 8px;
    font-size: 11px;
    white-space: nowrap;
  }
  .header-user-badge {
    gap: 0;
    padding: 0;
    border: 0;
    background: transparent;
  }
  .user-avatar-circle {
    display: none;
  }
  .btn-logout-header {
    min-height: 36px;
    padding: 0 7px;
    border: 1px solid var(--border, #e7ebf0);
    font-size: 11px;
    background: var(--surface, #ffffff);
  }
}
</style>
