import Vue from "vue";
import Vuex from "vuex";

import auth from "./auth.module";
import htmlClass from "./htmlclass.module";
import config from "./config.module";
import breadcrumbs from "./breadcrumbs.module";
import profile from "./profile.module";
import package1 from "./package.module";
import order from "./order.module";
import vehicle from "./vehicle.module";
import customers from "./customers.module";
import banks from "./banks.module";
import cash from "./cash.module";
import store from "./store.module";
import orderSell from "./orderSell.module";
import transaction from "./transaction.module";
import dashboard from "./dashboard.module";
import receipt from "./receipt.module";
import report from "./report.module";
import role from "./role.module";
import user from "./user.module";
import fee from "./fee.module";
import exports from "./exports.module";
import lead from './lead.module';
import navigate from './navigate.module';
import file from './file.module';
import warehouse from './warehouse.module';
import lease from './lease.module';

Vue.use(Vuex);

export default new Vuex.Store({
    modules: {
        auth,
        htmlClass,
        config,
        breadcrumbs,
        profile,
        package1,
        order,
        vehicle,
        store,
        banks,
        cash,
        customers,
        orderSell,
        transaction,
        dashboard,
        receipt,
        report,
        role,
        user,
        fee,
        exports,
        lead,
        navigate,
		file,
        warehouse,
        lease,
    },
});
