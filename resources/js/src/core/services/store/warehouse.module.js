import ApiService from "@/core/services/api.service";

export const WAREHOUSE_GET_SUMMARY = "warehouse_get_summary";
export const WAREHOUSE_GET_VEHICLES = "warehouse_get_vehicles";
export const WAREHOUSE_DISPATCH_TRANSFER = "warehouse_dispatch_transfer";
export const WAREHOUSE_RECEIVE_TRANSFER = "warehouse_receive_transfer";
export const WAREHOUSE_CANCEL_TRANSFER = "warehouse_cancel_transfer";
export const WAREHOUSE_RETURN_DIFFERENT_STORE = "warehouse_return_different_store";
export const WAREHOUSE_EXCHANGE_VEHICLE = "warehouse_exchange_vehicle";
export const WAREHOUSE_GET_MOVEMENT_HISTORY = "warehouse_get_movement_history";

const state = {
    summaryList: [],
    selectedWarehouseId: null,
};

const getters = {
    warehouseSummary: (state) => state.summaryList,
    selectedWarehouseId: (state) => state.selectedWarehouseId,
};

const mutations = {
    SET_WAREHOUSE_SUMMARY(state, list) {
        state.summaryList = list;
    },
    SET_SELECTED_WAREHOUSE_ID(state, id) {
        state.selectedWarehouseId = id;
    },
};

const actions = {
    [WAREHOUSE_GET_SUMMARY](context, params) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/warehouses/summary", params || {})
                .then(({ data }) => {
                    context.commit("SET_WAREHOUSE_SUMMARY", data.data || []);
                    resolve(data);
                })
                .catch((err) => {
                    reject(err?.response || err);
                });
        });
    },

    [WAREHOUSE_GET_VEHICLES](context, { storeId, params }) {
        return new Promise((resolve, reject) => {
            ApiService.query(`/api/auth/warehouses/${storeId}/vehicles`, params || {})
                .then(({ data }) => {
                    resolve(data);
                })
                .catch((err) => {
                    reject(err?.response || err);
                });
        });
    },

    [WAREHOUSE_DISPATCH_TRANSFER](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/warehouses/transfers", payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch((err) => {
                    reject(err?.response || err);
                });
        });
    },

    [WAREHOUSE_RECEIVE_TRANSFER](context, { transferId, payload }) {
        return new Promise((resolve, reject) => {
            ApiService.post(`/api/auth/warehouses/transfers/${transferId}/receive`, payload || {})
                .then(({ data }) => {
                    resolve(data);
                })
                .catch((err) => {
                    reject(err?.response || err);
                });
        });
    },

    [WAREHOUSE_CANCEL_TRANSFER](context, { transferId, reason }) {
        return new Promise((resolve, reject) => {
            ApiService.post(`/api/auth/warehouses/transfers/${transferId}/cancel`, { reason })
                .then(({ data }) => {
                    resolve(data);
                })
                .catch((err) => {
                    reject(err?.response || err);
                });
        });
    },

    [WAREHOUSE_RETURN_DIFFERENT_STORE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/warehouses/return-different-store", payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch((err) => {
                    reject(err?.response || err);
                });
        });
    },

    [WAREHOUSE_EXCHANGE_VEHICLE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/warehouses/vehicle-exchange", payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch((err) => {
                    reject(err?.response || err);
                });
        });
    },

    [WAREHOUSE_GET_MOVEMENT_HISTORY](context, vehicleId) {
        return new Promise((resolve, reject) => {
            ApiService.get(`/api/auth/vehicles/${vehicleId}/movement-history`)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch((err) => {
                    reject(err?.response || err);
                });
        });
    },
};

export default {
    state,
    actions,
    mutations,
    getters,
};
