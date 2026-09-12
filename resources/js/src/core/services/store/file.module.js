import ApiService from "@/core/services/api.service";

// action types
export const FILE_INDEX = "file";
export const FILE_CREATE = "file-create";
export const FILE_UPDATE = "file-update";
export const FILE_SHOW = "file-show";
export const FILE_DELETE = "file-delete";
export const FILE_GET_ALL = "file-get-all";

// set

const state = {};

const getters = {};

const actions = {
    [FILE_GET_ALL](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/file/all", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
    [FILE_INDEX](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/file", credentials)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [FILE_CREATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/file", payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [FILE_SHOW](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.get(`/api/auth/file/${id}`)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },

    [FILE_UPDATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post(
                `/api/auth/file/update/${payload.id}?_method=PUT`,
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

    [FILE_DELETE](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.delete(`/api/auth/file/${id}`)
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
