<template>
    <div
        id="kt_header_mobile"
        class="header-mobile align-items-center"
        v-bind:class="headerClasses"
    >
        <!--begin::Logo-->
        <a href="/">
            <!-- <img style="width: 40px; height: 40px" alt="Logo" src="/images/himoto.png"/> -->
            <img class="himoto-header-logo" alt="Logo" src="/images/branding/logo-himoto-pdf.png"/>
            <!--      <img alt="Logo" :src="headerLogo" />-->
        </a>
        <!--end::Logo-->
        <!--begin::Toolbar-->
        <div class="d-flex align-items-center">
            <!--begin::Aside Mobile Toggle-->
            <button
                v-if="asideEnabled"
                class="btn p-0 burger-icon burger-icon-left"
                id="kt_aside_mobile_toggle"
            >
                Mở menu
            </button>
            <!--end::Aside Mobile Toggle-->
            <!--begin::Header Menu Mobile Toggle-->
            <!--      <button-->
            <!--        class="btn p-0 burger-icon ml-4"-->
            <!--        id="kt_header_mobile_toggle"-->
            <!--        ref="kt_header_mobile_toggle"-->
            <!--      >-->
            <!--        <span></span>-->
            <!--      </button>-->
            <!--end::Header Menu Mobile Toggle-->
            <!--begin::Topbar Mobile Toggle-->
            <button
                class="btn btn-hover-text-primary p-0 ml-2"
                id="kt_header_mobile_topbar_toggle"
                ref="kt_header_mobile_topbar_toggle"
            >
                Tài khoản
            </button>
            <!--end::Topbar Mobile Toggle-->
        </div>
        <!--end::Toolbar-->
    </div>
</template>

<script>
import {mapGetters} from "vuex";
import KTLayoutHeaderTopbar from "@/assets/js/layout/base/header-topbar.js";

export default {
    name: "KTHeaderMobile",
    components: {},
    mounted() {
        // Init Header Topbar For Mobile Mode
        KTLayoutHeaderTopbar.init(this.$refs["kt_header_mobile_topbar_toggle"]);
    },
    computed: {
        ...mapGetters(["layoutConfig", "getClasses"]),

        /**
         * Get header logo
         * @returns {string}
         */
        headerLogo() {
            return process.env.BASE_URL + this.layoutConfig("self.logo.dark");
        },

        /**
         * Get classes for mobile header
         * @returns {null|*}
         */
        headerClasses() {
            const classes = this.getClasses("header_mobile");
            if (typeof classes !== "undefined") {
                return classes.join(" ");
            }
            return null;
        },

        /**
         * Check if the left aside menu is enabled
         * @returns {boolean}
         */
        asideEnabled() {
            return !!this.layoutConfig("aside.self.display");
        }
    }
};
</script>

<style scoped>
.himoto-header-logo {
    display: block;
    width: 140px;
    max-height: 30px;
    padding: 6px 10px;
    border-radius: 5px;
    background: #ed1c24;
    object-fit: contain;
}

.header-mobile .burger-icon,
.header-mobile #kt_header_mobile_topbar_toggle {
    width: auto;
    min-height: 30px;
    padding: 0 8px !important;
    font-size: 12px;
    line-height: 30px;
    white-space: nowrap;
}
</style>
