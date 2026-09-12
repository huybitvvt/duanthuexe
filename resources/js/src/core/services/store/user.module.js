import ApiService from "@/core/services/api.service";

// action types
export const USER_GET_ALL = "user_get_all";
export const USER_CREATE = "user_create";
export const USER_UPDATE = "user_update";
export const USER_GET_STAFF_BY_STORE = "user_get_staff_by_store";
export const USER_CHANGE_PASSWORD = "user_change_password";
export const USER_DELETE = "user_delete";
export const USER_CHANGE_MY_PASSWORD = "user_change_my_password";

// set

const state = {};

const getters = {};

const actions = {
    [USER_CHANGE_PASSWORD](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post('api/auth/users/change-password', payload).then(({data}) => {
                resolve(data);
            }).catch(({response}) => {
                reject(response)
            });
        })
    },
    [USER_GET_ALL](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/users", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
    [USER_GET_STAFF_BY_STORE](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/users/get-staff-by-store", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
    [USER_CREATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/users/store", payload).then(({data}) => {
                resolve(data);
            }).catch(({response}) => {
                reject(response)
            });
        })
    },

    [USER_UPDATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/users/update", payload).then(({data}) => {
                resolve(data);
            }).catch(({response}) => {
                reject(response);
            });
        })
    },

    [USER_DELETE](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.delete(`/api/auth/users/${id}`)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },

	[USER_CHANGE_MY_PASSWORD](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post('api/auth/users/change-my-password', payload).then(({data}) => {
                resolve(data);
            }).catch(({response}) => {
                reject(response)
            });
        })
    },
};

const mutations = {};

export default {
    state,
    actions,
    mutations,
    getters
};
