import ApiService from "@/core/services/api.service";

// action types
export const BANK_INDEX = "banks";
export const BANK_CREATE = "banks-create";
export const BANK_UPDATE = "banks-update";
export const BANK_SHOW = "banks-show";
export const BANK_DELETE = "banks-delete";
export const BANK_GET_ALL = "banks-get-all";

// set

const state = {};

const getters = {};

const actions = {
    [BANK_GET_ALL](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/banks/all", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
    [BANK_INDEX](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/banks", credentials)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [BANK_CREATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/banks", payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [BANK_SHOW](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.get(`/api/auth/banks/${id}`)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },

    [BANK_UPDATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post(
                `/api/auth/banks/update/${payload.id}?_method=PUT`,
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

    [BANK_DELETE](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.delete(`/api/auth/banks/${id}`)
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
