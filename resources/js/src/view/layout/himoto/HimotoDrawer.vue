<template>
  <div v-if="value">
    <div class="drawer-backdrop active" id="drawerBackdrop" @click="closeDrawer"></div>
    <aside
      class="drawer-panel active slide-drawer open"
      id="slideDrawer"
      ref="drawerContent"
      role="dialog"
      aria-modal="true"
      :aria-label="title"
      tabindex="-1"
      @keydown="handleKeyDown"
    >
      <!-- Header -->
      <div class="drawer-header">
        <div class="drawer-title-group">
          <h3 class="drawer-title" id="drawerTitle" ref="drawerTitle">{{ title }}</h3>
          <span v-if="subtitle" class="drawer-subtitle">{{ subtitle }}</span>
        </div>
        <div class="d-flex align-items-center" style="gap: 8px;">
          <span
            v-if="badge && badge.text"
            class="status-badge"
            :class="badge.type || 'info'"
          >
            {{ badge.text }}
          </span>
          <button
            type="button"
            class="btn btn-secondary btn-sm btn-icon-only drawer-close-btn"
            id="drawerCloseBtn"
            @click="closeDrawer"
            title="Đóng (Escape)"
            aria-label="Đóng"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        </div>
      </div>

      <!-- Body -->
      <div class="drawer-body" id="drawerBody">
        <slot></slot>
      </div>

      <!-- Footer -->
      <div v-if="$slots.footer" class="drawer-footer" id="drawerFooter">
        <slot name="footer"></slot>
      </div>
    </aside>
  </div>
</template>

<script>
export default {
  name: "HimotoDrawer",
  props: {
    value: {
      type: Boolean,
      default: false
    },
    title: {
      type: String,
      default: "Chi tiết"
    },
    subtitle: {
      type: String,
      default: ""
    },
    badge: {
      type: Object,
      default: () => null
    }
  },
  data() {
    return {
      openerElement: null
    };
  },
  watch: {
    value(newVal) {
      if (newVal) {
        this.openerElement = document.activeElement;
        this.$nextTick(() => {
          this.focusDrawer();
        });
      } else {
        this.restoreFocus();
      }
    }
  },
  methods: {
    closeDrawer() {
      this.$emit("input", false);
      this.$emit("close");
    },
    focusDrawer() {
      if (!this.$refs.drawerContent) return;
      const closeBtn = this.$el.querySelector("#drawerCloseBtn");
      if (closeBtn && typeof closeBtn.focus === "function") {
        closeBtn.focus();
        return;
      }
      const focusable = this.getFocusableElements();
      if (focusable.length > 0) {
        focusable[0].focus();
      } else {
        this.$refs.drawerContent.focus();
      }
    },
    restoreFocus() {
      this.$nextTick(() => {
        if (this.openerElement && typeof this.openerElement.focus === "function") {
          this.openerElement.focus();
        }
      });
    },
    getFocusableElements() {
      if (!this.$refs.drawerContent) return [];
      return Array.from(
        this.$refs.drawerContent.querySelectorAll(
          'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )
      );
    },
    handleKeyDown(e) {
      if (e.key === "Escape") {
        e.preventDefault();
        this.closeDrawer();
        return;
      }
      if (e.key === "Tab") {
        const focusable = this.getFocusableElements();
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (e.shiftKey) {
          if (document.activeElement === first) {
            e.preventDefault();
            last.focus();
          }
        } else {
          if (document.activeElement === last) {
            e.preventDefault();
            first.focus();
          }
        }
      }
    }
  }
};
</script>

<style scoped>
.drawer-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(23, 32, 42, 0.45);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  z-index: 998;
  opacity: 1;
  visibility: visible;
  transition: opacity 200ms ease;
}
.drawer-panel {
  position: fixed;
  top: 0;
  right: 0;
  width: 440px;
  max-width: 90vw;
  height: 100vh;
  background: var(--surface, #ffffff);
  box-shadow: -4px 0 24px rgba(23, 32, 42, 0.15);
  z-index: 999;
  display: flex;
  flex-direction: column;
  transform: translateX(0);
  visibility: visible;
  transition: transform 250ms cubic-bezier(0.16, 1, 0.3, 1);
  outline: none;
}
.drawer-header {
  padding: 18px 24px;
  border-bottom: 1px solid var(--border, #e7ebf0);
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.drawer-title-group {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.drawer-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--text-primary, #17202a);
  margin: 0;
}
.drawer-subtitle {
  font-size: 12px;
  color: var(--text-secondary, #687386);
}
.drawer-close-btn {
  background: var(--surface-alt, #f0f3f7);
  border: 1px solid var(--border, #e7ebf0);
  cursor: pointer;
  color: var(--text-secondary, #687386);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  transition: all 150ms;
}
.drawer-close-btn:hover {
  background: #ffffff;
  color: var(--brand-red, #ed1c24);
  border-color: #d1d7df;
}
.drawer-body {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
}
.drawer-footer {
  padding: 16px 24px;
  border-top: 1px solid var(--border, #e7ebf0);
  background: var(--surface-alt, #f0f3f7);
  display: flex;
  align-items: center;
}
</style>
