import ApiService from "@/core/services/api.service";

// action types
export const TRANSACTION_GET_LIST = "transaction_get_list";
export const TRANSACTION_DELETE = "transaction_delete";
export const TRANSACTION_STATS = "transaction_stats";
export const TRANSACTION_UPDATE = "transaction_update";

// set

const state = {};

const getters = {};

const actions = {
    [TRANSACTION_GET_LIST](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/transactions", credentials)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [TRANSACTION_DELETE](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.delete(`/api/auth/transactions/${id}`)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [TRANSACTION_STATS](context, payload) {
        return new Promise((res, rej) => {
            ApiService.query("/api/auth/transactions/stats", payload)
                .then(({ data }) => res(data))
                .catch(({ response }) => {
                    rej(response);
                });
        });
    },
	[TRANSACTION_UPDATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/transactions/update", payload.params)
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
    getters,
};
