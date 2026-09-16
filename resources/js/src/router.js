import Vue from "vue";
import Router from "vue-router";

Vue.use(Router);

export default new Router({
    mode: "history",
    routes: [
        {
            path: "/",
            redirect: "/dashboard",
            component: () => import("@/view/layout/Layout"),
            children: [
                {
                    path: "/dashboard",
                    name: "dashboard",
                    component: () => import("@/view/pages/Dashboard.vue"),
                },
                {
                    path: "/vehicles",
                    name: "vehicle",
                    component: () =>
                        import("@/view/pages/vehicles/VehicleIndex.vue"),
                },
                {
                    path: "/warehouses",
                    name: "warehouse",
                    component: () =>
                        import("@/view/pages/warehouse/WarehouseIndex.vue"),
                },
                {
                    path: "/leads",
                    name: "leads",
                    component: () => import("@/view/pages/lead/LeadIndex.vue"),
                },
                {
                    path: "/maintenance-rule",
                    name: "maintenance-rule",
                    component: () =>
                        import(
                            "@/view/pages/maintenance-rule/MaintenanceRule.vue"
                        ),
                },
                {
                    path: "/maintenance-type",
                    name: "maintenance-type",
                    component: () =>
                        import(
                            "@/view/pages/maintenance-type/MaintenanceType.vue"
                        ),
                },
                {
                    path: "/maintenance-log",
                    name: "maintenance-log",
                    component: () =>
                        import(
                            "@/view/pages/maintenance-log/MaintenanceLog.vue"
                        ),
                },
                {
                    path: "/maintenance-schedule",
                    name: "maintenance-schedule",
                    component: () =>
                        import(
                            "@/view/pages/maintenance-schedule/MaintenanceSchedule.vue"
                        ),
                },
                {
                    path: "/customers",
                    name: "customers",
                    component: () =>
                        import("@/view/pages/customers/CustomerIndex.vue"),
                    children: [],
                },
                {
                    path: "/customers-create",
                    name: "customers-create",
                    component: () =>
                        import("@/view/pages/customers/CustomerCreate.vue"),
                },
                {
                    path: "/customers-update/:id",
                    name: "customers-update",
                    component: () =>
                        import("@/view/pages/customers/CustomerUpdate.vue"),
                },
                {
                    path: "/banks",
                    name: "banks",
                    component: () => import("@/view/pages/banks/BankIndex.vue"),
                    children: [],
                },

                {
                    path: "/banks-create",
                    name: "banks-create",
                    component: () =>
                        import("@/view/pages/banks/BankCreate.vue"),
                },
                {
                    path: "/banks-update/:id",
                    name: "banks-update",
                    component: () =>
                        import("@/view/pages/banks/BankUpdate.vue"),
                },
                {
                    path: "/cash",
                    name: "cash",
                    component: () => import("@/view/pages/cash/CashIndex.vue"),
                    children: [],
                },

                {
                    path: "/cash-create",
                    name: "cash-create",
                    component: () => import("@/view/pages/cash/CashCreate.vue"),
                },
                {
                    path: "/cash-update/:id",
                    name: "cash-update",
                    component: () => import("@/view/pages/cash/CashUpdate.vue"),
                },
                {
                    path: "/transactions",
                    name: "transactions",
                    component: () =>
                        import("@/view/pages/finances/Transaction.vue"),
                },
                {
                    path: "/receipt",
                    name: "receipt",
                    component: () =>
                        import("@/view/pages/finances/ReceiptIndex.vue"),
                },
                {
                    path: "/receipt/create",
                    name: "receipt-create",
                    component: () =>
                        import("@/view/pages/finances/ReceiptCreate.vue"),
                },
                {
                    path: "/receipt/update",
                    name: "receipt-update",
                    component: () =>
                        import("@/view/pages/finances/ReceiptCreate.vue"),
                },
                {
                    path: "/stores",
                    name: "stores",
                    component: () =>
                        import("@/view/pages/stores/StoreIndex.vue"),
                    children: [],
                },
                {
                    path: "/store-create",
                    name: "stores-create",
                    component: () =>
                        import("@/view/pages/stores/StoreCreate.vue"),
                },
                {
                    path: "/store-update/:id",
                    name: "stores-update",
                    component: () =>
                        import("@/view/pages/stores/StoreUpdate.vue"),
                    children: [],
                },
                {
                    path: "/pricing",
                    name: "pricing",
                    component: () =>
                        import("@/view/pages/prices/PriceIndex.vue"),
                },
                {
                    path: "/user",
                    name: "user",
                    component: () => import("@/view/pages/users/UserIndex.vue"),
                },
                {
                    path: "/car-rental",
                    name: "car-rental",
                    component: () =>
                        import("@/view/pages/Order/OrderCarRental.vue"),
                },
                {
                    path: "/car-sell",
                    name: "car-sell",
                    component: () =>
                        import("@/view/pages/order-sell/OrderSellIndex.vue"),
                },
                {
                    path: "/lease-to-own",
                    name: "lease-to-own",
                    component: () =>
                        import("@/view/pages/lease-to-own/LeaseIndex.vue"),
                },
                {
                    path: "/report/detail-report",
                    name: "report-car-rental",
                    component: () =>
                        import("@/view/pages/report/ReportCardRental.vue"),
                },
                {
                    path: "/report/vehicle-revenue",
                    name: "report-vehicle-revenue",
                    component: () =>
                        import("@/view/pages/report/ReportVehicleRevenue.vue"),
                },
                {
                    path: "/orders",
                    redirect: "/car-rental",
                },
                {
                    path: "/roles",
                    redirect: "/user",
                },
            ],
        },
        {
            path: "/",
            component: () => import("@/view/pages/auth/Auth.vue"),
            children: [
                {
                    name: "login",
                    path: "/login",
                    component: () => import("@/view/pages/auth/Auth.vue"),
                },
                {
                    name: "register",
                    path: "/register",
                    component: () => import("@/view/pages/auth/Auth.vue"),
                },
                {
                    name: "ref",
                    path: "ref/:id",
                    component: () => import("@/view/pages/auth/Auth.vue"),
                },
            ],
        },
        {
            path: "*",
            redirect: "/404",
        },
        {
            // the 404 route, when none of the above matches
            path: "/404",
            name: "404",
            component: () => import("@/view/pages/error/Error-1.vue"),
        },
    ],
});
