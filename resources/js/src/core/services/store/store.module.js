import ApiService from "@/core/services/api.service";
import { PURGE_AUTH } from "./auth.module";

// action types
export const STORE_GET_ALL = "store_get_all";
export const STORE_INDEX = "store_index";
export const STORE_CREATE = "store_create";
export const STORE_SHOW = "store_show";
export const STORE_UPDATE = "store_update";
// export const CUSTOMER_SHOW = "customers-show";
export const STORE_DELETE = "store_delete";
export const SET_SELECTED_STORE_ID = "setSelectedStoreId";

// set

const state = {
    selectedStoreId: localStorage.getItem("himoto_store_id") || "all",
    storeList: [],
    storesLoaded: false,
    storesLastFetchedAt: 0,
    storesSessionId: null,
    storesGeneration: 0,
};

const getters = {
    selectedStoreId: (state) => state.selectedStoreId,
    allStores: (state) => state.storeList,
    isStoresFresh: (state) => state.storesLoaded && (Date.now() - state.storesLastFetchedAt < 60000)
};

let pendingDefaultStoreRequest = null;

const actions = {
    [STORE_GET_ALL](context, credentials) {
        // Cache-first: If requesting all stores with no specific filters and data is fresh (< 60s)
        const isDefaultQuery = !credentials || Object.keys(credentials).length === 0;
        const sessionId = context.rootGetters.authSessionId;
        const generation = context.state.storesGeneration;
        if (isDefaultQuery && context.state.storesSessionId === sessionId && context.state.storesLoaded && (Date.now() - context.state.storesLastFetchedAt < 60000)) {
            return Promise.resolve({ data: context.state.storeList });
        }
        if (isDefaultQuery && pendingDefaultStoreRequest?.sessionId === sessionId) {
            return pendingDefaultStoreRequest.promise;
        }

        const request = ApiService.query("/api/auth/stores/all", credentials)
            .then(({data}) => {
                if (isDefaultQuery && context.state.storesGeneration === generation && context.rootGetters.authSessionId === sessionId && context.rootGetters.isAuthenticated) {
                    context.commit("SET_CACHED_STORES", { stores: data.data || data, sessionId });
                }
                return data;
            })
            .catch((err) => {
                throw err?.response || err;
            });

        if (!isDefaultQuery) {
            return request;
        }

        const pending = { sessionId, promise: null };
        pending.promise = request.finally(() => {
            if (pendingDefaultStoreRequest === pending) pendingDefaultStoreRequest = null;
        });
        pendingDefaultStoreRequest = pending;
        return pending.promise;
    },
    [STORE_INDEX](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/stores", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
    [STORE_CREATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/stores", payload)
                .then(({data}) => {
                    context.commit("INVALIDATE_CACHED_STORES");
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
    [STORE_SHOW](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.get(`/api/auth/stores/${id}`)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },

    [STORE_UPDATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post(`/api/auth/stores/${payload.id}?_method=PUT`, payload)
                .then(({data}) => {
                    context.commit("INVALIDATE_CACHED_STORES");
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
    
    [STORE_DELETE](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.delete(`/api/auth/stores/${id}`)
                .then(({data}) => {
                    context.commit("INVALIDATE_CACHED_STORES");
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
    //
    // [SEARCH_CUSTOMER_ID_CARD](context, payload) {
    //     return new Promise((resolve, reject) => {
    //         ApiService.query(`/api/auth/customers/search-by-id-card`, payload)
    //             .then(({data}) => {
    //                 resolve(data);
    //             })
    //             .catch(({response}) => {
    //                 reject(response);
    //             });
    //     });
    // },
    [SET_SELECTED_STORE_ID](context, storeId) {
        context.commit(SET_SELECTED_STORE_ID, storeId);
    },
};

const mutations = {
    [SET_SELECTED_STORE_ID](state, storeId) {
        state.selectedStoreId = storeId;
        localStorage.setItem("himoto_store_id", storeId);
    },
    SET_CACHED_STORES(state, { stores, sessionId }) {
        state.storeList = stores;
        state.storesSessionId = sessionId;
        state.storesLoaded = true;
        state.storesLastFetchedAt = Date.now();
    },
    INVALIDATE_CACHED_STORES(state) {
        state.storesGeneration++;
        pendingDefaultStoreRequest = null;
        state.storesLoaded = false;
        state.storesLastFetchedAt = 0;
    },
    [PURGE_AUTH](state) {
        state.storesGeneration++;
        state.storeList = [];
        state.storesSessionId = null;
        state.storesLoaded = false;
        state.storesLastFetchedAt = 0;
        pendingDefaultStoreRequest = null;
    }
};

export default {
    state,
    actions,
    mutations,
    getters
};
