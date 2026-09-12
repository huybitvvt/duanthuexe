import ApiService from "@/core/services/api.service";

// action types
export const ROLE_GET_ALL = "role_get_all";


// set

const state = {};

const getters = {};

const actions = {
    [ROLE_GET_ALL](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/role/all", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    }
};

const mutations = {};

export default {
    state,
    actions,
    mutations,
    getters
};
