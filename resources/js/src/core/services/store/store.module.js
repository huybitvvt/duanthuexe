import ApiService from "@/core/services/api.service";

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
    selectedStoreId: localStorage.getItem("himoto_store_id") || "all"
};

const getters = {
    selectedStoreId: (state) => state.selectedStoreId
};

const actions = {
    [STORE_GET_ALL](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/stores/all", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
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
    }
};

export default {
    state,
    actions,
    mutations,
    getters
};
