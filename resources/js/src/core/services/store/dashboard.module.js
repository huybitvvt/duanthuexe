import ApiService from "@/core/services/api.service";

// action types
export const DASHBOARD_REPORT = "dashboard_report";
export const DASHBOARD_REPORT_CHART = "dashboard_report_chart";

// set

const state = {};

const getters = {};

const actions = {

    [DASHBOARD_REPORT](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/dashboard/report", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },

    [DASHBOARD_REPORT_CHART](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/dashboard/report-chart", credentials)
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
    getters
};
