import Vue from "vue";
import App from "./App.vue";
import router from "./router";
import store from "@/core/services/store";
import ApiService from "@/core/services/api.service";
import {VERIFY_AUTH} from "@/core/services/store/auth.module";
import {RESET_LAYOUT_CONFIG} from "@/core/services/store/config.module";
import Element from 'element-ui';
import Paginate from 'vuejs-paginate';
import * as filters from '../src/filters'; // global filters
import {ValidationObserver, ValidationProvider, extend, localize, configure} from 'vee-validate';
import * as rules from 'vee-validate/dist/rules'
import vi from 'vee-validate/dist/locale/vi.json';
import 'vue2-datepicker/index.css';
import DatePicker from 'vue2-datepicker';
import VueMask from 'v-mask';
import Notifications from 'vue-notification'
import money from 'v-money';
import mixin from '../src/common/common.js'
import locale from 'element-ui/lib/locale/lang/vi'
import VueSweetalert2 from 'vue-sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

/*Veevalidate*/
Vue.component('ValidationObserver', ValidationObserver);
Vue.component('ValidationProvider', ValidationProvider);
// install rules and localization
Object.keys(rules).forEach(rule => {
    extend(rule, rules[rule]);
});
localize('vi', vi);
configure({
    classes: {
        valid: 'is-valid',
        invalid: 'is-invalid',
        dirty: ['is-dirty', 'is-dirty'], // multiple classes per flag!
        // ...
    }
})
/*End*/
Vue.use(money, {precision: 4})
Vue.use(Notifications)
Vue.use(VueMask);
Vue.use(Element, {locale})
Vue.component('paginate', Paginate);
Vue.component('date-picker', DatePicker);
Vue.config.productionTip = false;
Vue.use(VueSweetalert2);
// Global 3rd party plugins
import "popper.js";
import "tooltip.js";
import PerfectScrollbar from "perfect-scrollbar";

window.PerfectScrollbar = PerfectScrollbar;
import ClipboardJS from "clipboard";

window.ClipboardJS = ClipboardJS;

// Vue 3rd party plugins
import i18n from "@/core/plugins/vue-i18n";
import vuetify from "@/core/plugins/vuetify";
import "@/core/plugins/portal-vue";
import "@/core/plugins/bootstrap-vue";
import "@/core/plugins/perfect-scrollbar";
import "@/core/plugins/highlight-js";
import "@/core/plugins/inline-svg";
import "@/core/plugins/apexcharts";
import "@/core/plugins/treeselect";
import "@/core/plugins/metronic";
import "@mdi/font/css/materialdesignicons.css";
import "@/core/plugins/formvalidation";
import dragScroll from "@/directives/dragScroll";

Vue.directive("drag-scroll", dragScroll);

// API service init
ApiService.init();

// Remove this to disable mock API
// MockService.init();

router.beforeEach((to, from, next) => {
    // Ensure we checked auth before each page load.
    Promise.all([store.dispatch(VERIFY_AUTH)]).then(next);

    // reset config to initial state
    store.dispatch(RESET_LAYOUT_CONFIG);

    // Scroll page to top on every route change
    setTimeout(() => {
        window.scrollTo(0, 0);
    }, 100);
});
Object.keys(filters).forEach(key => {
    Vue.filter(key, filters[key]);
});
Vue.mixin(mixin);
window.__HIMOTO_ROUTER__ = router;
window.__HIMOTO_STORE__ = store;
new Vue({
    router,
    store,
    i18n,
    vuetify,
    render: (h) => h(App),
}).$mount("#app");

