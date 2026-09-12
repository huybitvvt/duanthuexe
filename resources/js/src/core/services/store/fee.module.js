import ApiService from "@/core/services/api.service";

// action types
 
export const DELETE_FEE = "delete_fee";
export const PUT_OR_POST_FEE = "PUT_OR_POST_FEE";
 
 
// set

const state = {};

const getters = {};

const actions = {
 

    /**
     * api delete order
     * @param context
     * @param id
     * @returns {Promise<unknown>}
     */
    [DELETE_FEE](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.delete(`/api/auth/transactions/${id}`)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
    [PUT_OR_POST_FEE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post(`/api/auth/transactions/${payload.id}`,payload)
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
    state, actions, mutations, getters
};
