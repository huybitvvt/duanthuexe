import ApiService from "@/core/services/api.service";

// action types
export const CUSTOMER_INDEX = "customers";
export const CUSTOMER_CREATE = "customers-create";
export const CUSTOMER_UPDATE = "customers-update";
export const CUSTOMER_SHOW = "customers-show";
export const CUSTOMER_DELETE = "customers-delete";
export const SEARCH_CUSTOMER_ID_CARD = "customers-search-by-id-card";

// set

const state = {};

const getters = {};

const actions = {
    [CUSTOMER_INDEX](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/customers", credentials)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [CUSTOMER_CREATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/customers", payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [CUSTOMER_SHOW](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.get(`/api/auth/customers/${id}`)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },

    [CUSTOMER_UPDATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post(
                `/api/auth/customers/${payload.id}?_method=PUT`,
                payload.params
            )
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },

    [CUSTOMER_DELETE](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.delete(`/api/auth/customers/${id}`)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },

    [SEARCH_CUSTOMER_ID_CARD](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.query(`/api/auth/customers/search-by-id-card`, payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
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
    getters,
};
