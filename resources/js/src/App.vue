<template>
  <router-view></router-view>
</template>

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
