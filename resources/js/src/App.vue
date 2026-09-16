<template>
  <router-view></router-view>
</template>

<style lang="scss">
// 3rd party plugins css
@import "~bootstrap-vue/dist/bootstrap-vue.css";
@import "~perfect-scrollbar/css/perfect-scrollbar.css";
@import "~socicon/css/socicon.css";
@import "~animate.css";
@import "~@fortawesome/fontawesome-free/css/all.css";
@import "~line-awesome/dist/line-awesome/css/line-awesome.css";
@import "assets/plugins/flaticon/flaticon.css";
@import "assets/plugins/flaticon2/flaticon.css";
@import "assets/plugins/keenthemes-icons/font/ki.css";

// Main demo style scss
@import "assets/sass/style.vue";

// HIMOTO Brand & Design System
@import "assets/himoto/himoto.scss";

/* Product requirement: controls use text labels instead of decorative icons. */
.el-input__prefix,
.el-input__suffix,
.el-select__caret,
.el-message__icon,
.el-notification__icon,
.swal2-icon,
.himoto-app-shell .dropdown-toggle::after {
  display: none !important;
}

.el-input--prefix .el-input__inner,
.el-input--suffix .el-input__inner,
.el-date-editor.el-input .el-input__inner {
  padding-left: 15px !important;
  padding-right: 15px !important;
}

.el-message,
.el-notification {
  padding-left: 16px !important;
}

.el-upload--picture-card {
  line-height: normal !important;
  display: inline-flex !important;
  align-items: center;
  justify-content: center;
  padding: 12px;
}

.el-select-dropdown__list,
.el-select-dropdown__item {
  list-style: none !important;
}

.el-select-dropdown__item::marker {
  content: "";
}

/* BootstrapVue creates a multiplication sign for modal close controls. */
.modal-header .close {
  width: auto;
  min-width: 44px;
  padding: 8px !important;
  font-size: 0 !important;
  line-height: 1;
}

.modal-header .close::after {
  content: "Đóng";
  font-size: 12px;
  font-weight: 600;
  color: #687386;
}

// Check documentation for RTL css
// Update HTML with RTL attribute at public/index.html
/*@import "assets/css/style.vue.rtl";*/
</style>

<script>
import { OVERRIDE_LAYOUT_CONFIG } from "@/core/services/store/config.module";

export default {
  name: "MetronicVue",
  mounted() {
    /**
     * this is to override the layout config using saved data from localStorage
     * remove this to use config only from static json (@/core/config/layout.config.json)
     */
    this.$store.dispatch(OVERRIDE_LAYOUT_CONFIG);
  }
};
</script>
