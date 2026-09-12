import ApiService from "@/core/services/api.service";

// action types
export const ORDER_SELL_GET_ALL = "order_sell_get_all";
export const ORDER_SELL_GET_ALL_REPORT = "order_sell_get_all_report";
export const ORDER_SELL_CREATE = "order_sell_create";
export const ORDER_SELL_UPDATE = "order_sell_update";
export const ORDER_SELL_DELETE = "order_sell_delete";

// set

const state = {};

const getters = {};

const actions = {
    [ORDER_SELL_GET_ALL](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/order-sell", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
    [ORDER_SELL_GET_ALL_REPORT](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/order-sell/report", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
    [ORDER_SELL_CREATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/order-sell/store", payload).then(({data}) => {
                resolve(data);
            }).catch(({response}) => {
                reject(response)
            });
        })
    },
    [ORDER_SELL_UPDATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post('/api/auth/order-sell/update', payload).then(({data}) => {
                resolve(data);
            }).catch(({response}) => {
                reject(response);
            });
        })
    },
    [ORDER_SELL_DELETE](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.delete(`/api/auth/order-sell/${id}`)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
};

const mutations = {};

export default {
    state,
    actions,
    mutations,
    getters
};
