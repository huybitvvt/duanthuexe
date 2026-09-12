import ApiService from "@/core/services/api.service";

// action types
export const RECEIPT_INDEX = "receipt";
export const RECEIPT_CREATE = "receipt-create";
export const RECEIPT_UPDATE = "receipt-update";
export const RECEIPT_SHOW = "receipt-show";
export const RECEIPT_DELETE = "receipt-delete";
export const RECEIPT_GET_ALL = "receipt-get-all";

// set

const state = {};

const getters = {};

const actions = {
   
    [RECEIPT_INDEX](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/receipt", credentials)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
   
    [RECEIPT_SHOW](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.get(`/api/auth/receipt/${id}`)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [RECEIPT_CREATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/receipt", payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [RECEIPT_UPDATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post(
                `/api/auth/receipt/${payload.id}?_method=PUT`,
                payload
            )
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },

    [RECEIPT_DELETE](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.delete(`/api/auth/receipt/${id}`)
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
