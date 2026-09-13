import ApiService from "@/core/services/api.service";

// action types
export const DASHBOARD_REPORT = "dashboard_report";
export const DASHBOARD_REPORT_CHART = "dashboard_report_chart";

const state = {
    reportCache: {},
    chartCache: {}
};

const getters = {};

const actions = {
    [DASHBOARD_REPORT](context, credentials) {
        const storeKey = credentials?.store_id ? String(credentials.store_id) : "all";
        const cached = context.state.reportCache[storeKey];
        if (cached && (Date.now() - cached.timestamp < 30000)) {
            return Promise.resolve(cached.data);
        }

        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/dashboard/report", credentials)
                .then(({data}) => {
                    context.commit("SET_DASHBOARD_REPORT_CACHE", { key: storeKey, data });
                    resolve(data);
                })
                .catch((error) => {
                    reject(error?.response || error);
                });
        });
    },

    [DASHBOARD_REPORT_CHART](context, credentials) {
        const storeKey = credentials?.store_id ? String(credentials.store_id) : "all";
        const cached = context.state.chartCache[storeKey];
        if (cached && (Date.now() - cached.timestamp < 30000)) {
            return Promise.resolve(cached.data);
        }

        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/dashboard/report-chart", credentials)
                .then(({data}) => {
                    context.commit("SET_DASHBOARD_CHART_CACHE", { key: storeKey, data });
                    resolve(data);
                })
                .catch((error) => {
                    reject(error?.response || error);
                });
        });
    },
};

const mutations = {
    SET_DASHBOARD_REPORT_CACHE(state, { key, data }) {
        state.reportCache[key] = { data, timestamp: Date.now() };
    },
    SET_DASHBOARD_CHART_CACHE(state, { key, data }) {
        state.chartCache[key] = { data, timestamp: Date.now() };
    }
};

export default {
    state,
    actions,
    mutations,
    getters
};

