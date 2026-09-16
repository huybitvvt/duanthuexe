<template>
  <!-- begin::Scrolltop -->
  <div id="kt_scrolltop" ref="kt_scrolltop" class="scrolltop">
    Lên đầu trang
  </div>
  <!-- end::Scrolltop -->
</template>

<script>
import KTLayoutScrolltop from "@/assets/js/layout/extended/scrolltop.js";

export default {
  name: "KTScrollTop",
  mounted() {
    // Init Scrolltop
    KTLayoutScrolltop.init(this.$refs["kt_scrolltop"]);
  }
};
</script>
