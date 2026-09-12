import ApiService from "@/core/services/api.service";

// action types
export const GET_PACKAGES = "get_packages";
export const INVESTMENT_POST = "investment_post";
export const PACKAGE_CLAIM = "package_claim";
export const GET_HISTORY_INVESTMENT = "get_history_investment";
export const SUM_SVL = "sum_svl";
export const REPORT = "report";

// set
export const SET_SUM_SVL = "set_sum_svl";

const state = {
    amountSvl: 0
};

const getters = {
    amountSvl(state) {
        return state.amountSvl;
    },
};

const actions = {
    [GET_PACKAGES](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/get-package", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },

    [REPORT](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/report", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },

    [GET_HISTORY_INVESTMENT](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/get-history-investment", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
    [SUM_SVL](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/user-package/sum-svl", credentials)
                .then(({data}) => {
                    context.commit(SET_SUM_SVL, data);
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },


    [INVESTMENT_POST](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/user-package/store", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },

    [PACKAGE_CLAIM](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/user-package/claim", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
};

const mutations = {
    [SET_SUM_SVL](state, data) {
        state.amountSvl = data.data;
    },
};

export default {
    state,
    actions,
    mutations,
    getters
};
