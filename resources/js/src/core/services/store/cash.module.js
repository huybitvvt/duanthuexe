import ApiService from "@/core/services/api.service";

// action types
export const CASH_INDEX = "cash";
export const CASH_CREATE = "cash-create";
export const CASH_UPDATE = "cash-update";
export const CASH_SHOW = "cash-show";
export const CASH_DELETE = "cash-delete";
export const CASH_GET_ALL = "cash-get-all";

// set

const state = {};

const getters = {};

const actions = {
    [CASH_GET_ALL](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/cash/all", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
    [CASH_INDEX](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/cash", credentials)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [CASH_CREATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/cash", payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [CASH_SHOW](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.get(`/api/auth/cash/${id}`)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },

    [CASH_UPDATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post(
                `/api/auth/cash/update/${payload.id}?_method=PUT`,
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

    [CASH_DELETE](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.delete(`/api/auth/cash/${id}`)
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
